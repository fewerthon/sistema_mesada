<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../util.php';
require_role('filho');
$user = get_user_by_id((int)$_SESSION['user_id']);
?>
<?php html_head('Minhas Tarefas'); ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="/filho/index.php">Minhas Tarefas</a>
    <div class="ms-auto d-flex align-items-center">
      <span class="navbar-text me-3"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
      <a class="btn btn-outline-light" href="/logout.php">Sair</a>
    </div>
  </div>
</nav>
<div class="container my-4">
