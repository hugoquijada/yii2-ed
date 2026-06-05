<?php

namespace eDesarrollos\rest;

use eDesarrollos\data\Respuesta;
use yii\rest\Serializer as YiiSerializer;
use Yii;

class Serializer extends YiiSerializer {
  protected array $formatosDocumento = [
    JsonController::FORMATO_HTML,
    JsonController::FORMATO_CSV,
    JsonController::FORMATO_XLSX,
    JsonController::FORMATO_PDF,
    JsonController::FORMATO_DOCX,
  ];

  public function serialize($data) {
    if ($data instanceof Respuesta) {
      $formato = Yii::$app->getResponse()->format;
      if (in_array($formato, $this->formatosDocumento, true)) {
        return $data;
      }
    }

    $data = parent::serialize($data);
    if ($data instanceof Respuesta) {
      return $data->cuerpo;
    }

    return $data;
  }

}
