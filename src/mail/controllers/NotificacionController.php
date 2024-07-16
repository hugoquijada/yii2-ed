<?php

namespace eDesarrollos\mail\controllers;

use eDesarrollos\mail\models\NotificacionCorreo;
use Yii;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

# TODO: Cambiar por un controlador con seguridad
class NotificacionController extends Controller {
  
  const TIPO_UNO = "uno";
  const TIPO_MULTIPLE = "multiple";

  public function actionIndex() {
    $req = Yii::$app->getRequest();
    if(!$req->isPost) {
      # TODO: Cambiar por respuestas
      throw new ForbiddenHttpException("El método debe enviarse por POST");
    }
    # Para indicar si se envía un correo por receptor o un correo con multiple receptores
    # valores: uno o multiple
    $tipo = $req->getBodyParam("tipo", "");

    $tran = Yii::$app->getDb()->beginTransaction();
    try {
      if($tipo === self::TIPO_UNO) {
        $modelo = NotificacionCorreo::enviar($req->getBodyParams());
      } elseif($tipo === self::TIPO_MULTIPLE) {
        $modelo = NotificacionCorreo::enviarMultiple($req->getBodyParams());
      }
      if($modelo->hasErrors()) {
        foreach($modelo->getFirstErrors() as $error) {
          throw new BadRequestHttpException($error);
        }
      }
      $tran->commit();
    } catch(BadRequestHttpException $e) {
      $tran->rollBack();

      throw $e;
    } catch(\Exception $e) {
      $tran->rollBack();

      throw new BadRequestHttpException("Ocurrió un error en el servidor: {$e->getMessage()}");
    }

  }

}