<?php
require_once 'layout.php';
csrf_check();
$pdo = db();

// Carrega valores atuais (defaults caso não existam)
$st = $pdo->prepare("SELECT key, value FROM config WHERE key IN ('exibir_valores_filhos','bonus_percent')");
$st->execute();
$cfg = ['exibir_valores_filhos' => '1', 'bonus_percent' => '0'];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $cfg[$r['key']] = (string)$r['value'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $exibir = isset($_POST['exibir_valores_filhos']) ? '1' : '0';
  $bonus  = isset($_POST['bonus_percent']) ? (string)max(0, min(100, (int)$_POST['bonus_percent'])) : '0';

  // UPSERT manual (compatível com SQLite antigo)
  $up1 = $pdo->prepare("UPDATE config SET value=? WHERE key='exibir_valores_filhos'"); $up1->execute([$exibir]);
  if ($up1->rowCount() === 0) { $pdo->prepare("INSERT INTO config(key,value) VALUES ('exibir_valores_filhos',?)")->execute([$exibir]); }

  $up2 = $pdo->prepare("UPDATE config SET value=? WHERE key='bonus_percent'"); $up2->execute([$bonus]);
  if ($up2->rowCount() === 0) { $pdo->prepare("INSERT INTO config(key,value) VALUES ('bonus_percent',?)")->execute([$bonus]); }

  echo '<div class="alert alert-success">Configurações salvas.</div>';

  // Recarrega
  $cfg['exibir_valores_filhos'] = $exibir;
  $cfg['bonus_percent'] = $bonus;
}
?>
<div class="bg-white rounded shadow-sm p-3">
  <h5 class="mb-3">Configurações</h5>
  <form method="post" class="row g-3">
    <?php echo csrf_input(); ?>

    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="exibir_valores_filhos" name="exibir_valores_filhos" <?php if($cfg['exibir_valores_filhos']==='1') echo 'checked'; ?>>
        <label class="form-check-label" for="exibir_valores_filhos">
          Mostrar valores para os filhos (telas do filho)
        </label>
      </div>
      <div class="form-text">Desmarcando, o filho não verá valores (cards e coluna de valores).</div>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Bônus por 100% no mês (%)</label>
      <input type="number" min="0" max="100" step="1" class="form-control" name="bonus_percent" value="<?php echo htmlspecialchars($cfg['bonus_percent']); ?>">
      <div class="form-text">Percentual inteiro aplicado sobre o total ganho no mês, apenas se todas as tarefas do mês forem concluídas.</div>
    </div>

    <div class="col-12">
      <button class="btn btn-primary">Salvar</button>
    </div>
  </form>
</div>
</div><?php html_foot(); ?>
