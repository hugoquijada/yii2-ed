<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use Firebase\JWT\JWT;

class Usuario extends ActiveRecord implements IdentityInterface {

  /**
   * Finds an identity by the given id.
   *
   * @param string|int $id the id to be looked for
   * @return IdentityInterface|null the identity object that matches the given id.
   */
  public static function findIdentity($id) {
    return static::findOne($id);
  }

  /**
   * Finds an identity by the given token.
   *
   * @param string $token the token to be looked for
   * @return IdentityInterface|null the identity object that matches the given token.
   */
  public static function findIdentityByAccessToken($token, $type = null) {
    $key = Yii::$app->params['jwt.key'];
    $jwt = JWT::decode($token, $key, ['HS256']);
    if(!isset($jwt->id)) {
      return null;
    }

    return static::findOne($jwt->id);
  }

  /**
   * @return int|string current user ID
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @return string current user auth key
   */
  public function getAuthKey() {
    $key = Yii::$app->params['jwt.key'];
    $token = [
      "id" => $this->id,
      "pass" => $this->clave
    ];

    $jwt = JWT::encode($token, $key);
    return $jwt;
  }

  /**
   * @param string $authKey
   * @return bool if auth key is valid for current user
   */
  public function validateAuthKey($authKey) {
    $key = Yii::$app->params['jwt.key'];
    $jwt = JWT::decode($authKey, $key);
    if(!isset($jwt["id"])) {
      return false;
    }

    return $jwt["id"] == $this->id;
  }

}