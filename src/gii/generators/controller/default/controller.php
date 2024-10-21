<?php

use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/** @var yii\web\View $this */
/** @var yii\gii\generators\controller\Generator $generator */

echo "<?php\n";
?>

namespace <?= $generator->getControllerNamespace() ?>;

use <?= trim($generator->baseClass, '\\') . "\n" ?>

class <?= StringHelper::basename($generator->controllerClass) ?> extends AuthController {

<?php foreach ($generator->getActionIDs() as $action): ?>
  public function action<?= Inflector::id2camel($action) ?>() {
    return $this->render('<?= $action ?>');
  }
<?php endforeach; ?>

  /*
  public function buscador(&$query, $request) {
    $id = $request->get($this->modeloID, "");

    if($id !== "") {
      $query->andWhere([$this->modeloID => $id]);
    }
  } // */
  
}
