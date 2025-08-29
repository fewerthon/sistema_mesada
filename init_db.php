<?php
require_once 'db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// helper para checar coluna
function columnExists(PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("PRAGMA table_info($table)");
    $st->execute();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (strcasecmp($col['name'], $column) === 0) return true;
    }
    return false;
}

$pdo->beginTransaction();

// ---- Tabelas base (com schema já atualizado) ----
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
  desde TEXT DEFAULT CURRENT_DATE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS tarefas_status (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  tarefa_id INTEGER NOT NULL,
  data TEXT NOT NULL,
  concluida INTEGER NOT NULL DEFAULT 0,
  valor_tarefa REAL DEFAULT 0,
  mesada_ref  REAL DEFAULT 0,
  UNIQUE(user_id, tarefa_id, data),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE
);
-- config (flags e parâmetros do sistema)
CREATE TABLE IF NOT EXISTS config (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);
SQL);

// ---- Ajustes condicionais (se a tabela já existia sem as colunas novas) ----
if (!columnExists($pdo, 'tarefas_usuario', 'desde')) {
    $pdo->exec("ALTER TABLE tarefas_usuario ADD COLUMN desde TEXT DEFAULT CURRENT_DATE");
    $pdo->exec("UPDATE tarefas_usuario SET desde = date('now') WHERE desde IS NULL OR TRIM(desde)=''");
}
if (!columnExists($pdo, 'tarefas_status', 'valor_tarefa')) {
    $pdo->exec("ALTER TABLE tarefas_status ADD COLUMN valor_tarefa REAL DEFAULT 0");
}
if (!columnExists($pdo, 'tarefas_status', 'mesada_ref')) {
    $pdo->exec("ALTER TABLE tarefas_status ADD COLUMN mesada_ref REAL DEFAULT 0");
}

// ---- Índices úteis ----
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tu_user_dia_desde ON tarefas_usuario(user_id, dia_semana, desde)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_ts_user_tarefa_data ON tarefas_status(user_id, tarefa_id, data)");

// ---- Defaults na tabela config ----
$pdo->exec("INSERT OR IGNORE INTO config(key,value) VALUES ('exibir_valores_filhos','1')");
$pdo->exec("INSERT OR IGNORE INTO config(key,value) VALUES ('bonus_percent','0')");

// ---- Usuários de exemplo (se não houver) ----
$existsSup = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='supervisor'")->fetchColumn();
if ($existsSup === 0) {
    $pwd = password_hash('admin123', PASSWORD_DEFAULT);
    $st = $pdo->prepare('INSERT INTO users(name,email,password_hash,role,mesada) VALUES (?,?,?,?,?)');
    $st->execute(['Supervisor', 'admin@local', $pwd, 'supervisor', 0]);
}

$existsChild = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='filho'")->fetchColumn();
if ($existsChild === 0) {
    $pwd = password_hash('filho123', PASSWORD_DEFAULT);
    $st = $pdo->prepare('INSERT INTO users(name,email,password_hash,role,mesada) VALUES (?,?,?,?,?)');
    $st->execute(['Fulano', 'fulano@local', $pwd, 'filho', 100]);
}

$pdo->commit();

echo "Banco inicializado/atualizado.
Supervisor: admin@local / admin123 | Filho: fulano@local / filho123";
?>
