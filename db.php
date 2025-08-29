<?php
require_once ROOT_PATH . '/config.php';
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(ROOT_PATH . '/data')) {
            mkdir(ROOT_PATH . '/data', 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}
?>
