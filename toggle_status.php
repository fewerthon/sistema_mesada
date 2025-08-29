<?php
require_once __DIR__ . '/config.php';
require_once ROOT_PATH . '/util.php';
csrf_check();
require_login();
// Se supervisor, pode alterar status de um filho alvo.
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$target_user_id = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : $current_user_id;
$tarefa_id = (int)($_POST['tarefa_id'] ?? 0);
$date = $_POST['date'] ?? today_date();
$set = (int)($_POST['set'] ?? 0);
if ($tarefa_id <= 0 || $target_user_id <= 0) { http_response_code(400); exit('Parâmetros inválidos'); }
if (is_filho() && $date !== today_date()) { http_response_code(403); exit('Filhos só podem marcar tarefas de hoje.'); }
// Verifica se a tarefa está atribuída ao usuário-alvo neste dia da semana
$dow = ymd_to_weekday($date);
$st = db()->prepare('SELECT 1 FROM tarefas_usuario WHERE user_id=? AND tarefa_id=? AND dia_semana=?');
$st->execute([$target_user_id, $tarefa_id, $dow]);
if (!$st->fetchColumn()) { http_response_code(403); exit('Tarefa não atribuída neste dia.'); }
set_status_tarefa_no_dia($target_user_id, $tarefa_id, $date, $set ? 1 : 0);
header('Content-Type: application/json');
echo json_encode(['ok'=>true]);
?>
