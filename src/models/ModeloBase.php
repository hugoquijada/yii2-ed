<?php

namespace eDesarrollos\models;

use hqsoft\reportkit\document\CellStyle;
use hqsoft\reportkit\document\Document;
use hqsoft\reportkit\document\DocumentConfig;
use hqsoft\reportkit\document\Row;
use Ramsey\Uuid\Uuid;
use Yii;
use yii\helpers\ArrayHelper;

class ModeloBase extends \yii\db\ActiveRecord {
  public function load($data, $nombreFormulario = '') {
    return parent::load($data, $nombreFormulario);
  }

  public static function nombreSingular() {
    $nombre = new \ReflectionClass(static::class);
    $nombre = $nombre->getShortName();
    return $nombre;
  }

  public static function nombrePlural() {
    $nombre = new \ReflectionClass(static::class);
    $nombre = $nombre->getShortName() . 's';
    return $nombre;
  }

  public function uuid() {
    $pk = static::primaryKey();
    if (is_array($pk) && count($pk) > 1) {
      return null;
    }
    $pk = $pk[0];
    do {
      $uuid = (Uuid::uuid4())
        ->toString();

      $modelo = static::find()
        ->andWhere([$pk => $uuid]);
    } while ($modelo->exists());
    $this->{$pk} = $uuid;
    return $uuid;
  }

  public function validarUnico($atributo, $parametros) {
    $query = static::find()
      ->andWhere([$atributo => $this->{$atributo}]);

    if ($this->hasProperty("eliminado")) {
      $query->andWhere(["eliminado" => null]);
    }

    if (!$this->isNewRecord) {
      $llaves = $this->primaryKey();
      foreach ($llaves as $llave) {
        $query->andWhere(["!=", $llave, $this->{$llave}]);
      }
    }

    $existe = $query->exists();
    if ($existe) {
      $this->addError($atributo, "La {$atributo} ya ha sido utilizada.");
    }
  }

  public static function tituloReporte(): string {
    return static::nombrePlural();
  }

  public static function mostrarEnOpenapi(): bool {
    return true;
  }

  public static function configuracionReporte(): array {
    if (Yii::$app === null) {
      return [];
    }

    return Yii::$app->params['exportacion'] ?? [];
  }

  public static function columnasReporte(): array {
    $modelo = new static();
    $columnas = [];
    foreach ($modelo->fields() as $key => $value) {
      $atributo = is_string($key) ? $key : $value;
      if (!is_string($atributo)) {
        continue;
      }
      $columnas[$atributo] = $modelo->getAttributeLabel($atributo);
    }

    return $columnas;
  }

  public function filaReporte(): array {
    return $this->toArray(array_keys(static::columnasReporte()));
  }

  public static function documentoReporte(array $registros, string $tipo = Document::TYPE_SPREADSHEET): Document {
    $columnas = static::columnasReporte();
    if (empty($columnas)) {
      return new Document();
    }

    $totalColumnas = count($columnas);
    $maxColumnas = $totalColumnas > 24 ? $totalColumnas : 24;
    $spans = static::obtenerSpansReporte($totalColumnas, $maxColumnas);
    $config = static::obtenerConfiguracionReporte($tipo);
    $documento = Document::create(new DocumentConfig($maxColumnas));

    if ($tipo !== Document::TYPE_CSV) {
      $logo = $config['logo'] ?? [];
      $logoPath = static::resolverRutaLogoReporte($logo['ruta'] ?? null);
      if (!empty($logo['mostrar']) && $logoPath !== null) {
        $logoSpan = intval($logo['span'] ?? $maxColumnas);
        if ($logoSpan <= 0 || $logoSpan > $maxColumnas) {
          $logoSpan = $maxColumnas;
        }
        $logoAlign = $logo['align'] ?? 'left';
        $logoWidth = isset($logo['width']) ? intval($logo['width']) : null;
        $logoHeight = isset($logo['height']) ? intval($logo['height']) : null;
        $logoStyle = static::resolverEstiloReporte($logo['estilo'] ?? null);

        $documento->row(function (Row $row) use ($logoPath, $logoSpan, $logoAlign, $logoWidth, $logoHeight, $logoStyle) {
          $columna = $row->col($logoSpan)->align($logoAlign)->image($logoPath, $logoWidth, $logoHeight);
          if ($logoStyle !== null) {
            $columna->style($logoStyle);
          }
        });
      }
    }

    $titulo = trim((string)($config['titulo']['texto'] ?? static::tituloReporte()));
    if (!empty($config['titulo']['mostrar']) && $titulo !== '') {
      $estiloTitulo = static::resolverEstiloReporte($config['titulo']['estilo'] ?? CellStyle::title());
      $documento->row(function (Row $row) use ($titulo, $estiloTitulo, $maxColumnas) {
        $columna = $row->col($maxColumnas)->text($titulo);
        if ($estiloTitulo !== null) {
          $columna->style($estiloTitulo);
        }
      });
    }

    if (!empty($config['encabezado']['mostrar'])) {
      $estiloEncabezado = static::resolverEstiloReporte($config['encabezado']['estilo'] ?? CellStyle::header());
      $documento->row(function (Row $row) use ($columnas, $spans, $estiloEncabezado) {
        $indice = 0;
        foreach ($columnas as $etiqueta) {
          $columna = $row->col($spans[$indice] ?? 1)->text((string)$etiqueta);
          if ($estiloEncabezado !== null) {
            $columna->style($estiloEncabezado);
          }
          $indice++;
        }
      });
    }

    foreach (array_values($registros) as $indiceFila => $registro) {
      $fila = $registro instanceof static ? $registro->filaReporte() : (array)$registro;
      $estiloFila = static::resolverEstiloReporte($config['fila']['estilo'] ?? null);
      $estiloFilaPar = static::resolverEstiloReporte($config['fila']['estiloPar'] ?? null);
      $estiloFilaImpar = static::resolverEstiloReporte($config['fila']['estiloImpar'] ?? null);
      $esPar = (($indiceFila + 1) % 2) === 0;

      $documento->row(function (Row $row) use ($columnas, $fila, $spans, $estiloFila, $estiloFilaPar, $estiloFilaImpar, $esPar) {
        $indice = 0;
        foreach ($columnas as $atributo => $_etiqueta) {
          $valor = $fila[$atributo] ?? '';

          if (is_array($valor) || is_object($valor)) {
            $valor = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          }

          $columna = $row->col($spans[$indice] ?? 1)->text((string)$valor);
          if ($estiloFila !== null) {
            $columna->style($estiloFila);
          }
          $estiloAlterno = $esPar ? $estiloFilaPar : $estiloFilaImpar;
          if ($estiloAlterno !== null) {
            $columna->style($estiloAlterno);
          }
          $indice++;
        }
      });
    }

    return $documento;
  }

