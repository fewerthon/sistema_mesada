<?php
require_once __DIR__ . '/db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function columnExists(PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("PRAGMA table_info($table)");
    $st->execute();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (strcasecmp($col['name'], $column) === 0) return true;
    }
    return false;
}

try {
    $pdo->beginTransaction();

    // 1) vínculo com data de início
    if (!columnExists($pdo, 'tarefas_usuario', 'desde')) {
        // DEFAULT válido em SQLite (constante/keyword)
        $pdo->exec("ALTER TABLE tarefas_usuario ADD COLUMN desde TEXT DEFAULT CURRENT_DATE");
        // backfill para linhas já existentes
        $pdo->exec("UPDATE tarefas_usuario SET desde = date('now') WHERE desde IS NULL OR TRIM(desde)=''");
    }

    // 2) congelar valor na conclusão
    if (!columnExists($pdo, 'tarefas_status', 'valor_tarefa')) {
        $pdo->exec("ALTER TABLE tarefas_status ADD COLUMN valor_tarefa REAL DEFAULT 0");
    }
    if (!columnExists($pdo, 'tarefas_status', 'mesada_ref')) {
        $pdo->exec("ALTER TABLE tarefas_status ADD COLUMN mesada_ref REAL DEFAULT 0");
    }

    // 3) índices
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tu_user_dia_desde ON tarefas_usuario(user_id, dia_semana, desde)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ts_user_tarefa_data ON tarefas_status(user_id, tarefa_id, data)");

    $pdo->commit();
    echo "OK v1\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo "Erro na migração: " . $e->getMessage() . "\n";
}
?>