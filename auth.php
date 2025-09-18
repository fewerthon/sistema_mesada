<?php
require_once ROOT_PATH . '/db.php';
function find_user_by_email(string $email): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    $st->closeCursor();
    return $u ?: null;
}
function login_user(array $user): void {
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
}
function logout_user(): void {
    // apaga o registro no banco
    $st = db()->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
    $st->execute([$_SESSION['user_id']]);
    $st->closeCursor();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    setcookie('lembrar_de_mim', '', time() - 3600, '/');
    session_destroy();
}
?>
