<?php

namespace eDesarrollos\mail\models;

use Yii;

/**
 * This is the model class for table "NotificacionCorreoAdjunto".
 *
 * @property int $id
 * @property int|null $idNotificacionCorreo
 * @property string|null $ruta
 *
 * @property NotificacionCorreo $notificacionCorreo
 */
class NotificacionCorreoAdjunto extends \yii\db\ActiveRecord {

  /**
   * {@inheritdoc}
   */
  public static function tableName() {
    return 'NotificacionCorreoAdjunto';
  }

  /**
   * {@inheritdoc}
   */
  public function rules() {
    return [
      [['idNotificacionCorreo'], 'default', 'value' => null],
      [['idNotificacionCorreo'], 'integer'],
      [['ruta'], 'string', 'max' => 255],
      [['idNotificacionCorreo'], 'exist', 'skipOnError' => true, 'targetClass' => NotificacionCorreo::class, 'targetAttribute' => ['idNotificacionCorreo' => 'id']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function attributeLabels() {
    return [
      'id' => 'ID',
      'idNotificacionCorreo' => 'Id Notificacion Correo',
      'ruta' => 'Ruta',
    ];
  }

  /**
   * Gets query for [[IdNotificacionCorreo0]].
   *
   * @return \yii\db\ActiveQuery
   */
  public function getNotificacionCorreo() {
    return $this->hasOne(NotificacionCorreo::class, ['id' => 'idNotificacionCorreo']);
  }
}
