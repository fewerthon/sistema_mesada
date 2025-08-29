<?php
require_once 'layout.php';

$user = get_user_by_id((int)$_SESSION['user_id']);
$view = $_GET['view'] ?? 'semanal';
$ref  = $_GET['date'] ?? today_date();

// paginação
$allowed_ps = [10,20,50,100];
$ps = (int)($_GET['ps'] ?? 20);
if (!in_array($ps, $allowed_ps, true)) $ps = 20;
$p  = max(1, (int)($_GET['p'] ?? 1));

function week_bounds(string $date): array {
  $ts = strtotime($date);
  $dow = (int)date('w', $ts);
  $start = date('Y-m-d', strtotime("-{$dow} day", $ts));
  $end   = date('Y-m-d', strtotime('+' . (6 - $dow) . ' day', $ts));
  return [$start, $end];
}
function month_bounds(string $date): array {
  $ts = strtotime($date);
  return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
}

if ($view === 'mensal') {
  [$start, $end] = month_bounds($ref);
} else {
  [$start, $end] = week_bounds($ref);
}

$pdo = db();

// flags/configs
$cfgVal = $pdo->query("SELECT value FROM config WHERE key='exibir_valores_filhos'")->fetchColumn();
$showPerTaskValues = ($cfgVal === false) ? true : ((int)$cfgVal === 1);
$bonus_percent = (int)($pdo->query("SELECT value FROM config WHERE key='bonus_percent'")->fetchColumn());

// ---------- Construção das linhas + totais (respeita DESDE; concluída usa valor congelado) ----------
$totais = ['ganho'=>0.0, 'perda'=>0.0, 'total_previsto'=>0.0];
$rows = [];

