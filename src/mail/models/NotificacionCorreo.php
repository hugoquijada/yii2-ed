<?php

namespace eDesarrollos\mail\models;

use Yii;

/**
 * This is the model class for table "NotificacionCorreo".
 *
 * @property int $id
 * @property string $receptor
 * @property string $asunto
 * @property string $cuerpo
 * @property string|null $estatus
 * @property string|null $detalle
 * @property int $prioridad
 * @property string|null $enviado
 * @property string|null $creado
 * @property string|null $modificado
 * @property string|null $eliminado
 *
 * @property NotificacionCorreoAdjunto[] $adjuntos
 */
class NotificacionCorreo extends \yii\db\ActiveRecord {

  const ESTATUS_NUEVO = "Nuevo";
  const ESTATUS_PROCESO = "Proceso";
  const ESTATUS_ENVIADO = "Enviado";
  const ESTATUS_ERROR = "Error";

  const PRIORIDAD_1 = 1;
  const PRIORIDAD_2 = 2;
  const PRIORIDAD_3 = 3;

  /**
   * {@inheritdoc}
   */
  public static function tableName() {
    return 'NotificacionCorreo';
  }

  /**
   * {@inheritdoc}
   */
  public function rules() {
    return [
      [['receptor', 'asunto', 'cuerpo'], 'required'],
      [['receptor', 'enviado', 'creado', 'modificado', 'eliminado'], 'safe'],
      [['prioridad'], 'integer'],
      [['cuerpo', 'detalle'], 'string'],
      [['asunto'], 'string', 'max' => 255],
      [['estatus'], 'string', 'max' => 20],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function attributeLabels() {
    return [
      'id' => 'ID',
      'receptor' => 'Receptor',
      'asunto' => 'Asunto',
      'cuerpo' => 'Cuerpo',
      'estatus' => 'Estatus',
      'detalle' => 'Detalle',
      'enviado' => 'Enviado',
      'creado' => 'Creado',
      'modificado' => 'Modificado',
      'eliminado' => 'Eliminado',
    ];
  }

  /**
   * Gets query for [[NotificacionCorreoAdjuntos]].
   *
   * @return \yii\db\ActiveQuery
   */
  public function getAdjuntos() {
    return $this->hasMany(NotificacionCorreoAdjunto::class, ['idNotificacionCorreo' => 'id']);
  }

  private function validarCampos($params) {
    if(isset($params["prioridad"]) && !in_array($params["prioridad"], [self::PRIORIDAD_1, self::PRIORIDAD_2, self::PRIORIDAD_3])) {
      $this->addError("prioridad", "La prioridad no es válida");
      return false;
    } elseif(!isset($params["asunto"])) {
      $this->addError("estatus", "El Asunto es obligatorio");
      return false;
    } elseif(trim($params["asunto"]) === "") {
      $this->addError("estatus", "El Asunto no puede estar vacío");
      return false;
    }
    if(!isset($params["cuerpo"])) {
      $this->addError("estatus", "El Cuerpo es obligatorio");
      return false;
    } elseif(trim($params["cuerpo"]) === "") {
      $this->addError("estatus", "El Cuerpo no puede estar vacío");
      return false;
    }
    if(!isset($params["receptores"])) {
      $this->addError("estatus", "El Receptor es obligatorio");
      return false;
    } elseif(!is_array($params["receptores"]) || empty($params["receptores"])) {
      $this->addError("estatus", "El Receptor debe ser un arreglo");
      return false;
    }

    if(isset($params["adjuntos"]) && !is_array($params["adjuntos"])) {
      $this->addError("estatus", "Los Adjuntos debe ser un arreglo");
    }

    return true;
  }

  /**
   * Enviar un correo con muchos receptores
   */
  public static function enviar($params) {
    $modelo = self::crear();
    if(!$modelo->validarCampos($params)) {
      return $modelo;
    }

    $modelo->setAsunto($params["asunto"])
      ->setCuerpo($params["cuerpo"])
      ->setReceptor($params["receptor"]);

    if(!$modelo->save()) {
      return $modelo;
    }

    if(isset($params["adjuntos"])) {
      $modelo->setAdjuntos($params["adjuntos"]);
    }

    $modelo->refresh();
    return $modelo;
  }

  /**
   * Por cada receptor genera un registro de notificación
   * 
   * @param $params Arreglo con los parámetros para enviar un correo
   * @return null|static Regresa una instancia del modelo o null en caso de error
   */
  public static function enviarMultiple($params) {
    $modelo = self::crear();
    if(!$modelo->validarCampos($params)) {
      return $modelo;
    }
    $prioridad = self::PRIORIDAD_3;
    if(isset($params["prioridad"])) {
      $prioridad = $params["prioridad"];
    }

    try {
      $modelo = null;
      foreach($params["receptores"] as $indice => $valor) {
        $receptor = [];
        if(is_numeric($indice)) {
          # No tiene nombre de la persona a la que se le envía
          $receptor[] = $valor;
        } else {
          # $indice es equivalente al correo y $valor al nombre
          $receptor[$indice] = $valor;
        }
  
        $modelo = (self::crear($prioridad))
          ->setAsunto($params["asunto"])
          ->setCuerpo($params["cuerpo"])
          ->setReceptor($receptor);
  
        if(!$modelo->save()) {
          return $modelo;
        }
  
        if(isset($params["adjuntos"])) {
          $modelo->setAdjuntos($params["adjuntos"]);
        }
      }
  
      return $modelo;
    } catch(\Exception $e) {
      # Revisar el contenido de la variable $e si el resultado es nulo
      return null;
    }
  }

  public static function crear($prioridad = self::PRIORIDAD_3) {
    $modelo = new self();
    $modelo->creado = new \yii\db\Expression('now()');
    $modelo->estatus = self::ESTATUS_NUEVO;
    $modelo->prioridad = $prioridad;
    return $modelo;
  }

  public function setAsunto($asunto = "") {
    $this->asunto = $asunto;
    return $this;
  }

  public function setCuerpo($cuerpo = "") {
    $this->cuerpo = $cuerpo;
    return $this;
  }

  public function setReceptor($receptor = []) {
    $this->receptor = $receptor;
    return $this;
  }

  public function setAdjuntos($adjuntos = []) {
    if($this->hasErrors()) {
      return;
    }
    if(!is_array($adjuntos) || empty($adjuntos)) {
      return;
    }

    $instancias = [];
    foreach($adjuntos as $adjunto) {
      $n = new NotificacionCorreoAdjunto();
      $n->idNotificacionCorreo = $this->id;
      $n->ruta = $adjunto;
      if(!$n->save()) {
        // Agregar el error a un log
      }
      $instancias[] = $n;
    }

    return $instancias;
  }
}
