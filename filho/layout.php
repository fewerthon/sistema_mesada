<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/util.php';
require_role('filho');
//pegando dados do usuário
$me = get_user_by_id((int)($_SESSION['user_id'] ?? 0));
$user = get_user_by_id((int)$_SESSION['user_id']);
?>
<?php html_head('Minhas Tarefas'); ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?=$baseUrl;?>/filho/index.php">Minhas Tarefas</a>
    <div class="ms-auto d-flex align-items-center">
      <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#modalPerfil">
        <span class='navbar-text me-3'><?php echo htmlspecialchars($me['name']); ?></span>
      </a>
      <a class="btn btn-outline-light" href="<?=$baseUrl;?>/logout.php">Sair</a>
    </div>
  </div>
</nav>
<div class="container my-4">
<div class="modal fade" id="modalPerfil" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?=$baseUrl;?>/update_profile.php" onsubmit="return submitPerfil(this,event)">
      <?php echo csrf_input(); ?>
      <div class="modal-header">
        <h5 class="modal-title">Meu perfil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($me['name'] ?? ''); ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($me['email'] ?? ''); ?>" placeholder="seu@email.com">
          <div class="form-text">O e-mail deve ser único no sistema.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Nova senha</label>
          <input type="password" name="password_new" class="form-control" minlength="6" placeholder="Deixe em branco para manter">
        </div>
        <div class="mb-3">
          <label class="form-label">Confirmar nova senha</label>
          <input type="password" name="password_confirm" class="form-control" minlength="6" placeholder="Repita a nova senha">
        </div>
        <div class="small text-muted">Preencha a senha apenas se quiser alterá-la.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
function submitPerfil(form, ev){
  ev.preventDefault();
  fetch(form.action, { method:'POST', body:new FormData(form) })
    .then(r => r.json())
    .then(res => {
      if (res && res.ok) { location.reload(); }
      else { alert(res && res.error ? res.error : 'Falha ao salvar'); }
    })
    .catch(() => alert('Falha de rede'));
  return false;
}
</script>
