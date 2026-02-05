<?php

namespace eDesarrollos\mail\comandos;

use eDesarrollos\mail\models\NotificacionCorreo;
use eDesarrollos\mail\models\NotificacionCorreoAdjunto;
use Exception;
use Yii;
use yii\console\Controller;
use yii\db\Expression;

class CronController extends Controller {

  private $limite = 1;
  # Este debe venir de un archivo de configuración o de alguna tabla
  private $emisor = [];
  private $correoAResponder = "";

  public function actionIndex() {

    $notificaciones = NotificacionCorreo::find()
      ->andWhere([
        "estatus" => NotificacionCorreo::ESTATUS_NUEVO,
        "enviado" => null
      ])
      ->orderBy(["prioridad" => SORT_ASC])
      ->limit($this->limite);

    foreach ($notificaciones->each() as $notif) {
      /** @var NotificacionCorreo $notif */
      # Guardar el que se este procesando
      $notif->estatus = NotificacionCorreo::ESTATUS_PROCESO;
      $notif->modificado = new Expression('now()');
      $notif->save();

      $view = new \yii\web\View();

      $contenido = $view->render("@edesarrollos/mail/views/layouts/cuerpo", [
        "cuerpo" => $notif->cuerpo
      ]);

      $destinos = $notif->receptor;
      if (!is_array($destinos)) {
        $notif->estatus = NotificacionCorreo::ESTATUS_ERROR;
        $notif->detalle = "No hay destinos para el correo";
        $notif->save();
        continue;
      }
      if(isset($destinos[0]) && is_array($destinos[0])) {
        if(empty($destinos[0])) {
          $notif->estatus = NotificacionCorreo::ESTATUS_ERROR;
          $notif->detalle = "No hay destinos para el correo";
          $notif->save();
          continue;
        }
        $destinos = $destinos[0];
      }

      try {

        $emisor = $this->emisor;
        if(empty($emisor)) {
          $params = Yii::$app->params;
          if(isset($params["correo.emisor"])) {
            $emisor = $params["correo.emisor"];
          }
        }

        if(empty($emisor)) {
          throw new Exception("Debe configurar el correo emisor");
        }

        $correo = \Yii::$app->mailer->compose()
          ->setFrom($emisor)
          // ->setReplyTo($this->correoAResponder)
          ->setTo($destinos)
          ->setSubject($notif->asunto)
          ->setHtmlBody($contenido);

        foreach ($notif->adjuntos as $adjunto) {
          if (is_file($adjunto->ruta)) {
            $correo->attach($adjunto->ruta);
          }
        }

        $resultado = $correo->send();

        if ($resultado) {
          $notif->enviado = new Expression('now()');
          $notif->estatus = NotificacionCorreo::ESTATUS_ENVIADO;
        } else {
          $notif->estatus = NotificacionCorreo::ESTATUS_ERROR;
          $notif->detalle = $correo->toString(); # Buscar la manera de obtener el error
        }
        $notif->save();

        $this->stdout("\n");
      } catch (\Exception $e) {

        $notif->estatus = $notif::ESTATUS_ERROR;
        $notif->detalle = $e->getMessage();
        $notif->save();

        $this->stdout(" Ocurrió un error al guardar {$e->getMessage()}\n");
      }

    }
  }
}
