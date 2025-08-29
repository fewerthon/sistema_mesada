<?php
require_once __DIR__ . '/layout.php';
csrf_check();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mesada = (float)($_POST['mesada'] ?? 100);
        $pwd = password_hash($_POST['password'] ?? '123456', PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users(name,email,password_hash,role,mesada) VALUES (?,?,?,?,?)')
            ->execute([$name,$email,$pwd,'filho',$mesada]);
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mesada = (float)($_POST['mesada'] ?? 100);
        $pdo->prepare('UPDATE users SET name=?, email=?, mesada=? WHERE id=? AND role="filho"')
            ->execute([$name,$email,$mesada,$id]);
        if (!empty($_POST['password'])) {
            $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$pwd, $id]);
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM users WHERE id=? AND role="filho"')->execute([$id]);
    }
}
$filhos = $pdo->query("SELECT * FROM users WHERE role='filho' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="bg-white rounded shadow-sm p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Filhos</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoFilho">Novo filho</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Nome</th><th>E-mail</th><th>Mesada</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($filhos as $f): ?>
        <tr>
          <td><?php echo htmlspecialchars($f['name']); ?></td>
          <td><?php echo htmlspecialchars($f['email']); ?></td>
          <td><?php echo money_br((float)$f['mesada']); ?></td>
          <td>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?php echo $f['id']; ?>">Editar</button>
            <form method="post" class="d-inline" onsubmit="return confirm('Excluir este filho?');">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
              <button class="btn btn-sm btn-outline-danger">Excluir</button>
            </form>
          </td>
        </tr>
        <div class="modal fade" id="edit<?php echo $f['id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
            <div class="modal-header"><h5 class="modal-title">Editar Filho</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="mb-2"><label class="form-label">Nome</label><input class="form-control" name="name" value="<?php echo htmlspecialchars($f['name']); ?>" required></div>
              <div class="mb-2"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($f['email']); ?>" required></div>
              <div class="mb-2"><label class="form-label">Mesada</label><input class="form-control" type="number" step="0.01" name="mesada" value="<?php echo htmlspecialchars($f['mesada']); ?>" required></div>
              <div class="mb-2"><label class="form-label">Nova senha (opcional)</label><input class="form-control" type="password" name="password"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button><button class="btn btn-primary" type="submit">Salvar</button></div>
          </form>
        </div></div></div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="modal fade" id="novoFilho" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="create">
    <div class="modal-header"><h5 class="modal-title">Novo Filho</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2"><label class="form-label">Nome</label><input class="form-control" name="name" required></div>
      <div class="mb-2"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" required></div>
      <div class="mb-2"><label class="form-label">Senha</label><input class="form-control" type="password" name="password" required></div>
      <div class="mb-2"><label class="form-label">Mesada mensal</label><input class="form-control" type="number" step="0.01" name="mesada" value="100" required></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button><button class="btn btn-primary" type="submit">Salvar</button></div>
  </form>
</div></div></div>
</div><?php html_foot(); ?>
