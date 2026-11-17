<?php

namespace eDesarrollos\auditoria;

use Ramsey\Uuid\Uuid;
use Yii;
use yii\base\Component;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\db\JsonExpression;

class BitacoraCambio extends Component {

  public $tabla = 'BitacoraCambio';
  public $atributosOmitidos = ['creado', 'modificado', 'eliminado'];
  public $atributosSensibles = ['clave', 'contrasena', 'password', 'token', 'refreshToken', 'totpSemilla'];

  public function registrarGuardado(ActiveRecord $modelo, bool $insertar, array $atributosAnteriores): void {
    if (!$this->debeRegistrar($modelo)) {
      return;
    }

    $antes = [];
    $despues = [];
    if ($insertar) {
      foreach ($modelo->getAttributes() as $atributo => $valor) {
        if ($this->debeIncluirAtributo($atributo)) {
          $despues[$atributo] = $this->sanearValor($atributo, $valor);
        }
      }
    } else {
      foreach ($atributosAnteriores as $atributo => $valorAnterior) {
        if ($this->debeIncluirAtributo($atributo)) {
          $antes[$atributo] = $this->sanearValor($atributo, $valorAnterior);
          $despues[$atributo] = $this->sanearValor($atributo, $modelo->$atributo);
        }
      }
    }

    if (empty($antes) && empty($despues)) {
      return;
    }

    $this->registrar($modelo, $insertar ? 'crear' : 'actualizar', $antes, $despues);
  }

  public function registrarEliminacion(ActiveRecord $modelo): void {
    if (!$this->debeRegistrar($modelo)) {
      return;
    }

    $antes = [];
    foreach ($modelo->getAttributes() as $atributo => $valor) {
      if ($this->debeIncluirAtributo($atributo)) {
        $antes[$atributo] = $this->sanearValor($atributo, $valor);
      }
    }
    $this->registrar($modelo, 'eliminar', $antes, []);
  }

  private function registrar(ActiveRecord $modelo, string $accion, array $antes, array $despues): void {
    try {
      $usuario = $this->obtenerUsuario();
      $request = Yii::$app instanceof \yii\web\Application ? Yii::$app->request : null;
      $llaves = $modelo->getPrimaryKey(true);

      Yii::$app->db->createCommand()->insert($this->tabla, [
        'id' => Uuid::uuid4()->toString(),
        'idUsuario' => $usuario ? $usuario->getId() : null,
        'tabla' => $modelo::tableName(),
        'idRegistro' => http_build_query($llaves, '', '&'),
        'accion' => $accion,
        'antes' => new JsonExpression($antes),
        'despues' => new JsonExpression($despues),
        'ruta' => $request ? $request->getUrl() : null,
        'fecha' => new Expression('now()'),
      ])->execute();
    } catch (\Throwable $error) {
      Yii::warning('No fue posible registrar la bitácora de cambios: ' . $error->getMessage(), __METHOD__);
    }
  }

  private function debeRegistrar(ActiveRecord $modelo): bool {
    return !method_exists($modelo, 'bitacoraHabilitada') || $modelo::bitacoraHabilitada();
  }

  private function debeIncluirAtributo(string $atributo): bool {
    return !in_array($atributo, $this->atributosOmitidos, true);
  }

  private function sanearValor(string $atributo, $valor) {
    if (in_array($atributo, $this->atributosSensibles, true)) {
      return '[oculto]';
    }

    return $valor;
  }

  private function obtenerUsuario() {
    if (!(Yii::$app instanceof \yii\web\Application) || !Yii::$app->has('user')) {
      return null;
    }

    return Yii::$app->user->identity;
  }
}
