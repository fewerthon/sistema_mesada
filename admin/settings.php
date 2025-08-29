<?php
include "../includes/db.php";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $show_values = isset($_POST['show_values']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE settings SET show_values=? WHERE id=1");
    $stmt->execute([$show_values]);
}
$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();
?>
<form method="post">
    <label>
        <input type="checkbox" name="show_values" value="1" <?= $settings['show_values'] ? 'checked' : '' ?>>
        Mostrar valores das tarefas
    </label>
    <button type="submit">Salvar</button>
</form>