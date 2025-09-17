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

// Agora respeita o DESDE (só conta vínculo ativo na data)
$st = db()->prepare('
  SELECT 1
    FROM tarefas_usuario
   WHERE user_id = ? AND tarefa_id = ? AND dia_semana = ?
     AND date(desde) <= date(?)
');
$st->execute([$target_user_id, $tarefa_id, $dow, $date]);
if (!$st->fetchColumn()) { http_response_code(403); exit('Tarefa não atribuída neste dia.'); }

set_status_tarefa_no_dia($target_user_id, $tarefa_id, $date, $set ? 1 : 0);

// Se marcou como CONCLUÍDA, congela o valor da tarefa e a mesada vigente no dia
if ($set) {
    $pdo = db();
    $user = get_user_by_id($target_user_id);

    // Tarefas válidas do dia (respeitando DESDE) para calcular rateio correto
    $st2 = $pdo->prepare("
      SELECT tu.tarefa_id, t.titulo, t.peso
        FROM tarefas_usuario tu
        JOIN tarefas t ON t.id = tu.tarefa_id
       WHERE tu.user_id = ?
         AND tu.dia_semana = ?
         AND t.ativo = 1
         AND date(tu.desde) <= date(?)
       ORDER BY t.titulo
    ");
    $st2->execute([$target_user_id, $dow, $date]);
    $tarefasDia = $st2->fetchAll(PDO::FETCH_ASSOC);

    $map_val = map_valores_por_tarefa($user, $date, $tarefasDia);
    $valor = (float)($map_val[$tarefa_id] ?? 0.0);
    $mesada_ref = (float)$user['mesada'];

    // Atualiza a linha correspondente na tabela de status (usa coluna "data")
    $pdo->prepare("
      UPDATE tarefas_status
         SET valor_tarefa = ?, mesada_ref = ?
       WHERE user_id = ? AND tarefa_id = ? AND date(data) = date(?)
    ")->execute([$valor, $mesada_ref, $target_user_id, $tarefa_id, $date]);
}
header('Content-Type: application/json');
echo json_encode(['ok'=>true]);