<?php

use yii\gii\generators\model\Generator;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var yii\widgets\ActiveForm $form */
/** @var yii\gii\generators\model\Generator $generator */

echo $form->field($generator, 'db');
echo $form->field($generator, 'useSchemaName')->checkbox();
echo $form->field($generator, 'tableName')->textInput([
  'autocomplete' => 'off',
  'data' => [
    'table-prefix' => $generator->getTablePrefix(),
    'action' => Url::to(['default/action', 'id' => 'model', 'name' => 'GenerateClassName'])
  ]
]);
echo $form->field($generator, 'modelClass');
echo $form->field($generator, 'ns');
echo $form->field($generator, 'generateRelationsFromCurrentSchema')->checkbox();
echo $form->field($generator, 'useClassConstant')->checkbox();
echo $form->field($generator, 'generateLabelsFromComments')->checkbox();
