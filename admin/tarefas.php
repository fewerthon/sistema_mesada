<?php
require_once 'layout.php';
csrf_check();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $titulo = trim($_POST['titulo'] ?? '');
        $peso = max(1, (int)($_POST['peso'] ?? 1));
        $pdo->prepare('INSERT INTO tarefas(titulo,peso,ativo) VALUES (?,?,1)')->execute([$titulo,$peso]);
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $peso = max(1, (int)($_POST['peso'] ?? 1));
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $pdo->prepare('UPDATE tarefas SET titulo=?, peso=?, ativo=? WHERE id=?')->execute([$titulo,$peso,$ativo,$id]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM tarefas WHERE id=?')->execute([$id]);
    }
}
$tasks = $pdo->query('SELECT * FROM tarefas ORDER BY ativo DESC, titulo')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="bg-white rounded shadow-sm p-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Tarefas</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novaTarefa">Nova tarefa</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Título</th><th>Peso</th><th>Ativa</th><th>Ações</th></tr></thead>
      <tbody>
        <?php foreach ($tasks as $t): ?>
        <tr>
          <td><?php echo htmlspecialchars($t['titulo']); ?></td>
          <td><?php echo (int)$t['peso']; ?></td>
          <td><?php echo $t['ativo'] ? 'Sim' : 'Não'; ?></td>
          <td>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editT<?php echo $t['id']; ?>">Editar</button>
            <form method="post" class="d-inline" onsubmit="return confirm('Excluir tarefa?');">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
              <button class="btn btn-sm btn-outline-danger">Excluir</button>
            </form>
          </td>
        </tr>
        <div class="modal fade" id="editT<?php echo $t['id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
          <form method="post">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
            <div class="modal-header"><h5 class="modal-title">Editar Tarefa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="mb-2"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?php echo htmlspecialchars($t['titulo']); ?>" required></div>
              <div class="mb-2"><label class="form-label">Peso</label><input class="form-control" type="number" min="1" name="peso" value="<?php echo (int)$t['peso']; ?>" required></div>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="at<?php echo $t['id']; ?>" name="ativo" <?php if($t['ativo']) echo 'checked'; ?>>
              <label class="form-check-label" for="at<?php echo $t['id']; ?>">Ativa</label></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button><button class="btn btn-primary" type="submit">Salvar</button></div>
          </form>
        </div></div></div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="modal fade" id="novaTarefa" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="create">
    <div class="modal-header"><h5 class="modal-title">Nova Tarefa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2"><label class="form-label">Título</label><input class="form-control" name="titulo" required></div>
      <div class="mb-2"><label class="form-label">Peso</label><input class="form-control" type="number" min="1" name="peso" value="1" required></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancelar</button><button class="btn btn-primary" type="submit">Salvar</button></div>
  </form>
</div></div></div>
</div><?php html_foot(); ?>
