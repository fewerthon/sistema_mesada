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

require_once 'db.php';

// caminho para url
$baseUrl = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = rtrim($baseUrl, '/'); // remove barra final
$baseUrl = preg_replace('/\/(admin|filho)$/', '', $baseUrl);



// Se o usuário NÃO ESTÁ LOGADO na sessão, mas TEM um cookie "lembrar de mim"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['lembrar_de_mim'])) {
    
    // Divide o cookie em seletor e validador
    list($selector, $validator) = explode(':', $_COOKIE['lembrar_de_mim']);

    if ($selector && $validator) {
        // Procura o seletor no banco de dados
        // $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
        // $stmt = db()->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
        $stmt = db()->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= datetime('now')");
        $stmt->execute([$selector]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token_data) {
            // Se encontrou, verifica se o validador do cookie corresponde ao do banco
            if (password_verify($validator, $token_data['hashed_validator'])) {
                
                // Sucesso! O cookie é válido. Loga o usuário.
                $_SESSION['user_id'] = $token_data['user_id'];


                $st = db()->prepare("SELECT name, role FROM users WHERE id = ?");
                $st->execute([$token_data['user_id']]);
                $res = $st->fetch(PDO::FETCH_ASSOC);
                $st->closeCursor();
                $_SESSION['name'] = $res['name'];
                $_SESSION['role'] = $res['role'];

                // (Opcional, mas recomendado para segurança)
                // Gerar novos tokens, atualizar o banco e o cookie do usuário.
                // Isso invalida o token antigo e dificulta o roubo de sessão.

            } else {
                // Se a validação falhar, apague o cookie inválido
                setcookie('lembrar_de_mim', '', time() - 3600, '/');
            }
        }
    }
}

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
    echo "<!doctype html><html lang='pt-BR'><head>
<meta charset='utf-8'>
<link rel='manifest' href='/manifest.json'>
<meta name='theme-color' content='#4a90e2'>
<meta name='viewport' content='width=device-width, initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . "</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "</head><body class='bg-light'>";
}
function html_foot(): void {
    echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').then(registration => {
        console.log('ServiceWorker registrado com sucesso: ', registration.scope);
      }).catch(error => {
        console.log('Falha no registro do ServiceWorker: ', error);
      });
    });
  }
</script>
</body></html>";
}
?>
