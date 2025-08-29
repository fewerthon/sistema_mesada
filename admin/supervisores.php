<?php
require_once 'layout.php';
csrf_check();
$pdo = db();

$meId = (int)($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pwd   = password_hash($_POST['password'] ?? '123456', PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?)')
            ->execute([$name, $email, $pwd, 'supervisor']);
    } elseif ($action === 'update') {
        $id    = (int)($_POST['id'] ?? 0);
        if ($id !== $meId) { // não permite editar a si mesmo por aqui
            $name  = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $pdo->prepare('UPDATE users SET name=?, email=? WHERE id=? AND role="supervisor"')
                ->execute([$name, $email, $id]);
            if (!empty($_POST['password'])) {
                $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=? AND role="supervisor"')
                    ->execute([$pwd, $id]);
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id !== $meId) { // não permite excluir a si mesmo
            $pdo->prepare('DELETE FROM users WHERE id=? AND role="supervisor"')->execute([$id]);
        }
    }
}

// lista de supervisores, exceto o logado
$st = $pdo->prepare("SELECT * FROM users WHERE role='supervisor' AND id<>? ORDER BY name");
$st->execute([$meId]);
$supervisores = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="bg-white rounded shadow-sm p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Supervisores</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoSupervisor">Novo supervisor</button>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Nome</th><th>E-mail</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($supervisores as $s): ?>
        <tr>
          <td><?php echo htmlspecialchars($s['name']); ?></td>
          <td><?php echo htmlspecialchars($s['email']); ?></td>
          <td>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?php echo $s['id']; ?>">Editar</button>
            <form method="post" class="d-inline" onsubmit="return confirm('Excluir este supervisor?');">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
              <button class="btn btn-sm btn-outline-danger">Excluir</button>
            </form>
          </td>
        </tr>

        <div class="modal fade" id="edit<?php echo $s['id']; ?>" tabindex="-1">
          <div class="modal-dialog"><div class="modal-content">
            <form method="post">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
              <div class="modal-header">
                <h5 class="modal-title">Editar Supervisor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-2">
                  <label class="form-label">Nome</label>
                  <input class="form-control" name="name" value="<?php echo htmlspecialchars($s['name']); ?>" required>
                </div>
                <div class="mb-2">
                  <label class="form-label">E-mail</label>
                  <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($s['email']); ?>" required>
                </div>
                <div class="mb-2">
                  <label class="form-label">Nova senha (opcional)</label>
                  <input class="form-control" type="password" name="password">
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
                <button class="btn btn-primary" type="submit">Salvar</button>
              </div>
            </form>
          </div></div>
        </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="novoSupervisor" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h5 class="modal-title">Novo Supervisor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Nome</label>
          <input class="form-control" name="name" required>
        </div>
        <div class="mb-2">
          <label class="form-label">E-mail</label>
          <input class="form-control" type="email" name="email" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Senha</label>
          <input class="form-control" type="password" name="password" required>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button>
        <button class="btn btn-primary" type="submit">Salvar</button>
      </div>
    </form>
  </div></div>
</div>
</div><?php html_foot(); ?>
