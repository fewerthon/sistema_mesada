<?php
require_once __DIR__ . '/config.php';
require_once ROOT_PATH . '/util.php';
require_login();
if (is_supervisor()) { header('Location: admin/index.php'); exit; }
if (is_filho()) { header('Location: filho/index.php'); exit; }
html_head('Mesada & Tarefas');
echo '<div class="container py-5"><p>Perfil desconhecido.</p></div>';
html_foot();
?>
