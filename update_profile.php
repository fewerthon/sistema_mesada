<?php
require_once __DIR__ . '/config.php';
require_once ROOT_PATH . '/util.php';

csrf_check();
require_login();

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$pass1   = $_POST['password_new'] ?? '';
$pass2   = $_POST['password_confirm'] ?? '';

header('Content-Type: application/json');

if ($user_id <= 0) { echo json_encode(['ok'=>false,'error'=>'Sessão inválida']); exit; }
if ($name === '' || mb_strlen($name) < 2) { echo json_encode(['ok'=>false,'error'=>'Nome muito curto']); exit; }
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'error'=>'E-mail inválido']); exit; }
if ($pass1 !== '' || $pass2 !== '') {
    if ($pass1 !== $pass2) { echo json_encode(['ok'=>false,'error'=>'As senhas não conferem']); exit; }
    if (strlen($pass1) < 6) { echo json_encode(['ok'=>false,'error'=>'A senha deve ter pelo menos 6 caracteres']); exit; }
}

// helpers de introspecção simples (SQLite)
function users_column(PDO $pdo, array $candidates, ?string $fallback = null): ?string {
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_map(fn($c)=>strtolower($c['name']), $cols);
    foreach ($candidates as $c) if (in_array(strtolower($c), $names, true)) return $c;
    return $fallback;
}
function looks_like_hash(string $s): bool {
    return (bool)preg_match('/^\$2[aby]\$|\$argon2/i', $s); // bcrypt/argon2
}

try {
    $pdo->beginTransaction();

    // 1) Atualiza nome (sempre) + e-mail (se existir coluna e foi informado)
    $emailCol = null;
    if ($email !== '') {
        $emailCol = users_column($pdo, ['email','mail','e_mail'], null);
        if (!$emailCol) { throw new RuntimeException('Coluna de e-mail não encontrada na tabela users'); }

        // unicidade (case-insensitive)
        $st = $pdo->prepare("SELECT id FROM users WHERE lower($emailCol)=lower(?) AND id<>? LIMIT 1");
        $st->execute([$email, $user_id]);
        if ($st->fetchColumn()) {
            throw new RuntimeException('E-mail já está em uso por outro usuário');
        }
    }

    // Atualiza nome (e e-mail se houver)
    if ($email !== '' && $emailCol) {
        $pdo->prepare("UPDATE users SET name=?, $emailCol=? WHERE id=?")
            ->execute([$name, $email, $user_id]);
    } else {
        $pdo->prepare("UPDATE users SET name=? WHERE id=?")
            ->execute([$name, $user_id]);
    }

    // 2) Atualiza senha (opcional)
    if ($pass1 !== '') {
        $passCol = users_column($pdo, ['password','senha','password_hash','pass','pwd'], 'password');

        // pega valor atual pra decidir se mantém hash
        $st = $pdo->prepare("SELECT $passCol FROM users WHERE id=?");
        $st->execute([$user_id]);
        $current = (string)$st->fetchColumn();

        // se já está hasheando hoje, mantém hasheado; se não, mantém texto (pra não quebrar login legado)
        $newPass = looks_like_hash($current) ? password_hash($pass1, PASSWORD_DEFAULT) : $pass1;

        $pdo->prepare("UPDATE users SET $passCol=? WHERE id=?")->execute([$newPass, $user_id]);
    }

    $pdo->commit();
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // retorna msg mais útil no dev
    echo json_encode(['ok'=>false, 'error'=>'Erro ao salvar: '.$e->getMessage()]);
}
