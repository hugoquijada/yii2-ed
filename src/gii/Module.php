<?php

namespace eDesarrollos\gii;

class Module extends \yii\gii\Module {

  protected function coreGenerators() {
    return [
      'model' => ['class' => 'app\gii\generators\model\Generator'],
      // 'crud' => ['class' => 'yii\gii\generators\crud\Generator'],
      'controller' => ['class' => 'app\gii\generators\controller\Generator'],
      // 'form' => ['class' => 'yii\gii\generators\form\Generator'],
      // 'module' => ['class' => 'yii\gii\generators\module\Generator'],
      // 'extension' => ['class' => 'yii\gii\generators\extension\Generator'],
    ];
  }

}
