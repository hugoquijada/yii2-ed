<?php

namespace eDesarrollos\rest;

use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\Response;
use Yii;


/**
 * @property \yii\web\Application $app
 * @property \yii\web\Request $req
 * @property \yii\web\Response $res
 * @property \yii\db\ActiveQuery $queryInicial
 * @property int $limite
 * @property int $pagina
 * @property string $ordenar
 * @property string $modelClass
 */
class JsonController extends Controller {

  public $app = null;
  public $req = null;
  public $res = null;

  public $queryInicial = null;
  public $modelClass = null;

  public $limite = null;
  public $pagina = null;
  public $ordenar = null;

  public $serializer = 'common\rest\Serializer';

  public function behaviors() {
    $behavior = parent::behaviors();
    $behavior['contentNegotiator'] =  [
      'class' => ContentNegotiator::class,
      'formats' => [
        'application/json' => Response::FORMAT_JSON,
        'application/xml' => Response::FORMAT_XML,
      ],
    ];
    $behavior['corsFilter'] = [
      'class' => Cors::class,
      'cors' => [
        'Origin' => ['*'],
        'Access-Control-Request-Method' => [
          'GET', 'POST', 'PUT', 'PATCH', 
          'DELETE', 'HEAD', 'OPTIONS'
        ],
        'Access-Control-Request-Headers' => ['*'],
      ],
    ];
    $behavior["authenticator"]["except"] = ['options'];
    return $behavior;
  }

  public function beforeAction($action) {
    parent::beforeAction($action);
    Yii::$app->getResponse()->format = Response::FORMAT_JSON;
    $this->app = Yii::$app;
    $this->req = $this->app->getRequest();
    $this->res = $this->app->getResponse();
    if ($this->req->isGet) {
      $this->limite = $this->req->get("limite", 20);
      $this->pagina = $this->req->get("pagina", 0);
      $this->ordenar = $this->req->get("ordenar", "");
    }
    if ($this->modelClass !== null) {
      $model = new $this->modelClass;
      $tableName = $this->modelClass::tableName();
      $this->queryInicial = $this->modelClass::find();
      if ($model->hasProperty('eliminado')) {
        $this->queryInicial
          ->where(["{{{$tableName}}}.[[eliminado]]" => null]);
      }
    }
    return true;
  }

}
