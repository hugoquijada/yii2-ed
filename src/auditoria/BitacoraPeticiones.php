<?php

namespace eDesarrollos\auditoria;

use Ramsey\Uuid\Uuid;
use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\db\Expression;
use yii\web\Response;

class BitacoraPeticiones implements BootstrapInterface {

  public $tabla = 'BitacoraPeticion';
  public $soloErrores = true;
  public $camposSensibles = ['authorization', 'password', 'clave', 'token', 'refreshToken', 'totp', 'codigoTotp'];

  public function bootstrap($app) {
    if (!$app instanceof \yii\web\Application) {
      return;
    }

    $inicio = microtime(true);
    $app->response->on(Response::EVENT_AFTER_SEND, function ($evento) use ($app, $inicio) {
      $this->registrar($app, $evento->sender, $inicio);
    });
  }

  private function registrar(Application $app, Response $respuesta, float $inicio): void {
    if ($this->soloErrores && $respuesta->statusCode < 400) {
      return;
    }

    try {
      $request = $app->request;
      $usuario = $app->has('user') ? $app->user->identity : null;
      $cuerpo = $this->sanear($request->getBodyParams());
      $parametros = $this->sanear($request->getQueryParams());
      $error = is_array($respuesta->data)
        ? ($respuesta->data['mensaje'] ?? $respuesta->data['message'] ?? null)
        : null;

      $app->db->createCommand()->insert($this->tabla, [
        'id' => Uuid::uuid4()->toString(),
        'idUsuario' => $usuario ? $usuario->getId() : null,
        'metodo' => $request->method,
        'ruta' => $request->getUrl(),
        'codigo' => $respuesta->statusCode,
        'ip' => $request->userIP,
        'agente' => $request->userAgent,
        'parametros' => json_encode($parametros, JSON_UNESCAPED_UNICODE),
        'cuerpo' => json_encode($cuerpo, JSON_UNESCAPED_UNICODE),
        'error' => is_scalar($error) ? (string)$error : null,
        'duracionMs' => (int)round((microtime(true) - $inicio) * 1000),
        'fecha' => new Expression('now()'),
      ])->execute();
    } catch (\Throwable $error) {
      Yii::warning('No fue posible registrar la bitácora de peticiones: ' . $error->getMessage(), __METHOD__);
    }
  }

  private function sanear($valor) {
    if (!is_array($valor)) {
      return $valor;
    }

    $resultado = [];
    foreach ($valor as $llave => $item) {
      if (in_array((string)$llave, $this->camposSensibles, true)) {
        $resultado[$llave] = '[oculto]';
      } else {
        $resultado[$llave] = $this->sanear($item);
      }
    }
    return $resultado;
  }
}
