<?php

namespace eDesarrollos\rest;

use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;

class AuthController extends JsonController {

  /**
   * @var \eDesarrollos\models\Usuario $usuario
   */
  public $usuario;
  public $permisos = [];

  public $accionesPermitidas = ["index", "post", "put", "delete"];

  public function behaviors() {
    $behavior = parent::behaviors();
    $behavior["authenticator"]["authMethods"] = [
      QueryParamAuth::class,
      HttpBearerAuth::class
    ];
    return $behavior;
  }

  public function beforeAction($action) {
    parent::beforeAction($action);

    $this->usuario = \Yii::$app->getUser()->getIdentity();

    if ($this->usuario === null) {
      throw new \yii\web\UnauthorizedHttpException('No está autorizado para realizar esta acción.');
    }

    if (!empty($this->permisos)) {
      $this->usuario->cargarPermisos($this->permisos);
    }

    return true;
  }
}
