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

// flag de exibição de valores
$showValues = (int)($pdo->query("SELECT value FROM config WHERE key='exibir_valores_filhos'")->fetchColumn()) === 1;

// Pré-cálculo (respeita DESDE e usa congelado quando concluída)
$totais = ['ganho'=>0.0, 'perda'=>0.0, 'total_previsto'=>0.0];
$dias = [];

for ($ts = strtotime($start); $ts <= strtotime($end); $ts += 86400) {
  $d = date('Y-m-d', $ts);
  $dow = (int)date('w', $ts);

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

  $stS = $pdo->prepare("SELECT tarefa_id, concluida, COALESCE(valor_tarefa,0) AS valor_tarefa FROM tarefas_status WHERE user_id=? AND date(data)=date(?)");
  $stS->execute([(int)$user['id'], $d]);
  $statusMap = [];
  foreach ($stS->fetchAll(PDO::FETCH_ASSOC) as $row) { $statusMap[(int)$row['tarefa_id']] = $row; }

  // Se não vamos mostrar valores, não precisamos calcular; mas manter para consistência não pesa.
  $map_val = $showValues ? map_valores_por_tarefa($user, $d, $tarefas) : [];

  $linhas = [];
  foreach ($tarefas as $t) {
    $tid = (int)$t['tarefa_id'];
    $done = (int)($statusMap[$tid]['concluida'] ?? 0) === 1;
    $valor_congelado = (float)($statusMap[$tid]['valor_tarefa'] ?? 0.0);
    $valor_calc = (float)($map_val[$tid] ?? 0.0);
    $valor = $done && $valor_congelado > 0 ? $valor_congelado : $valor_calc;

    if ($showValues) {
      if ($done) $totais['ganho'] += $valor; else $totais['perda'] += $valor;
    }

    $linhas[] = [
      'data' => $d,
      'titulo' => $t['titulo'],
      'peso' => (int)$t['peso'],
      'valor' => $valor,
      'done'  => $done,
    ];
  }

  if ($showValues) $totais['total_previsto'] = $totais['ganho'] + $totais['perda'];
  if ($linhas) $dias[] = ['data'=>$d, 'linhas'=>$linhas];
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

  <?php if ($showValues): ?>
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-4"><div class="p-3 border rounded h-100"><div class="text-muted">Previsto</div><div class="fs-5"><?php echo money_br($totais['total_previsto']); ?></div></div></div>
    <div class="col-12 col-md-4"><div class="p-3 border rounded h-100"><div class="text-muted">Ganho</div><div class="fs-5 text-success"><?php echo money_br($totais['ganho']); ?></div></div></div>
    <div class="col-12 col-md-4"><div class="p-3 border rounded h-100"><div class="text-muted">Descontos</div><div class="fs-5 text-danger"><?php echo money_br($totais['perda']); ?></div></div></div>
  </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Data</th>
          <th>Tarefa</th>
          <th>Peso</th>
          <?php if ($showValues): ?><th>Valor</th><?php endif; ?>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($dias as $dia): ?>
        <?php foreach ($dia['linhas'] as $ln): ?>
          <tr>
            <td><?php echo htmlspecialchars($dia['data']); ?></td>
            <td><?php echo htmlspecialchars($ln['titulo']); ?></td>
            <td><?php echo (int)$ln['peso']; ?></td>
            <?php if ($showValues): ?><td><?php echo money_br($ln['valor']); ?></td><?php endif; ?>
            <td><?php echo $ln['done'] ? '<span class="badge bg-success">Concluída</span>' : '<span class="badge bg-secondary">Pendente</span>'; ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div><?php html_foot(); ?>