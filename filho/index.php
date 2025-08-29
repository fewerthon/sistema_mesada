<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../util.php';
$user = get_user_by_id((int)$_SESSION['user_id']);
$hoje = today_date();
$dow = ymd_to_weekday($hoje);
$tarefas = tarefas_do_dia_para_usuario((int)$user['id'], $dow);
$map_val = map_valores_por_tarefa($user, $hoje, $tarefas);
?>
<div class="bg-white rounded shadow-sm p-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 mb-0">Tarefas de hoje (<?php echo htmlspecialchars($hoje); ?>)</h2>
    <a class="btn btn-sm btn-outline-secondary" href="/filho/tarefas.php?view=semanal">Ver semana</a>
  </div>
  <?php if (!$tarefas): ?>
    <div class="alert alert-info">Você não tem tarefas atribuídas hoje.</div>
  <?php else: ?>
    <ul class="list-group">
      <?php foreach ($tarefas as $t): 
        $done = status_tarefa_no_dia((int)$user['id'], (int)$t['tarefa_id'], $hoje);
      ?>
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <div>
          <input type="checkbox" class="form-check-input me-2" data-tarefa="<?php echo $t['tarefa_id']; ?>" <?php if($done) echo 'checked'; ?>>
          <strong><?php echo htmlspecialchars($t['titulo']); ?></strong>
          <small class="text-muted ms-2">peso <?php echo (int)$t['peso']; ?></small>
        </div>
        <span class="badge bg-light text-dark border">Valor: <?php echo money_br($map_val[$t['tarefa_id']] ?? 0); ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="text-muted small mt-2">Você só pode marcar/desmarcar tarefas no dia de hoje.</p>
  <?php endif; ?>
</div>
<script>
  document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', async (e) => {
      const tarefa_id = e.target.getAttribute('data-tarefa');
      const set = e.target.checked ? 1 : 0;
      const formData = new FormData();
      formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
      formData.append('tarefa_id', tarefa_id);
      formData.append('date', '<?php echo $hoje; ?>');
      formData.append('set', set);
      formData.append('target_user_id', '<?php echo (int)$user['id']; ?>');
      const r = await fetch('/toggle_status.php', { method: 'POST', body: formData });
      if (!r.ok) {
        alert('Falha ao atualizar.');
        e.target.checked = !e.target.checked;
      }
    });
  });
</script>
</div><?php html_foot(); ?>
