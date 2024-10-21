<?php

namespace eDesarrollos\gii\generators\controller;

use yii\gii\CodeFile;

class Generator extends \yii\gii\generators\controller\Generator {

  /**
   * @var string the controller class name
   */
  public $controllerClass = "app\\modulos\\v1\\controladores\\";
  /**
   * @var string the base class of the controller
   */
  public $baseClass = 'eDesarrollos\rest\AuthController';
  /**
   * @var string list of action IDs separated by commas or spaces
   */
  public $actions = 'index';


  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'Controladores';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return 'Este generador te ayuda a escribir un controlador';
  }

  /**
   * {@inheritdoc}
   */
  public function requiredTemplates() {
    return ['controller.php'];
  }

  /**
   * {@inheritdoc}
   */
  public function stickyAttributes() {
    return ['baseClass'];
  }

  /**
   * {@inheritdoc}
   */
  public function generate() {
    $files = [];

    $files[] = new CodeFile(
      $this->getControllerFile(),
      $this->render('controller.php')
    );

    /*
    foreach ($this->getActionIDs() as $action) {
      $files[] = new CodeFile(
        $this->getViewFile($action),
        $this->render('view.php', ['action' => $action])
      );
    } // */

    return $files;
  }

}
