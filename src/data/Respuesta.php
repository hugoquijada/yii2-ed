<?php

namespace eDesarrollos\data;

use eDesarrollos\rest\Serializer;
use yii\data\ActiveDataProvider;

class Respuesta {

  const FORMATO_SQL = 'sql';

  public $cuerpo = [];
  protected $atributosPermitidos = [
    'resultado',
    'mensaje',
    'errores',
    'detalle',
    'paginacion'
  ];

  protected $parametros = [
    "total" => 0,
    "pagina" => 0,
    "limite" => 0,
    "ordenar" => false
  ];

  public function __set($nombre, $valor) {
    if(!in_array($nombre, $this->atributosPermitidos)) {
      return;
    }
    $this->cuerpo[$nombre] = $valor;
  }

  public function __get($nombre) {
    if(isset($this->cuerpo[$nombre])) {
      return $this->cuerpo[$nombre];
    }

    return null;
  }

  public function __construct($modelo = null, $limite = 20, $pagina = 1, $ordenar = false) {
    $this->parametros['limite'] = $limite;
    $this->parametros['pagina'] = $pagina;
    $this->parametros['ordenar'] = $ordenar;
    if($modelo !== null) {
      $this->modelo($modelo);
    }
  }

  public function modelo($modelo) {
    $this->esExitoso();
    if ($modelo instanceof \yii\db\ActiveRecord) {
      if ($modelo->hasErrors()) {
        $this->esError();
        $this->errores = $modelo->getFirstErrors();
      } else {
        $this->detalle($modelo->toArray());
      }
    } elseif ($modelo instanceof \yii\db\ActiveQuery || $modelo instanceof \yii\db\Query) {
      \Yii::$app->getResponse()->setStatusCode(200);
      $req = \Yii::$app->getRequest();
      $sql = trim($req->get("formato", "")) === self::FORMATO_SQL;
      if ($sql) {
        echo $modelo->createCommand()->getRawSql();
        exit(0);
      }
      $limite = intval($this->parametros['limite']);
      $pagina = intval($this->parametros['pagina']);
      $ordenar = $this->parametros['ordenar'];
      $total = $modelo->count();

      if($pagina <= 0) {
        $pagina = 1;
      }

      $offset = 0;
      if (($pagina - 1) >= 0) {
        $offset = $limite * ($pagina - 1);
      }

      if($offset > 0) {
        $modelo->offset($offset);
      }

      $modelo->limit($limite);

      if ($ordenar !== false && ($campo = trim($ordenar)) !== "") {
        $separar = explode(",", $ordenar);
        $ordenamiento = [];
        foreach ($separar as $segmento) {
          $exp = explode("-", trim($segmento));
          $desc = false;
          if (count($exp) > 1) {
            $campo = $exp[0];
            $desc = $exp[1] === 'desc';
          }
          $ordenamiento[$campo] = $desc ? SORT_DESC : SORT_ASC;
        }
        if (!empty($ordenamiento)) {
          $modelo->orderBy($ordenamiento);
        }
      }

      if ($limite > $total || $limite <= 0) {
        $limite = $total;
      }

      $this->paginacion = [
        "total" => (int)$total, # Total de elementos
        "pagina" => $pagina, # Página actual
        "limite" => $limite # Elementos por página
      ];

      $s = new Serializer();
      $this->resultado = $s->serialize(new ActiveDataProvider(["query" => $modelo, "pagination" => false]));
    } elseif(is_array($modelo) && isset($modelo[0])) {
      $total = count($modelo);
      $this->paginacion = [
        "total" => $total,
        "pagina" => 1,
        "limite" => $total
      ];
      $this->resultado = $modelo;
    } elseif(!empty($modelo)) {
      $this->paginacion = [
        "total" => 1,
        "pagina" => 1,
        "limite" => 1
      ];
      $this->resultado = [$modelo];
    } else {
      $this->paginacion = [
        "total" => 0,
        "pagina" => 0,
        "limite" => 0
      ];
      $this->resultado = [];
    }
    return $this;
  }

  public function esExitoso($codigo = 200) {
    \Yii::$app->getResponse()->setStatusCode($codigo);
    return $this;
  }
  
  public function esError($codigo = 400) {
    \Yii::$app->getResponse()->setStatusCode($codigo);
    return $this;
  }

  public function detalle($detalle) {
    $this->detalle = $detalle;
    return $this;
  }

  public function mensaje($mensaje) {
    $this->mensaje = $mensaje;
    return $this;
  }

  public function getParametros() {
    return $this->parametros;
  }
}