<?php

namespace eDesarrollos\rest;

class UrlRule extends \yii\rest\UrlRule {

  public $pluralize = false;

  public $patterns = [
    'PUT' => 'put',
    'DELETE' => 'delete',
    'GET,HEAD' => 'index',
    'POST' => 'post',
    'GET,HEAD' => 'index',
    '' => 'options',
  ];

}