  protected static function obtenerConfiguracionReporte(string $tipo): array {
    $default = [
      'titulo' => [
        'mostrar' => false,
        'texto' => '',
        'estilo' => CellStyle::title(),
      ],
      'logo' => [
        'mostrar' => false,
        'ruta' => null,
        'width' => null,
        'height' => null,
        'span' => 24,
        'align' => 'left',
        'estilo' => null,
      ],
      'encabezado' => [
        'mostrar' => true,
        'estilo' => CellStyle::header(),
      ],
      'fila' => [
        'estilo' => null,
        'estiloPar' => null,
        'estiloImpar' => null,
      ],
      'formatos' => [],
    ];

    $config = static::configuracionReporte();
    $configFormato = $config['formatos'][$tipo] ?? [];
    unset($config['formatos']);

    return ArrayHelper::merge($default, $config, $configFormato);
  }

  protected static function obtenerSpansReporte(int $totalColumnas, int $maxColumnas): array {
    if ($totalColumnas <= 0) {
      return [];
    }

    if ($totalColumnas >= $maxColumnas) {
      return array_fill(0, $totalColumnas, 1);
    }

    $base = intdiv($maxColumnas, $totalColumnas);
    $restante = $maxColumnas % $totalColumnas;
    $spans = [];
    for ($i = 0; $i < $totalColumnas; $i++) {
      $spans[] = $base + ($i < $restante ? 1 : 0);
    }

    return $spans;
  }

  protected static function resolverEstiloReporte($config): ?CellStyle {
    if ($config === null) {
      return null;
    }

    if ($config instanceof CellStyle) {
      return CellStyle::create()->merge($config);
    }

    if (is_string($config) && method_exists(CellStyle::class, $config)) {
      return CellStyle::$config();
    }

    if (is_array($config)) {
      $estilo = CellStyle::create();
      if (static::esListaArray($config)) {
        foreach ($config as $item) {
          $estiloItem = static::resolverEstiloReporte($item);
          if ($estiloItem !== null) {
            $estilo->merge($estiloItem);
          }
        }
        return $estilo;
      }

      foreach ($config as $metodo => $valor) {
        if ($metodo === 'border') {
          $bordes = static::esListaArray($valor) ? $valor : [$valor];
          foreach ($bordes as $borde) {
            if (is_array($borde)) {
              $estilo->border(
                $borde['side'] ?? 'all',
                $borde['style'] ?? 'thin',
                $borde['color'] ?? '#000000'
              );
            }
          }
          continue;
        }

        if (!method_exists($estilo, $metodo)) {
          continue;
        }

        if (is_array($valor) && static::esListaArray($valor)) {
          $estilo->{$metodo}(...$valor);
        } else {
          $estilo->{$metodo}($valor);
        }
      }

      return $estilo;
    }

    return null;
  }

  protected static function esListaArray($value): bool {
    if (!is_array($value)) {
      return false;
    }

    return array_keys($value) === range(0, count($value) - 1);
  }

  protected static function resolverRutaLogoReporte($ruta): ?string {
    if (empty($ruta) || !is_string($ruta)) {
      return null;
    }

    $path = Yii::getAlias($ruta, false);
    if ($path === false) {
      $path = $ruta;
    }

    if (!is_file($path)) {
      return null;
    }

    return $path;
  }
}
