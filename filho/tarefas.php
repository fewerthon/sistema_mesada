<?php
require_once 'layout.php';
$user = get_user_by_id((int)$_SESSION['user_id']);
$view = $_GET['view'] ?? 'semanal';
$ref = $_GET['date'] ?? today_date();
function week_bounds(string $date): array {
  $ts = strtotime($date);
  $dow = (int)date('w', $ts);
  $start = date('Y-m-d', strtotime("-{$dow} day", $ts));
  $end = date('Y-m-d', strtotime('+' . (6 - $dow) . ' day', $ts));
  return [$start, $end];
}
if ($view === 'mensal') {
  $start = date('Y-m-01', strtotime($ref));
  $end = date('Y-m-t', strtotime($ref));
} else {
  [$start, $end] = week_bounds($ref);
}
$totais = ganhos_no_periodo((int)$user['id'], $start, $end);
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
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-4"><div class="p-3 border rounded h-100"><div class="text-muted">Previsto</div><div class="fs-5"><?php echo money_br($totais['total_previsto']); ?></div></div></div>
    <div class="col-12 col-md-4"><div class="p-3 border rounded h-100"><div class="text-muted">Ganho</div><div class="fs-5 text-success"><?php echo money_br($totais['ganho']); ?></div></div></div>
    <div class="col-12 col-md-4"><div class="p-3 border rounded h-100"><div class="text-muted">Descontos</div><div class="fs-5 text-danger"><?php echo money_br($totais['perda']); ?></div></div></div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Data</th><th>Tarefa</th><th>Peso</th><th>Valor</th><th>Status</th></tr></thead>
      <tbody>
      <?php
      for ($ts = strtotime($start); $ts <= strtotime($end); $ts += 86400) {
        $d = date('Y-m-d', $ts);
        $dow = (int)date('w', $ts);
        $tarefas = tarefas_do_dia_para_usuario((int)$user['id'], $dow);
        if (!$tarefas) continue;
        $map_val = map_valores_por_tarefa($user, $d, $tarefas);
        foreach ($tarefas as $t) {
          $done = status_tarefa_no_dia((int)$user['id'], (int)$t['tarefa_id'], $d);
          echo '<tr>';
          echo '<td>'.htmlspecialchars($d).'</td>';
          echo '<td>'.htmlspecialchars($t['titulo']).'</td>';
          echo '<td>'.(int)$t['peso'].'</td>';
          echo '<td>'.money_br($map_val[$t['tarefa_id']] ?? 0).'</td>';
          echo '<td>'.($done?'<span class="badge bg-success">Concluída</span>':'<span class="badge bg-secondary">Pendente</span>').'</td>';
          echo '</tr>';
        }
      }
      ?>
      </tbody>
    </table>
  </div>
</div>
</div><?php html_foot(); ?>
