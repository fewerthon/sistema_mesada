<?php
// Configurações globais
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');
session_start();

// caminho para sistema
$dir = str_replace('\\', '/', __DIR__);
// Remove somente se terminar em /admin ou /filho
define('ROOT_PATH', preg_replace('/\/(admin|filho)$/', '', $dir));



// Caminho do banco
const DB_PATH = ROOT_PATH . '/data/app.db';

// caminho para url
$baseUrl = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = rtrim($baseUrl, '/'); // remove barra final
$baseUrl = preg_replace('/\/(admin|filho)$/', '', $baseUrl);

// CSRF simples
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_input(): string {
    $t = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
    return "<input type='hidden' name='csrf_token' value='{$t}'>";
}
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = $_POST['csrf_token'] ?? '';
        if (!$in || !hash_equals($_SESSION['csrf_token'] ?? '', $in)) {
            http_response_code(400);
            exit('Falha de CSRF. Recarregue a página.');
        }
    }
}
function html_head(string $title = 'Mesada & Tarefas'): void {
    echo "<!doctype html><html lang='pt-BR'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . "</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='bg-light'>";
}
function html_foot(): void {
    echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script></body></html>";
}
?>