for ($ts = strtotime($start); $ts <= strtotime($end); $ts += 86400) {
  $d = date('Y-m-d', $ts);
  $dow = (int)date('w', $ts);

  // tarefas válidas no dia
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

  // status/valores congelados do dia
  $stS = $pdo->prepare("
    SELECT tarefa_id, concluida, COALESCE(valor_tarefa,0) AS valor_tarefa
      FROM tarefas_status
     WHERE user_id = ? AND date(data) = date(?)
  ");
  $stS->execute([(int)$user['id'], $d]);
  $statusMap = [];
  foreach ($stS->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusMap[(int)$row['tarefa_id']] = $row;
  }

  // valores atuais (para pendentes)
  $map_val = map_valores_por_tarefa($user, $d, $tarefas);

  foreach ($tarefas as $t) {
    $tid   = (int)$t['tarefa_id'];
    $done  = (int)($statusMap[$tid]['concluida'] ?? 0) === 1;
    $v_frozen = (float)($statusMap[$tid]['valor_tarefa'] ?? 0.0);
    $v_calc   = (float)($map_val[$tid] ?? 0.0);
    $valor    = $done && $v_frozen > 0 ? $v_frozen : $v_calc;

    if ($done) $totais['ganho'] += $valor; else $totais['perda'] += $valor;

    $rows[] = [
      'data'       => $d,
      'tarefa_id'  => $tid,
      'titulo'     => $t['titulo'],
      'peso'       => (int)$t['peso'],
      'valor'      => $valor,
      'done'       => $done,
    ];
  }
}
$totais['total_previsto'] = $totais['ganho'] + $totais['perda'];

// ---------- BÔNUS DO MÊS (mês de competência de $ref, até ONTEM; 0 de 0 conta como OK) ----------
[$m_ini, $m_fim] = month_bounds($ref);
$ontem = date('Y-m-d', strtotime('-1 day'));
$bonus_until = min($m_fim, $ontem);

$bonus_occ_total = 0; $bonus_occ_done = 0;
if ($bonus_until >= $m_ini) {
  for ($ts = strtotime($m_ini); $ts <= strtotime($bonus_until); $ts += 86400) {
    $d = date('Y-m-d', $ts);
    $dow = (int)date('w', $ts);

    $st = $pdo->prepare("
      SELECT tu.tarefa_id
        FROM tarefas_usuario tu
        JOIN tarefas t ON t.id = tu.tarefa_id
       WHERE tu.user_id = ?
         AND tu.dia_semana = ?
         AND t.ativo = 1
         AND date(tu.desde) <= date(?)
    ");
    $st->execute([(int)$user['id'], $dow, $d]);
    $tarefasDia = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$tarefasDia) continue;

    $stS = $pdo->prepare("SELECT tarefa_id FROM tarefas_status WHERE user_id=? AND date(data)=date(?) AND concluida=1");
    $stS->execute([(int)$user['id'], $d]);
    $doneSet = array_fill_keys(array_column($stS->fetchAll(PDO::FETCH_ASSOC), 'tarefa_id'), true);

    foreach ($tarefasDia as $td) {
      $bonus_occ_total++;
      if (!empty($doneSet[(int)$td['tarefa_id']])) $bonus_occ_done++;
    }
  }
}
$bonus_ok = ($bonus_occ_done === $bonus_occ_total);
$bonus_value = ($bonus_ok && $bonus_percent > 0)
  ? round(((float)$user['mesada']) * ($bonus_percent / 100), 2)
  : 0.0;

// ---------- Paginação do analítico ----------
$total_rows  = count($rows);
$total_pages = max(1, (int)ceil($total_rows / $ps));
if ($p > $total_pages) $p = $total_pages;
$offset = ($p - 1) * $ps;
$page_rows = array_slice($rows, $offset, $ps);

// helper para URLs mantendo filtros
function tarefas_build_url(array $params): string {
  return '?' . http_build_query($params);
}
$qbase = [
  'view' => $view,
  'date' => $ref,
  'ps'   => $ps,
];
?>
<div class="bg-white rounded shadow-sm p-3">
  <!-- Toolbar única, sem quebra e com autosubmit -->
  <div class="d-flex align-items-center justify-content-between flex-nowrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-nowrap">
      <a href="<?=$baseUrl;?>/filho/index.php" class="btn btn-sm btn-outline-secondary">Hoje</a>
      <a href="<?php echo tarefas_build_url(['view'=>'semanal','date'=>$ref,'ps'=>$ps,'p'=>1]); ?>" class="btn btn-sm <?php echo $view==='semanal'?'btn-primary':'btn-outline-primary'; ?>">Semanal</a>
      <a href="<?php echo tarefas_build_url(['view'=>'mensal','date'=>$ref,'ps'=>$ps,'p'=>1]); ?>" class="btn btn-sm <?php echo $view==='mensal'?'btn-primary':'btn-outline-primary'; ?>">Mensal</a>
    </div>
    <form id="filtros" class="d-flex align-items-center gap-2 flex-nowrap" method="get">
      <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
      <input type="hidden" name="p" value="<?php echo (int)$p; ?>">
      <input type="date" class="form-control form-control-sm" name="date" value="<?php echo htmlspecialchars($ref); ?>" aria-label="Data de referência">
      <select name="ps" class="form-select form-select-sm" aria-label="Itens por página">
        <?php foreach ($allowed_ps as $opt): ?>
          <option value="<?php echo $opt; ?>" <?php if($ps==$opt) echo 'selected'; ?>><?php echo $opt; ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <!-- Cards SEMPRE visíveis -->
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <div class="p-3 border rounded h-100">
        <div class="text-muted">Previsto</div>
        <div class="fs-5"><?php echo money_br($totais['total_previsto']); ?></div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="p-3 border rounded h-100">
        <div class="text-muted">Bônus do mês (<?php echo (int)$bonus_percent; ?>%)</div>
        <div class="fs-5 <?php echo $bonus_value>0 ? 'text-success' : 'text-primary'; ?>">
          <?php echo money_br($bonus_value); ?>
        </div>
        <div class="small text-muted">Até ontem: <?php echo $bonus_occ_done; ?> de <?php echo $bonus_occ_total; ?> concl.</div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="p-3 border rounded h-100">
        <div class="text-muted">Ganho</div>
        <div class="fs-5 text-success"><?php echo money_br($totais['ganho']); ?></div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="p-3 border rounded h-100">
        <div class="text-muted">Descontos</div>
        <div class="fs-5 text-danger"><?php echo money_br($totais['perda']); ?></div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="small text-muted">
      <?php
        if ($total_rows > 0) {
          $from = $offset + 1;
          $to   = min($offset + $ps, $total_rows);
          echo "Mostrando <strong>{$from}-{$to}</strong> de <strong>{$total_rows}</strong>";
        } else {
          echo "Sem registros no período.";
        }
      ?>
    </div>
    <?php
      $prev_disabled = $p <= 1 ? 'disabled' : '';
      $next_disabled = $p >= $total_pages ? 'disabled' : '';
    ?>
    <div class="btn-group">
      <a class="btn btn-sm btn-outline-secondary <?php echo $prev_disabled; ?>"
         href="<?php echo $p>1 ? tarefas_build_url($qbase + ['p'=>$p-1]) : '#'; ?>">
         « Anterior
      </a>
      <span class="btn btn-sm btn-light disabled">Página <?php echo $p; ?> / <?php echo $total_pages; ?></span>
      <a class="btn btn-sm btn-outline-secondary <?php echo $next_disabled; ?>"
         href="<?php echo $p<$total_pages ? tarefas_build_url($qbase + ['p'=>$p+1]) : '#'; ?>">
         Próxima »
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Data</th>
          <th>Tarefa</th>
          <th>Peso</th>
          <?php if ($showPerTaskValues): ?><th>Valor</th><?php endif; ?>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($page_rows as $ln): ?>
        <tr>
          <td><?php echo htmlspecialchars($ln['data']); ?></td>
          <td><?php echo htmlspecialchars($ln['titulo']); ?></td>
          <td><?php echo (int)$ln['peso']; ?></td>
          <?php if ($showPerTaskValues): ?><td><?php echo money_br($ln['valor']); ?></td><?php endif; ?>
          <td><?php echo $ln['done'] ? '<span class="badge bg-success">Concluída</span>' : '<span class="badge bg-secondary">Pendente</span>'; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <div class="small text-muted">
      <?php
        if ($total_rows > 0) {
          $from = $offset + 1;
          $to   = min($offset + $ps, $total_rows);
          echo "Mostrando <strong>{$from}-{$to}</strong> de <strong>{$total_rows}</strong>";
        }
      ?>
    </div>
    <div class="btn-group">
      <a class="btn btn-sm btn-outline-secondary <?php echo $prev_disabled; ?>"
         href="<?php echo $p>1 ? tarefas_build_url($qbase + ['p'=>$p-1]) : '#'; ?>">
         « Anterior
      </a>
      <span class="btn btn-sm btn-light disabled">Página <?php echo $p; ?> / <?php echo $total_pages; ?></span>
      <a class="btn btn-sm btn-outline-secondary <?php echo $next_disabled; ?>"
         href="<?php echo $p<$total_pages ? tarefas_build_url($qbase + ['p'=>$p+1]) : '#'; ?>">
         Próxima »
      </a>
    </div>
  </div>
</div>

<script>
  // autosubmit dos filtros, sem botão
  (function(){
    const form = document.getElementById('filtros');
    if (!form) return;
    form.addEventListener('change', function(ev){
      const name = ev.target && ev.target.name;
      if (name === 'date' || name === 'ps') {
        // ao trocar filtro, volta para a página 1
        const pInput = form.querySelector('input[name="p"]');
        if (pInput) pInput.value = 1;
        form.submit();
      }
    });
  })();
</script>

</div><?php html_foot(); ?>