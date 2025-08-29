<?php
require_once 'layout.php';

$user = get_user_by_id((int)$_SESSION['user_id']);
$view = $_GET['view'] ?? 'semanal';
$ref  = $_GET['date'] ?? today_date();

function week_bounds(string $date): array {
  $ts = strtotime($date);
  $dow = (int)date('w', $ts);
  $start = date('Y-m-d', strtotime("-{$dow} day", $ts));
  $end   = date('Y-m-d', strtotime('+' . (6 - $dow) . ' day', $ts));
  return [$start, $end];
}

if ($view === 'mensal') {
  $start = date('Y-m-01', strtotime($ref));
  $end   = date('Y-m-t',  strtotime($ref));
} else {
  [$start, $end] = week_bounds($ref);
}

$pdo = db();

// 1) Pré-cálculo: percorre o intervalo, respeita DESDE e acumula totais
$totais = ['ganho'=>0.0, 'perda'=>0.0, 'total_previsto'=>0.0];
$dias = []; // armazena dados do dia para reutilizar ao renderizar a tabela

for ($ts = strtotime($start); $ts <= strtotime($end); $ts += 86400) {
  $d = date('Y-m-d', $ts);
  $dow = (int)date('w', $ts);

  // Busca tarefas válidas no dia (respeita DESDE do vínculo)
  $st = $pdo->prepare("
    SELECT tu.tarefa_id, t.titulo, t.peso
      FROM tarefas_usuario tu
      JOIN tarefas t ON t.id = tu.tarefa_id
     WHERE tu.user_id = ?
       AND tu.dia_semana = ?
       AND t.ativo = 1
       AND date(tu.desde) <= date(?)
     ORDER BY t.titulo
  ");
  $st->execute([(int)$user['id'], $dow, $d]);
  $tarefas = $st->fetchAll(PDO::FETCH_ASSOC);
  if (!$tarefas) continue;

  $map_val = map_valores_por_tarefa($user, $d, $tarefas);

  // Acumula totais previstos (soma dos valores do dia)
  $totais['total_previsto'] += array_sum($map_val);

  // Acumula ganhos/perdas conforme status
  foreach ($tarefas as $t) {
    $done  = status_tarefa_no_dia((int)$user['id'], (int)$t['tarefa_id'], $d);
    $valor = (float)($map_val[$t['tarefa_id']] ?? 0.0);
    if ($done) $totais['ganho'] += $valor; else $totais['perda'] += $valor;
  }

  // Guarda para renderização
  $dias[] = [
    'data'     => $d,
    'tarefas'  => $tarefas,
    'map_val'  => $map_val,
  ];
}
?>
<div class="bg-white rounded shadow-sm p-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <a href="<?=$baseUrl;?>/filho/index.php" class="btn btn-sm btn-outline-secondary">Hoje</a>
      <a href="?view=semanal&date=<?php echo htmlspecialchars($ref); ?>" class="btn btn-sm <?php echo $view==='semanal'?'btn-primary':'btn-outline-primary'; ?>">Semanal</a>
      <a href="?view=mensal&date=<?php echo htmlspecialchars($ref); ?>" class="btn btn-sm <?php echo $view==='mensal'?'btn-primary':'btn-outline-primary'; ?>">Mensal</a>
    </div>
    <form class="d-flex align-items-center gap-2" method="get">
      <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
      <input type="date" class="form-control form-control-sm" name="date" value="<?php echo htmlspecialchars($ref); ?>">
      <button class="btn btn-sm btn-outline-secondary">Ir</button>
    </form>
  </div>

  <!-- 2) Cards no topo, já com os totais calculados -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
      <div class="p-3 border rounded h-100">
        <div class="text-muted">Previsto</div>
        <div class="fs-5"><?php echo money_br($totais['total_previsto']); ?></div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="p-3 border rounded h-100">
        <div class="text-muted">Ganho</div>
        <div class="fs-5 text-success"><?php echo money_br($totais['ganho']); ?></div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="p-3 border rounded h-100">
        <div class="text-muted">Descontos</div>
        <div class="fs-5 text-danger"><?php echo money_br($totais['perda']); ?></div>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Data</th><th>Tarefa</th><th>Peso</th><th>Valor</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($dias as $dia): ?>
        <?php
          $d = $dia['data'];
          $tarefas = $dia['tarefas'];
          $map_val = $dia['map_val'];
        ?>
        <?php foreach ($tarefas as $t): ?>
          <?php
            $done  = status_tarefa_no_dia((int)$user['id'], (int)$t['tarefa_id'], $d);
            $valor = (float)($map_val[$t['tarefa_id']] ?? 0.0);
          ?>
          <tr>
            <td><?php echo htmlspecialchars($d); ?></td>
            <td><?php echo htmlspecialchars($t['titulo']); ?></td>
            <td><?php echo (int)$t['peso']; ?></td>
            <td><?php echo money_br($valor); ?></td>
            <td><?php echo $done?'<span class="badge bg-success">Concluída</span>':'<span class="badge bg-secondary">Pendente</span>'; ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div><?php html_foot(); ?>