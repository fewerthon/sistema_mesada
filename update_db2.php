<?php
require_once 'db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// cria tabela e valores padrão
$pdo->exec("CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT NOT NULL)");
$pdo->exec("INSERT OR IGNORE INTO config(key,value) VALUES ('exibir_valores_filhos','1')");
$pdo->exec("INSERT OR IGNORE INTO config(key,value) VALUES ('bonus_percent','0')");

echo "OK v2\n";