<?php

namespace eDesarrollos\rest;

use yii\filters\auth\HttpBearerAuth;
use yii\filters\auth\QueryParamAuth;
use yii\web\ForbiddenHttpException;

class AuthController extends JsonController {

  /**
   * @var \eDesarrollos\models\Usuario $usuario
   */
  public $usuario;
  public $permisos = [];

  /**
   * Permisos y escenario opcionales por acción.
   *
   * Una acción ausente en este arreglo sólo requiere autenticación. Cada
   * elemento puede ser un permiso, una lista de permisos requeridos o un
   * arreglo con las llaves permiso y escenario.
   *
   * @var array
   */
  public $permisosAccion = [];

  private $_accionActual;

  public $accionesPermitidas = ["index", "post", "put", "delete", "importar-vista-previa", "importar-aplicar"];
  public $importacionHabilitada = true;

  public function behaviors() {
    $behavior = parent::behaviors();
    $behavior["authenticator"]["authMethods"] = [
      QueryParamAuth::class,
      HttpBearerAuth::class
    ];
    return $behavior;
  }

  public function beforeAction($action) {
    if (!parent::beforeAction($action)) {
      return false;
    }

    $this->usuario = \Yii::$app->getUser()->getIdentity();

    if ($this->usuario === null) {
      throw new \yii\web\UnauthorizedHttpException('No está autorizado para realizar esta acción.');
    }

    if (!empty($this->permisos)) {
      $this->usuario->cargarPermisos($this->permisos);
    }

    $this->_accionActual = $action->id;
    $configuracion = $this->configuracionPermisoAccion($action->id);
    if ($configuracion !== null && isset($configuracion['permiso'])
      && !$this->puede($configuracion['permiso'])) {
      throw new ForbiddenHttpException('No tiene permiso para realizar esta acción.');
    }

    return true;
  }

  /**
   * Indica si el usuario autenticado cuenta con todos los permisos recibidos.
   * Un permiso vacío representa una acción sin restricción adicional.
   *
   * @param string|array|null $permisos
   */
  public function puede($permisos): bool {
    if ($permisos === null || $permisos === '' || $permisos === []) {
      return true;
    }

    foreach ((array) $permisos as $permiso) {
      if (!method_exists($this->usuario, 'tienePermiso')
        || !$this->usuario->tienePermiso($permiso)) {
        return false;
      }
    }

    return true;
  }

  protected function prepararModelo($modelo, string $accion): void {
    parent::prepararModelo($modelo, $accion);

    $configuracion = $this->configuracionPermisoAccion($accion);
    $escenario = $configuracion['escenario'] ?? null;
    if ($escenario !== null) {
      $modelo->setScenario($escenario);
    }
  }

  protected function prepararModeloAccionActual($modelo): void {
    $this->prepararModelo($modelo, $this->_accionActual ?? '');
  }

  protected function obtenerAccionActual(string $accionPredeterminada): string {
    return $this->_accionActual ?? $accionPredeterminada;
  }

  private function configuracionPermisoAccion(string $accion): ?array {
    if (!array_key_exists($accion, $this->permisosAccion)) {
      if (in_array($accion, ['importar-vista-previa', 'importar-aplicar'], true)) {
        $permisos = [];
        foreach (['post', 'put'] as $accionGuardar) {
          $configuracion = $this->configuracionPermisoAccion($accionGuardar);
          if ($configuracion !== null && isset($configuracion['permiso'])) {
            $permisos = array_merge($permisos, (array)$configuracion['permiso']);
          }
        }
        if (!empty($permisos)) {
          return ['permiso' => array_values(array_unique($permisos))];
        }
      }
      return null;
    }

    $configuracion = $this->permisosAccion[$accion];
    if (!is_array($configuracion)) {
      return ['permiso' => $configuracion];
    }

    if (!array_key_exists('permiso', $configuracion)
      && !array_key_exists('escenario', $configuracion)) {
      return ['permiso' => $configuracion];
    }

    return $configuracion;
  }
}
