<?php

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace eDesarrollos\gii\generators\model;

use Yii;
use yii\gii\CodeFile;

class Generator extends \yii\gii\generators\model\Generator {

  /**
   * @inheritdoc
   */
  public function getName() {
    return 'Modelos';
  }

  /**
   * @inheritdoc
   */
  public function getDescription() {
    return 'Genera una clase ActiveRecord para la tabla especificada de la base de datos.';
  }

  /**
   * @inheritdoc
   */
  public function generate() {
    $files = [];
    $relations = $this->generateRelations();
    $db = $this->getDbConnection();
    foreach ($this->getTableNames() as $tableName) {
      // model:
      $modelClassName = $this->generateClassName($tableName);
      $queryClassName = $this->generateQuery ? $this->generateQueryClassName($modelClassName) : false;
      $tableRelations = isset($relations[$tableName]) ? $relations[$tableName] : [];
      $tableSchema = $db->getTableSchema($tableName);
      $params = [
        'tableName' => $tableName,
        'className' => $modelClassName,
        'queryClassName' => $queryClassName,
        'tableSchema' => $tableSchema,
        'properties' => $this->generateProperties($tableSchema),
        'labels' => $this->generateLabels($tableSchema),
        'rules' => $this->generateRules($tableSchema),
        'relations' => $tableRelations,
        'relationsClassHints' => $this->generateRelationsClassHints($tableRelations, $this->generateQuery),
      ];
      $files[] = new CodeFile(
        Yii::getAlias('@' . str_replace('\\', '/', $this->ns)) . '/' . $modelClassName . '.php',
        $this->render('model.php', $params)
      );

      // query:
      if ($queryClassName) {
        $params['className'] = $queryClassName;
        $params['modelClassName'] = $modelClassName;
        $files[] = new CodeFile(
          Yii::getAlias('@' . str_replace('\\', '/', $this->queryNs)) . '/' . $queryClassName . '.php',
          $this->render('query.php', $params)
        );
      }
    }

    return $files;
  }

}
