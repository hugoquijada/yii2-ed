<?php

namespace eDesarrollos\mail;

use Yii;

/**
 * v1 module definition class
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface {

  /**
   * {@inheritdoc}
   */
  public $controllerNamespace = 'eDesarrollos\mail\controladores';

  public function bootstrap($app) {
    if ($app instanceof \yii\console\Application) {
      $this->controllerNamespace = 'eDesarrollos\mail\comandos';
    }
  }

}
