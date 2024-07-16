<?php

namespace eDesarrollos\rest;

use yii\rest\Serializer as YiiSerializer;
use eDesarrollos\data\Respuesta;

class Serializer extends YiiSerializer {

  public function serialize($data) {
    $data = parent::serialize($data);
    if ($data instanceof Respuesta) {
      return $data->cuerpo;
    }

    return $data;
  }

}
