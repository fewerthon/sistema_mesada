<?php
require_once 'layout.php';
$pdo = db();

$filhos = $pdo->query("SELECT id,name FROM users WHERE role='filho' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ((count($filhos)>0) ? (int)$filhos[0]['id'] : 0);
$date = $_GET['date'] ?? today_date();
$user = $user_id ? get_user_by_id($user_id) : null;

// paginação: tamanho e página
$allowed_ps = [10,20,50,100];
$ps = (int)($_GET['ps'] ?? 20);
if (!in_array($ps, $allowed_ps, true)) $ps = 20;
$p  = max(1, (int)($_GET['p'] ?? 1));

function period_month_bounds(string $date): array {
  $ts = strtotime($date);
  $start = date('Y-m-01', $ts);
  $end   = date('Y-m-t', $ts);
  return [$start, $end];
}
list($ini, $fim) = period_month_bounds($date);

// lê configurações (bônus %)
$bonus_percent = (int)($pdo->query("SELECT value FROM config WHERE key='bonus_percent'")->fetchColumn());

// Totais e construção das linhas do analítico (para paginar depois)
$totais = ['ganho'=>0.0,'perda'=>0.0,'total_previsto'=>0.0];
$occ_total = 0; $occ_done = 0;
$rows = [];

if ($user) {
  for ($ts = strtotime($ini); $ts <= strtotime($fim); $ts += 86400) {
    $d = date('Y-m-d', $ts);
    $dow = (int)date('w', $ts);

    // tarefas válidas no dia (respeita DESDE)
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
    $st->execute([$user_id, $dow, $d]);
    $tarefas = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$tarefas) continue;

    // status/valores congelados do dia
    $stS = $pdo->prepare("
      SELECT tarefa_id, concluida, COALESCE(valor_tarefa,0) AS valor_tarefa
        FROM tarefas_status
       WHERE user_id = ? AND date(data) = date(?)
    ");
    $stS->execute([$user_id, $d]);
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

      // contar ocorrências do mês (para bônus)
      $occ_total++;
      if ($done) $occ_done++;

      // guarda linha para o analítico (paginação)
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
}

// bônus (somente com 100% concluído)
$bonus_value = 0.0;
$bonus_ok = ($occ_total > 0 && $occ_done === $occ_total);
if ($bonus_ok && $bonus_percent > 0) {
  $bonus_value = round($totais['ganho'] * ($bonus_percent / 100), 2);
}

// paginação dos rows
$total_rows = count($rows);
$total_pages = max(1, (int)ceil($total_rows / $ps));
if ($p > $total_pages) $p = $total_pages;
$offset = ($p - 1) * $ps;
$page_rows = array_slice($rows, $offset, $ps);

// helper pra montar URLs de paginação mantendo filtros
function rel_build_url(array $params): string {
  return '?' . http_build_query($params);
}
$qbase = [
  'user_id' => $user_id,
  'date'    => $date,
  'ps'      => $ps,
];
?>
<div class="bg-white rounded shadow-sm p-3">
  <form method="get" class="row g-2 mb-3">
    <div class="col-auto">
      <label class="form-label">Filho</label>
      <select name="user_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($filhos as $f): ?>
          <option value="<?php echo $f['id']; ?>" <?php if($user_id==$f['id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($f['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label">Mês de referência</label>
      <input type="month" class="form-control" name="date" value="<?php echo substr($date,0,7); ?>" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
      <label class="form-label">Itens por página</label>
      <select name="ps" class="form-select" onchange="this.form.submit()">
        <?php foreach ([10,20,50,100] as $opt): ?>
          <option value="<?php echo $opt; ?>" <?php if($ps==$opt) echo 'selected'; ?>><?php echo $opt; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if ($user): ?>
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3"><div class="p-3 border rounded h-100"><div class="text-muted">Total previsto</div><div class="fs-4"><?php echo money_br($totais['total_previsto']); ?></div></div></div>
    <div class="col-12 col-md-3"><div class="p-3 border rounded h-100"><div class="text-muted">Ganho até agora</div><div class="fs-4 text-success"><?php echo money_br($totais['ganho']); ?></div></div></div>
    <div class="col-12 col-md-3"><div class="p-3 border rounded h-100"><div class="text-muted">Descontos</div><div class="fs-4 text-danger"><?php echo money_br($totais['perda']); ?></div></div></div>
    <div class="col-12 col-md-3">
      <div class="p-3 border rounded h-100">
        <div class="text-muted">Bônus do mês (<?php echo (int)$bonus_percent; ?>%)</div>
        <div class="fs-4 <?php echo $bonus_value>0 ? 'text-success' : 'text-primary'; ?>">
          <?php echo money_br($bonus_value); ?>
        </div>
        <div class="small text-muted">Concluídas <?php echo $occ_done; ?> de <?php echo $occ_total; ?> tarefas no mês.</div>
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
    <div class="btn-group">
      <?php
        $prev_disabled = $p <= 1 ? 'disabled' : '';
        $next_disabled = $p >= $total_pages ? 'disabled' : '';
      ?>
      <a class="btn btn-sm btn-outline-secondary <?php echo $prev_disabled; ?>"
         href="<?php echo $p>1 ? rel_build_url($qbase + ['p'=>$p-1]) : '#'; ?>">
         « Anterior
      </a>
      <span class="btn btn-sm btn-light disabled">Página <?php echo $p; ?> / <?php echo $total_pages; ?></span>
      <a class="btn btn-sm btn-outline-secondary <?php echo $next_disabled; ?>"
         href="<?php echo $p<$total_pages ? rel_build_url($qbase + ['p'=>$p+1]) : '#'; ?>">
         Próxima »
      </a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Data</th><th>Tarefa</th><th>Peso</th><th>Valor</th><th>Status</th><th>Ação (Supervisor)</th></tr></thead>
      <tbody>

      <?php $i=0 ; foreach ($page_rows as $ln): ?>
          <?php $diaSem = $dias_semana[date('l', strtotime($ln['data']))];
          $numSemana = date('W', strtotime($ln['data']));
           // Determina se a semana é par ou ímpar
          $parImpar = ($numSemana % 2 == 0) ? 'par' : 'impar';
          // Determina a tonalidade com base na paridade da linha (índice $i)
          $tonalidade = $i % 2;
          // Obtém a cor final
          $cor_fundo = $cores[$parImpar][$tonalidade];
          ?>
        <style>table td {background-color: transparent !important;}</style>
        <tr style="background-color: <?php echo $cor_fundo; ?>;">
          <td><?php echo htmlspecialchars($ln['data']); echo " $diaSem"; ?></td>
          <td><?php echo htmlspecialchars($ln['titulo']); ?></td>
          <td><?php echo (int)$ln['peso']; ?></td>
          <td><?php echo money_br($ln['valor']); ?></td>
          <td>
            <?php echo $ln['done']
              ? '<span class="badge bg-success">Concluída</span>'
              : '<span class="badge bg-secondary">Pendente</span>'; ?>
          </td>
          <td>
            <?php
              $done = $ln['done'];
              echo '<form method="post" action="'.$baseUrl.'/toggle_status.php" class="d-inline" onsubmit="return relSubmit(this,event)">'.csrf_input().
                   '<input type="hidden" name="target_user_id" value="'.$user_id.'">'.
                   '<input type="hidden" name="tarefa_id" value="'.$ln['tarefa_id'].'">'.
                   '<input type="hidden" name="date" value="'.$ln['data'].'">'.
                   '<input type="hidden" name="set" value="'.($done?0:1).'">'.
                   '<button class="btn btn-sm '.($done?'btn-outline-danger':'btn-outline-success').'">'.($done?'Desmarcar':'Marcar').'</button></form>';
            ?>
          </td>
        </tr>
      <?php $i++; endforeach; ?>
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
         href="<?php echo $p>1 ? rel_build_url($qbase + ['p'=>$p-1]) : '#'; ?>">
         « Anterior
      </a>
      <span class="btn btn-sm btn-light disabled">Página <?php echo $p; ?> / <?php echo $total_pages; ?></span>
      <a class="btn btn-sm btn-outline-secondary <?php echo $next_disabled; ?>"
         href="<?php echo $p<$total_pages ? rel_build_url($qbase + ['p'=>$p+1]) : '#'; ?>">
         Próxima »
      </a>
    </div>
  </div>

  <script>
  function relSubmit(form, ev){
    ev.preventDefault();
    fetch(form.action, { method:'POST', body:new FormData(form) })
      .then(r => r.ok ? r.json() : Promise.reject())
      .then(() => location.reload())
      .catch(() => location.reload());
    return false;
  }
  </script>

  <?php else: ?>
    <div class="alert alert-warning">Selecione um filho.</div>
  <?php endif; ?>
</div>
</div><?php html_foot(); ?>
