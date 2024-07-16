<?php

namespace eDesarrollos\mail\commands;

use eDesarrollos\mail\models\NotificacionCorreo;
use yii\console\Controller;

class NotificacionController extends Controller {

  public function stdout($msg) {
    return parent::stdout("{$msg}\n");
  }

  public function actionGuardar() {
    $modelo = NotificacionCorreo::crear()
      ->setReceptor([
        "hquijada@edesarrollos.com" => "Hugo Quijada",
        "rsotobernal@edesarrollos.com" => "Rafael Soto"
      ])
      ->setAsunto("Correo con header y footer")
      ->setCuerpo("<h1>Título del Mensaje</h1><br><p>Este es un ejemplo de cómo enviamos un mensaje alerta desde el sistema</p>");

    if(!$modelo->save()) {
      $this->stdout(json_encode($modelo->getFirstErrors()));
    }

    $this->stdout("Proceso terminado");
  }

  public function actionGuardarMultiple() {
    $parametros = [
      "asunto" => "Asunto del correo",
      "cuerpo" => "Este es el cuerpo del correo, puede ser <strong>HTML</strong>",
      "receptores" => [
        "hquijada@edesarrollos.com"
      ],
      "adjuntos" => [# Debe contener la(s) ruta(s) al(los) archivo(s)
      ]
    ];

    $resultado = NotificacionCorreo::enviarMultiple($parametros);
    print($resultado);
  }

} 