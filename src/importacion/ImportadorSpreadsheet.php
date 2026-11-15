<?php

namespace eDesarrollos\importacion;

use eDesarrollos\models\ModeloBase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use yii\base\InvalidArgumentException;

class ImportadorSpreadsheet {

  public function leer(string $nombreTemporal, string $modelClass): array {
    if (!is_subclass_of($modelClass, ModeloBase::class)) {
      throw new InvalidArgumentException('El modelo de importación debe extender ModeloBase.');
    }

    $hoja = IOFactory::load($nombreTemporal)->getActiveSheet();
    $filas = $hoja->toArray(null, true, true, false);
    $columnas = $this->columnas($modelClass);
    $encabezado = $this->encontrarEncabezado($filas, $columnas);

    if ($encabezado === null) {
      throw new InvalidArgumentException('No se encontró una fila de encabezados compatible con el reporte.');
    }

    $resultado = [];
    foreach (array_slice($filas, $encabezado['indice'] + 1) as $indice => $fila) {
      $valores = [];
      foreach ($encabezado['mapa'] as $columna => $atributo) {
        $valor = $fila[$columna] ?? null;
        if (is_string($valor)) {
          $valor = trim($valor);
        }
        $valores[$atributo] = $valor;
      }

      if (!$this->filaVacia($valores)) {
        $resultado[] = [
          'numero' => $encabezado['indice'] + $indice + 2,
          'valores' => $valores,
        ];
      }
    }

    if (empty($resultado)) {
      throw new InvalidArgumentException('El archivo no contiene registros para importar.');
    }

    return [
      'encabezados' => array_values($encabezado['mapa']),
      'filas' => $resultado,
    ];
  }

  public function revisar(array $filas, string $modelClass, callable $prepararModelo): array {
    $resultado = [];
    $resumen = ['nuevos' => 0, 'actualizados' => 0, 'sinCambios' => 0, 'errores' => 0];

    foreach ($filas as $fila) {
      $revision = $this->prepararFila($fila, $modelClass, $prepararModelo);
      $resultado[] = $revision;
      $resumen[$revision['estado']]++;
    }

    return ['filas' => $resultado, 'resumen' => $resumen];
  }

  public function guardar(array $filas, string $modelClass, callable $prepararModelo): array {
    $revision = $this->revisar($filas, $modelClass, $prepararModelo);
    if ($revision['resumen']['errores'] > 0) {
      return $revision;
    }

    foreach ($filas as $fila) {
      $modelo = $this->modeloFila($fila['valores'], $modelClass, $prepararModelo);
      if ($modelo['cambios'] === []) {
        continue;
      }
      $modelo['modelo']->save(false);
    }

    return $revision;
  }

  private function prepararFila(array $fila, string $modelClass, callable $prepararModelo): array {
    try {
      $resultado = $this->modeloFila($fila['valores'], $modelClass, $prepararModelo);
      $modelo = $resultado['modelo'];
      $errores = $modelo->validate() ? [] : $modelo->getFirstErrors();
      $estado = !empty($errores) ? 'errores' : ($modelo->getIsNewRecord()
        ? 'nuevos' : ($resultado['cambios'] === [] ? 'sinCambios' : 'actualizados'));

      return [
        'numero' => $fila['numero'],
        'estado' => $estado,
        'cambios' => $resultado['cambios'],
        'errores' => $errores,
      ];
    } catch (\Throwable $error) {
      return [
        'numero' => $fila['numero'],
        'estado' => 'errores',
        'cambios' => [],
        'errores' => ['archivo' => $error->getMessage()],
      ];
    }
  }

  private function modeloFila(array $valores, string $modelClass, callable $prepararModelo): array {
    $modelo = $modelClass::resolverModeloImportacion($valores);
    if (!$modelo instanceof $modelClass) {
      throw new InvalidArgumentException('resolverModeloImportacion debe regresar una instancia del modelo.');
    }

    $prepararModelo($modelo, $valores);
    $permitidos = $this->atributosSeguros($modelo);
    $valores = array_intersect_key($valores, array_flip($permitidos));
    $modelo->load($valores, '');

    $cambios = [];
    foreach (array_keys($valores) as $atributo) {
      if ($modelo->getAttribute($atributo) != $modelo->getOldAttribute($atributo)) {
        $cambios[$atributo] = $modelo->getAttribute($atributo);
      }
    }

    return ['modelo' => $modelo, 'cambios' => $cambios];
  }

  private function columnas(string $modelClass): array {
    $columnas = [];
    foreach ($modelClass::columnasImportacion() as $columna) {
      if (!isset($columna['id'])) {
        continue;
      }
      $columnas[$this->normalizar((string)$columna['id'])] = (string)$columna['id'];
      if (!empty($columna['label'])) {
        $columnas[$this->normalizar((string)$columna['label'])] = (string)$columna['id'];
      }
    }
    return $columnas;
  }

  private function encontrarEncabezado(array $filas, array $columnas): ?array {
    $mejor = null;
    foreach (array_slice($filas, 0, 50) as $indice => $fila) {
      $mapa = [];
      foreach ($fila as $columna => $valor) {
        $normalizado = $this->normalizar((string)$valor);
        if ($normalizado !== '' && isset($columnas[$normalizado])) {
          $mapa[$columna] = $columnas[$normalizado];
        }
      }
      if ($mejor === null || count($mapa) > count($mejor['mapa'])) {
        $mejor = ['indice' => $indice, 'mapa' => $mapa];
      }
    }
    return $mejor !== null && !empty($mejor['mapa']) ? $mejor : null;
  }

  private function atributosSeguros(ModeloBase $modelo): array {
    return array_values(array_filter($modelo->safeAttributes(), function ($atributo) {
      return $atributo !== '' && $atributo[0] !== '!';
    }));
  }

  private function filaVacia(array $valores): bool {
    foreach ($valores as $valor) {
      if ($valor !== null && $valor !== '') {
        return false;
      }
    }
    return true;
  }

  private function normalizar(string $valor): string {
    $valor = trim(mb_strtolower($valor));
    $sinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
    if ($sinAcentos !== false) {
      $valor = $sinAcentos;
    }
    return preg_replace('/[^a-z0-9]+/', '', $valor);
  }
}
