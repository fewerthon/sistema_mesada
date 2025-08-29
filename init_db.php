<?php
require_once __DIR__ . '/db.php';
$pdo = db();
$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('supervisor','filho')),
  mesada REAL DEFAULT 100,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS tarefas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  titulo TEXT NOT NULL,
  peso INTEGER NOT NULL DEFAULT 1,
  ativo INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS tarefas_usuario (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  tarefa_id INTEGER NOT NULL,
  dia_semana INTEGER NOT NULL CHECK(dia_semana BETWEEN 0 AND 6),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
  mesada_cadastrada REAL,
);
CREATE TABLE IF NOT EXISTS tarefas_status (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  tarefa_id INTEGER NOT NULL,
  data TEXT NOT NULL,
  concluida INTEGER NOT NULL DEFAULT 0,
  UNIQUE(user_id, tarefa_id, data),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE
);
SQL);
// Supervisor padrão
$exists = $pdo->query("SELECT COUNT(*) FROM users WHERE role='supervisor'")->fetchColumn();
if ((int)$exists === 0) {
    $pwd = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users(name,email,password_hash,role,mesada) VALUES (?,?,?,?,?)')
        ->execute(['Supervisor', 'admin@local', $pwd, 'supervisor', 0]);
}
// Filho exemplo
$childExists = $pdo->query("SELECT COUNT(*) FROM users WHERE role='filho'")->fetchColumn();
if ((int)$childExists === 0) {
    $pwd = password_hash('filho123', PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users(name,email,password_hash,role,mesada) VALUES (?,?,?,?,?)')
        ->execute(['Fulano', 'fulano@local', $pwd, 'filho', 100]);
}
echo "Banco inicializado. Supervisor: admin@local / admin123 | Filho: fulano@local / filho123";
?>
