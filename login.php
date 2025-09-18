<?php
require_once __DIR__ . '/config.php';
require_once ROOT_PATH . '/auth.php';
csrf_check();
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $user = find_user_by_email($email);
    if ($user && password_verify($pass, $user['password_hash'])) {
        login_user($user);

// VERIFICA SE O CHECKBOX "LEMBRAR DE MIM" FOI MARCADO
    if (isset($_POST['lembrar']) && $_POST['lembrar'] == '1') {
        // Gera tokens seguros e aleatórios
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        // Define a data de expiração para 30 dias
        $expires = new DateTime('now');
        $expires->add(new DateInterval('P30D')); // P30D = Período de 30 Dias

        // Armazena os tokens no banco de dados
        $stmt = db()->prepare("INSERT INTO auth_tokens (selector, hashed_validator, user_id, expires) VALUES (?, ?, ?, ?)");
        // Criptografa o validador antes de salvar (ESSENCIAL)
        $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
        $stmt->execute([
            $selector,
            $hashed_validator,
            $user['id'],
            $expires->format('Y-m-d H:i:s')
        ]);

        // Cria o cookie no navegador do usuário
        $cookie_value = $selector . ':' . $validator;
        setcookie('lembrar_de_mim', $cookie_value, [
            'expires' => $expires->getTimestamp(),
            'path' => '/',
            'domain' => '', // Deixe em branco para o domínio atual
            'secure' => true,   // O cookie só será enviado em conexões HTTPS
            'httponly' => true, // O cookie não pode ser acessado por JavaScript
            'samesite' => 'Lax'
        ]);
    }



        header('Location: index.php'); exit;
    } else {
        $error = 'Login inválido';
    }
}
html_head('Login - Mesada & Tarefas');
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="h4 mb-3 text-center">Entrar</h1>
          <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
          <form method="post">
            <?php echo csrf_input(); ?>
            <div class="mb-3">
              <label class="form-label">E-mail</label>
              <input class="form-control" type="email" name="email" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Senha</label>
              <input class="form-control" type="password" name="password" required>
            </div>
            <div>
                <input type="checkbox" name="lembrar" id="lembrar" value="1" checked>
                <label for="lembrar">Lembrar de mim por 30 dias</label>
            </div>
            <button class="btn btn-primary w-100" type="submit">Entrar</button>
          </form>
        </div>
      </div>
     </div>
  </div>
</div>
<?php html_foot(); ?>
