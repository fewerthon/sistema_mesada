<?php
require_once ROOT_PATH . '/db.php';
// caminho para url
$baseUrl = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = rtrim($baseUrl, '/'); // remove barra final
$baseUrl = preg_replace('/\/(admin|filho)$/', '', $baseUrl);

function today_date(): string { return date('Y-m-d'); }
function ymd_to_weekday(string $ymd): int { return (int)date('w', strtotime($ymd)); } // 0=Dom
function days_in_month_for_date(string $ymd): int { return (int)date('t', strtotime($ymd)); }
function money_br(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
function get_user_by_id(int $id): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}
function require_login(): void {
    global $baseUrl;
    if (empty($_SESSION['user_id'])) { header('Location: '.$baseUrl.'/login.php'); exit; }
}
function require_role(string $role): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) { http_response_code(403); exit('Acesso negado.'); }
}
function is_supervisor(): bool { return ($_SESSION['role'] ?? '') === 'supervisor'; }
function is_filho(): bool { return ($_SESSION['role'] ?? '') === 'filho'; }
function valor_diario(array $user, string $date): float {
    $mesada = (float)($user['mesada'] ?? 0);
    $dias = days_in_month_for_date($date);
    return $dias > 0 ? ($mesada / $dias) : 0.0;
}
function tarefas_do_dia_para_usuario(int $user_id, int $dia_semana): array {
    $sql = 'SELECT t.id as tarefa_id, t.titulo, t.peso
            FROM tarefas_usuario tu
            JOIN tarefas t ON t.id = tu.tarefa_id AND t.ativo = 1
            WHERE tu.user_id = :uid AND tu.dia_semana = :d
            ORDER BY t.titulo';
    $st = db()->prepare($sql);
    $st->execute([':uid' => $user_id, ':d' => $dia_semana]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function map_valores_por_tarefa(array $user, string $date, array $tarefas_dia): array {
    $vd = valor_diario($user, $date);
    $peso_total = array_sum(array_map(fn($t) => (int)$t['peso'], $tarefas_dia));
    if ($peso_total <= 0) { return []; }
    $map = [];
    foreach ($tarefas_dia as $t) {
        $map[$t['tarefa_id']] = $vd * ((int)$t['peso'] / $peso_total);
    }
    return $map;
}
function status_tarefa_no_dia(int $user_id, int $tarefa_id, string $date): int {
    $st = db()->prepare('SELECT concluida FROM tarefas_status WHERE user_id = ? AND tarefa_id = ? AND data = ?');
    $st->execute([$user_id, $tarefa_id, $date]);
    $r = $st->fetchColumn();
    return $r ? (int)$r : 0;
}
function set_status_tarefa_no_dia(int $user_id, int $tarefa_id, string $date, int $concluida): void {
    $pdo = db();
    $pdo->prepare('INSERT INTO tarefas_status (user_id, tarefa_id, data, concluida)
                   VALUES (?, ?, ?, ?)
                   ON CONFLICT(user_id, tarefa_id, data) DO UPDATE SET concluida = excluded.concluida')
        ->execute([$user_id, $tarefa_id, $date, $concluida ? 1 : 0]);
}
function ganhos_no_periodo(int $user_id, string $inicio, string $fim): array {
    $user = get_user_by_id($user_id);
    if (!$user) return ['ganho'=>0,'perda'=>0,'total_previsto'=>0];
    $inicio_ts = strtotime($inicio);
    $fim_ts = strtotime($fim);
    $ganho = 0.0; $perda = 0.0; $prev = 0.0;
    for ($ts = $inicio_ts; $ts <= $fim_ts; $ts += 86400) {
        $date = date('Y-m-d', $ts);
        $dow = (int)date('w', $ts);
        $tarefas = tarefas_do_dia_para_usuario($user_id, $dow);
        if (!$tarefas) continue;
        $map_val = map_valores_por_tarefa($user, $date, $tarefas);
        $vd = array_sum($map_val);
        $prev += $vd;
        foreach ($tarefas as $t) {
            $v = $map_val[$t['tarefa_id']] ?? 0;
            $done = status_tarefa_no_dia($user_id, (int)$t['tarefa_id'], $date);
            if ($done) $ganho += $v; else $perda += $v;
        }
    }
    return ['ganho'=>$ganho, 'perda'=>$perda, 'total_previsto'=>$prev];
}
?>
