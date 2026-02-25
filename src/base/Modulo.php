<?php

namespace eDesarrollos\base;

class Modulo extends \yii\base\Module {

  public function getViewPath() {
    return $this->getBasePath() . DIRECTORY_SEPARATOR . 'vistas';
  }
}
