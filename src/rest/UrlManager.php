<?php

namespace eDesarrollos\rest;

class UrlManager extends \yii\web\UrlManager {

  public $enablePrettyUrl = true;

  public $showScriptName = false;

  public $enableStrictParsing = true;

  // public $ruleConfig = ['class' => 'eDesarrollos\rest\UrlRule'];

  public $rules = [
    'GET pdf/<controller:[\w-]+>/<action:[\w-]+>.<format:(?!pdf$)[\w]+>' => 'v1/default/error',
    'GET pdf/<controller:[\w-]+>.<format:(?!pdf$)[\w]+>' => 'v1/default/error',
    'GET excel/<controller:[\w-]+>/<action:[\w-]+>.<format:(?!xlsx$)[\w]+>' => 'v1/default/error',
    'GET excel/<controller:[\w-]+>.<format:(?!xlsx$)[\w]+>' => 'v1/default/error',
    'GET word/<controller:[\w-]+>/<action:[\w-]+>.<format:(?!word$)[\w]+>' => 'v1/default/error',
    'GET word/<controller:[\w-]+>.<format:(?!word$)[\w]+>' => 'v1/default/error',
    'OPTIONS <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>/<action:[\w-]+>.<formato:(json|xml|csv|html|sql)>' => '<module>/<controller>/options',
    'OPTIONS <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>.<formato:(json|xml|csv|html|sql)>' => '<module>/<controller>/options',
    'GET <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>/<action:[\w-]+>.<formato:(json|xml|csv|html|sql)>' => '<module>/<controller>/<action>',
    'GET <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>.<formato:(json|xm|csvl|html|sql)>' => '<module>/<controller>',
    'POST <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>/<action:[\w-]+>.<formato:(json|xml|csv|html|sql)>' => '<module>/<controller>/<action>',
    'POST <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>.<formato:(json|xml|csv|html|sql)>' => '<module>/<controller>/guardar',
    'PUT <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>/<action:[\w-]+>.<formato:(json|xml|csv|html|sql)>' => '<module>/<controller>/<action>',
    'PUT <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>.<formato:(json|xml|csv|html|sql)>' => '<module>/<controller>/guardar',
    'DELETE <module:(?!pdf$|excel$|word$)[\w-]+>/<controller:[\w-]+>.<formato:(json|xml|csv|html|sql)>' => '<module>/<controller>/eliminar',
    'GET <controller:[\w-]+>/<action:[\w-]+>.<formato:(pdf)>' => 'pdf/<controller>/<action>',
    'GET <controller:[\w-]+>.<formato:(pdf)>' => 'pdf/<controller>',
    'GET <controller:[\w-]+>/<action:[\w-]+>.<formato:(xlsx)>' => 'excel/<controller>/<action>',
    'GET <controller:[\w-]+>.<formato:(xlsx)>' => 'excel/<controller>',
    'GET <controller:[\w-]+>/<action:[\w-]+>.<formato:(word)>' => 'word/<controller>/<action>',
    'GET <controller:[\w-]+>.<formato:(word)>' => 'word/<controller>',
  ];
}
