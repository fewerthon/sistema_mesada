<?php
require_once __DIR__ . '/config.php';
require_once ROOT_PATH . '/auth.php';
logout_user();
header('Location: login.php');
?>
