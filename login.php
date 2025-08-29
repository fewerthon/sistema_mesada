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
            <button class="btn btn-primary w-100" type="submit">Entrar</button>
          </form>
        </div>
      </div>
     </div>
  </div>
</div>
<?php html_foot(); ?>
