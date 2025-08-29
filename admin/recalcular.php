<?php
require_once 'layout.php';
csrf_check();
$pdo = db();

// Lista de filhos
$filhos = $pdo->query("SELECT id, name, email, mesada FROM users WHERE role='filho' ORDER BY name")
              ->fetchAll(PDO::FETCH_ASSOC);

// Helpers
function period_month_bounds(string $ym): array {
  $ts = strtotime($ym . '-01');
  return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
}

$alert = null;

// Submissão: recalcular concluídas do mês com a MESADA ATUAL do filho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user_id = (int)($_POST['user_id'] ?? 0);
  $ym      = trim($_POST['ym'] ?? '');

  if ($user_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $alert = ['type'=>'danger', 'msg'=>'Parâmetros inválidos.'];
  } else {
    [$ini, $fim] = period_month_bounds($ym);
    $user = get_user_by_id($user_id);
    if (!$user) {
      $alert = ['type'=>'danger', 'msg'=>'Filho não encontrado.'];
    } else {
      try {
        $pdo->beginTransaction();

        // Concluídas no mês
        $st = $pdo->prepare("
          SELECT tarefa_id, date(data) AS d
            FROM tarefas_status
           WHERE user_id = ?
             AND concluida = 1
             AND date(data) BETWEEN date(?) AND date(?)
        ");
        $st->execute([$user_id, $ini, $fim]);
        $concluidas = $st->fetchAll(PDO::FETCH_ASSOC);

        $atualizadas = 0;

        if ($concluidas) {
          // Cache de peso/título das tarefas
          $tarefasInfo = [];
          $ids = array_unique(array_map(fn($r)=>(int)$r['tarefa_id'], $concluidas));
          if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stT = $pdo->prepare("SELECT id, titulo, peso FROM tarefas WHERE id IN ($in)");
            $stT->execute($ids);
            foreach ($stT->fetchAll(PDO::FETCH_ASSOC) as $t) {
              $tarefasInfo[(int)$t['id']] = ['peso'=>(int)$t['peso'], 'titulo'=>$t['titulo']];
            }
          }

          // Sempre usa a MESADA ATUAL do filho (do cadastro)
          $mesadaAtual = (float)$user['mesada'];
          $u = $user; 
          $u['mesada'] = $mesadaAtual;

          // Agrupa concluídas por data para calcular uma única vez por dia
          $byDate = [];
          foreach ($concluidas as $row) {
            $byDate[$row['d']][] = (int)$row['tarefa_id'];
          }

          $stUpd = $pdo->prepare("
            UPDATE tarefas_status
               SET mesada_ref = ?, valor_tarefa = ?
             WHERE user_id = ? AND tarefa_id = ? AND date(data) = date(?)
               AND concluida = 1
          ");

          foreach ($byDate as $d => $tidsDoDia) {
            $dow = (int)date('w', strtotime($d));

            // Tarefas válidas no dia (respeita DESDE/ativo)
            $stDia = $pdo->prepare("
              SELECT tu.tarefa_id, t.titulo, t.peso
                FROM tarefas_usuario tu
                JOIN tarefas t ON t.id = tu.tarefa_id
               WHERE tu.user_id = ?
                 AND tu.dia_semana = ?
                 AND t.ativo = 1
                 AND date(tu.desde) <= date(?)
               ORDER BY t.titulo
            ");
            $stDia->execute([$user_id, $dow, $d]);
            $tarefasDia = $stDia->fetchAll(PDO::FETCH_ASSOC);

            // Garante que tarefas concluídas no dia entrem no cálculo mesmo se hoje não estiverem mais vinculadas
            $present = [];
            foreach ($tarefasDia as $td) $present[(int)$td['tarefa_id']] = true;
            foreach ($tidsDoDia as $tid) {
              if (empty($present[$tid])) {
                $tarefasDia[] = [
                  'tarefa_id' => $tid,
                  'titulo'    => $tarefasInfo[$tid]['titulo'] ?? '',
                  'peso'      => (int)($tarefasInfo[$tid]['peso'] ?? 1),
                ];
              }
            }

            // Calcula os valores de TODO o dia e pega a parte de cada tarefa concluída
            $map = map_valores_por_tarefa($u, $d, $tarefasDia);

            foreach ($tidsDoDia as $tid) {
              $valor = (float)($map[$tid] ?? 0.0);
              $stUpd->execute([$mesadaAtual, $valor, $user_id, $tid, $d]);
              $atualizadas++;
            }
          }
        }

        $pdo->commit();
        $alert = ['type'=>'success', 'msg'=>"Recalculado com a mesada atual para <strong>$atualizadas</strong> ocorrência(s) concluída(s) entre <strong>$ini</strong> e <strong>$fim</strong>."];
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $alert = ['type'=>'danger', 'msg'=>'Erro ao recalcular: '.$e->getMessage()];
      }
    }
  }
}

// Estado inicial dos selects
$sel_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ((count($filhos)>0) ? (int)$filhos[0]['id'] : 0);
$sel_ym   = $_GET['ym'] ?? date('Y-m');
?>
<div class="bg-white rounded shadow-sm p-3">
  <h2 class="h5 mb-3">Recalcular tarefas concluídas (mês)</h2>

  <?php if ($alert): ?>
    <div class="alert alert-<?php echo $alert['type']; ?>"><?php echo $alert['msg']; ?></div>
  <?php endif; ?>

  <form method="post" class="row g-3 mb-4">
    <?php echo csrf_input(); ?>

    <div class="col-12 col-md-5">
      <label class="form-label">Filho</label>
      <select name="user_id" class="form-select" required>
        <?php foreach ($filhos as $f): ?>
          <option value="<?php echo $f['id']; ?>" <?php if($sel_user==$f['id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($f['name']); ?> — mesada atual: <?php echo money_br((float)$f['mesada']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Mês</label>
      <input type="month" name="ym" class="form-control" value="<?php echo htmlspecialchars($sel_ym); ?>" required>
    </div>

    <div class="col-12 col-md-4 d-flex align-items-end">
      <button class="btn btn-primary w-100">Recalcular concluídas do mês</button>
    </div>
  </form>

  <div class="alert alert-info">
    Esta ação <strong>não altera a mesada do cadastro</strong>. Ela apenas:
    <ul class="mb-0">
      <li>Atualiza <code>mesada_ref</code> nas ocorrências <em>concluídas</em> do mês para a <strong>mesada atual</strong> do filho;</li>
      <li>Recalcula <code>valor_tarefa</code> usando todas as tarefas válidas de cada dia (proporção correta).</li>
    </ul>
  </div>
</div>
</div><?php html_foot(); ?>
