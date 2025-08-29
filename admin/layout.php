<?php
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . '/util.php';
require_role('supervisor');
?>
<?php html_head('Admin - Mesada & Tarefas'); ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?=$baseUrl;?>/admin/index.php">Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExample" aria-controls="navbarsExample" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarsExample">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?=$baseUrl;?>/admin/usuarios.php">Filhos</a></li>
        <li class="nav-item"><a class="nav-link" href="<?=$baseUrl;?>/admin/tarefas.php">Tarefas</a></li>
        <li class="nav-item"><a class="nav-link" href="<?=$baseUrl;?>/admin/vinculos.php">Vínculos</a></li>
        <li class="nav-item"><a class="nav-link" href="<?=$baseUrl;?>/admin/relatorio.php">Relatórios</a></li>
      </ul>
      <span class='navbar-text me-3'><?php echo htmlspecialchars($_SESSION['name']); ?></span>
      <a class="btn btn-outline-light" href="<?=$baseUrl;?>/logout.php">Sair</a>
    </div>
  </div>
</nav>
<div class="container my-4">
