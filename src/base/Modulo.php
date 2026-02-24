<?php

namespace eDesarrollos\base;

class Modulo extends \yii\base\Module {

  public function getViewPath() {
    if ($this->_viewPath === null) {
      $this->_viewPath = $this->getBasePath() . DIRECTORY_SEPARATOR . 'views';
    }

    return $this->_viewPath;
  }
}
