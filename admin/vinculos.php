<?php
require_once __DIR__ . '/layout.php';
csrf_check();
$pdo = db();
$filhos = $pdo->query("SELECT id, name FROM users WHERE role='filho' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$tarefas = $pdo->query("SELECT id, titulo, peso FROM tarefas WHERE ativo=1 ORDER BY titulo")->fetchAll(PDO::FETCH_ASSOC);
$uid = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ((count($filhos)>0) ? (int)$filhos[0]['id'] : 0);
if ($uid) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->prepare('DELETE FROM tarefas_usuario WHERE user_id=?')->execute([$uid]);
        foreach (range(0,6) as $d) {
            $list = $_POST['dia_'.$d] ?? [];
            foreach ($list as $tid) {
                $pdo->prepare('INSERT INTO tarefas_usuario(user_id,tarefa_id,dia_semana) VALUES (?,?,?)')->execute([$uid,(int)$tid,$d]);
            }
        }
        echo '<div class="alert alert-success">Vínculos atualizados.</div>';
    }
    $vinc = $pdo->prepare('SELECT tarefa_id, dia_semana FROM tarefas_usuario WHERE user_id=?');
    $vinc->execute([$uid]);
    $map = [];
    foreach ($vinc->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $map[$v['dia_semana']][] = (int)$v['tarefa_id'];
    }
}
?>
<div class="bg-white rounded shadow-sm p-3">
  <form method="get" class="row g-2 mb-3">
    <div class="col-auto"><label class="form-label">Filho</label></div>
    <div class="col-auto">
      <select class="form-select" name="user_id" onchange="this.form.submit()">
        <?php foreach ($filhos as $f): ?>
          <option value="<?php echo $f['id']; ?>" <?php if($uid==$f['id']) echo 'selected'; ?>><?php echo htmlspecialchars($f['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
  <?php if ($uid): ?>
  <form method="post">
    <?php echo csrf_input(); ?>
    <div class="row g-3">
      <?php
      $dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
      foreach (range(0,6) as $d): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="border rounded p-2 h-100">
            <h6><?php echo $dias[$d]; ?></h6>
            <?php foreach ($tarefas as $t):
              $checked = in_array($t['id'], $map[$d] ?? []);
            ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="t<?php echo $d.'_'.$t['id']; ?>" name="dia_<?php echo $d; ?>[]" value="<?php echo $t['id']; ?>" <?php if($checked) echo 'checked'; ?>>
                <label class="form-check-label" for="t<?php echo $d.'_'.$t['id']; ?>"><?php echo htmlspecialchars($t['titulo']); ?> <small class="text-muted">(peso <?php echo (int)$t['peso']; ?>)</small></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-3"><button class="btn btn-primary">Salvar vínculos</button></div>
  </form>
  <?php else: ?>
    <div class="alert alert-warning">Cadastre ao menos um filho.</div>
  <?php endif; ?>
</div>
</div><?php html_foot(); ?>
