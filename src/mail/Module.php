<?php

namespace app\modules\mail;

use Yii;

/**
 * v1 module definition class
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface {

  /**
   * {@inheritdoc}
   */
  public $controllerNamespace = 'app\modules\mail\controllers';

  /**
   * {@inheritdoc}
   */
  public function init() {
    parent::init();

    $app = Yii::$app;
    if(!($app instanceof \yii\console\Application)){
      $response = $app->getResponse();
      $headers = $response->getHeaders();
      $params = \Yii::$app->params;

      $headers->set('Access-Control-Allow-Methods', '*');
      $headers->set('Access-Control-Allow-Headers', '*');
      $headers->set('Access-Control-Allow-Origin', '*');
      $headers->set('Access-Control-Request-Method', 'POST, GET, DELETE, PUT, OPTIONS');
      $headers->set('Access-Control-Allow-Credentials', 'true');
      $headers->set('Access-Control-Max-Age', 86400);
      if (Yii::$app->getRequest()->isOptions) {
        Yii::$app->end();
      }
      \Yii::$app->getUser()->enableSession = false;
      \Yii::$app->getUser()->identityClass = 'app\models\Usuario';
    }
  }

  public function bootstrap($app) {
    if ($app instanceof \yii\console\Application) {
      $this->controllerNamespace = 'app\modules\mail\commands';
    }
  }

}
