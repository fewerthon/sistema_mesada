<?php
include "../includes/db.php";
session_start();
$current_id = $_SESSION['user_id'];

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO supervisores (nome, senha) VALUES (?, ?)");
        $stmt->execute([$_POST['nome'], password_hash($_POST['senha'], PASSWORD_DEFAULT)]);
    } elseif ($_POST['action'] === 'update') {
        $stmt = $pdo->prepare("UPDATE supervisores SET senha=? WHERE id=?");
        $stmt->execute([password_hash($_POST['senha'], PASSWORD_DEFAULT), $_POST['id']]);
    } elseif ($_POST['action'] === 'delete' && $_POST['id'] != $current_id) {
        $stmt = $pdo->prepare("DELETE FROM supervisores WHERE id=?");
        $stmt->execute([$_POST['id']]);
    }
}

$supervisores = $pdo->query("SELECT * FROM supervisores")->fetchAll();
?>
<h2>Gerenciar Supervisores</h2>
<table>
<tr><th>Nome</th><th>Ações</th></tr>
<?php foreach ($supervisores as $s): ?>
<tr>
  <td><?= htmlspecialchars($s['nome']) ?></td>
  <td>
    <?php if ($s['id'] != $current_id): ?>
      <form method="post" style="display:inline">
        <input type="hidden" name="id" value="<?= $s['id'] ?>">
        <input type="hidden" name="action" value="delete">
        <button>Remover</button>
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>