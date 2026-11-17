<?php

namespace eDesarrollos\rest;

use eDesarrollos\data\Respuesta;
use eDesarrollos\importacion\ImportadorSpreadsheet;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\web\Request;
use yii\web\Response;
use yii\rest\Controller;
use yii\filters\ContentNegotiator;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\UploadedFile;

class JsonController extends Controller {

  const FORMATO_HTML = 'html';
  const FORMATO_JSON = 'json';
  const FORMATO_SQL = 'sql';
  const FORMATO_XML = 'xml';
  const FORMATO_CSV = 'csv';
  const FORMATO_XLSX = 'xlsx';
  const FORMATO_PDF = 'pdf';
  const FORMATO_DOCX = 'docx';
  const FORMATO_BLOB = 'blob';

  /** @var \yii\web\Application */
  public $app = null;
  /** @var Request */
  public $req = null;
  /** @var Response */
  public $res = null;

  /** @var ActiveQuery */
  public $queryInicial = null;
  /** @var string */
  public $modelClass = null;
  /** @var string */
  public $modeloID = 'id';

  /** @var string */
  public $nombreSingular = 'Registro';
  /** @var string */
  public $nombrePlural = 'Registros';

  /** @var int */
  public $limite = null;
  /** @var int */
  public $pagina = null;
  /** @var string */
  public $ordenar = null;
  /** @var string */
  public $formato = self::FORMATO_JSON;
  /** @var bool */
  public $todo = false;

  /** @var string */
  public $serializer = 'eDesarrollos\rest\Serializer';

  /** @var array */
  public $accionesPermitidas = ["index"];

  /** Habilitar explícitamente en cada controlador que admita importaciones. */
  public $importacionHabilitada = false;
  public $duracionVistaPreviaImportacion = 900;

  public static function mostrarEnOpenapi(): bool {
    return true;
  }

  public static function accionesOcultasOpenapi(): array {
    return [];
  }

  public static function openapiControlador(): array {
    return [];
  }

  public static function openapiAcciones(): array {
    return [];
  }

  public function behaviors() {
    $behavior = parent::behaviors();
    $behavior['contentNegotiator'] =  [
      'class' => ContentNegotiator::class,
      'formats' => [
        'application/json' => Response::FORMAT_JSON,
        'application/xml' => Response::FORMAT_XML,
      ],
    ];
    $behavior["authenticator"]["except"] = ['options'];
    return $behavior;
  }

  public function beforeAction($action) {
    parent::beforeAction($action);
    $this->app = Yii::$app;
    $this->req = $this->app->getRequest();
    $this->res = $this->app->getResponse();
    $this->res->on(Response::EVENT_BEFORE_SEND, [$this, 'validarFormatoRespuesta']);
    $headers = $this->res->getHeaders();

    $origin = $this->req->headers->get('Origin');
    $headers->set('Access-Control-Allow-Methods', 'POST, GET, DELETE, PUT, OPTIONS');
    $headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With, Content-Disposition, Content-Length');
    $headers->set('Access-Control-Expose-Headers', 'Content-Disposition, Content-Length, Content-Type');
    $headers->set('Access-Control-Request-Method', 'POST, GET, DELETE, PUT, OPTIONS');
    $headers->set('Access-Control-Allow-Credentials', 'true');
    if ($origin) {
      $headers->set('Access-Control-Allow-Origin', $origin);
    } else {
      $headers->set('Access-Control-Allow-Origin', '*');
    }
    $headers->set('Access-Control-Max-Age', 86400);
    if ($this->req->isOptions) {
      Yii::$app->end();
    }
    if(!in_array($action->id, $this->accionesPermitidas)) {
      throw new ForbiddenHttpException("Acción no permitida");
    }

    $this->formato = $this->req->get("formato", self::FORMATO_JSON);
    if ($this->req->isGet) {
      $this->limite = max(1, intval($this->req->get("limite", 20)));
      $this->pagina = max(1, intval($this->req->get("pagina", 1)));
      $this->ordenar = $this->req->get("ordenar", "");
      $this->todo = $this->debeExportarTodo();
    }
    if ($this->modelClass !== null) {
      $model = new $this->modelClass;
      $tableName = $this->modelClass::tableName();
      $this->nombreSingular = $this->modelClass::nombreSingular() ?? 'Registro';
      $this->nombrePlural = $this->modelClass::nombrePlural() ?? 'Registros';
      $this->queryInicial = $this->modelClass::find();
      if ($model->hasProperty('eliminado')) {
        $this->queryInicial
          ->where(["{{{$tableName}}}.[[eliminado]]" => null]);
      }
    }
    $formato = $this->formato;
    if ($formato === self::FORMATO_JSON) {
      $this->res->format = Response::FORMAT_JSON;
    } elseif ($formato === self::FORMATO_XML) {
      $this->res->format = Response::FORMAT_XML;
    } elseif ($formato === self::FORMATO_HTML) {
      $this->res->format = self::FORMATO_HTML;
      $this->res->formatters[self::FORMATO_HTML] = 'eDesarrollos\formatters\HtmlFormatter';
    } elseif ($formato === self::FORMATO_SQL) {
      $this->res->format = Response::FORMAT_RAW;
    } elseif ($formato === self::FORMATO_CSV) {
      $this->res->format = self::FORMATO_CSV;
      $this->res->formatters[self::FORMATO_CSV] = 'eDesarrollos\formatters\CsvFormatter';
    } elseif ($formato === self::FORMATO_XLSX) {
      $this->res->format = self::FORMATO_XLSX;
      $this->res->formatters[self::FORMATO_XLSX] = 'eDesarrollos\formatters\SpreadsheetFormatter';
    } elseif ($formato === self::FORMATO_PDF) {
      $this->res->format = self::FORMATO_PDF;
      $this->res->formatters[self::FORMATO_PDF] = 'eDesarrollos\formatters\PdfFormatter';
    } elseif ($formato === self::FORMATO_DOCX) {
      $this->res->format = self::FORMATO_DOCX;
      $this->res->formatters[self::FORMATO_DOCX] = 'eDesarrollos\formatters\DocxFormatter';
    } elseif ($formato === self::FORMATO_BLOB) {
      $this->res->format = Response::FORMAT_RAW;
    }
    return true;
  }

  public function validarFormatoRespuesta($event): void {
    /** @var Response $respuesta */
    $respuesta = $event->sender;

    if ($respuesta->isClientError || $respuesta->isServerError || $this->esFormatoRespuestaCompatible($respuesta)) {
      return;
    }

    $formatoActual = $respuesta->format;
    $respuesta->setStatusCode(406, 'Formato de respuesta no corresponde');
    $respuesta->format = Response::FORMAT_JSON;
    $respuesta->data = [
      'mensaje' => "El formato solicitado '.{$this->formato}' no corresponde al formato de respuesta '{$formatoActual}'.",
      'errores' => [
        'formato' => 'La respuesta debe coincidir con el formato solicitado en la URL.',
      ],
    ];
    $respuesta->content = null;
    $respuesta->stream = null;

    $headers = $respuesta->getHeaders();
    $headers->remove('Content-Disposition');
    $headers->remove('Content-Length');
    $headers->remove('Content-Range');
    $headers->remove('Content-Type');
  }

  protected function esFormatoRespuestaCompatible(Response $respuesta): bool {
    if ($this->formato === self::FORMATO_BLOB) {
      return $respuesta->format === Response::FORMAT_RAW;
    }

    $formatos = [
      self::FORMATO_JSON => Response::FORMAT_JSON,
      self::FORMATO_XML => Response::FORMAT_XML,
      self::FORMATO_HTML => self::FORMATO_HTML,
      self::FORMATO_SQL => Response::FORMAT_RAW,
      self::FORMATO_CSV => self::FORMATO_CSV,
      self::FORMATO_XLSX => self::FORMATO_XLSX,
      self::FORMATO_PDF => self::FORMATO_PDF,
      self::FORMATO_DOCX => self::FORMATO_DOCX,
    ];

    return ($formatos[$this->formato] ?? null) === $respuesta->format
      && !$respuesta->getHeaders()->has('Content-Disposition');
  }

  public function actionOptions() {
    $headers = $this->res->getHeaders();

    // TODO: Agregar encabezados personalizados

    return "";
  }

  public function actionIndex() {
    if ($this->modelClass === null) {
      return (new Respuesta())
        ->esError()
        ->mensaje("Debe especificar un modelo");
    }

    $query = $this->queryInicial;

    $this->buscador($query, $this->req);

    return new Respuesta($query, $this->limite, $this->pagina, $this->ordenar, $this->todo);
  }

  public function actionPost() {
    return $this->guardarModelo($this->obtenerAccionActual('post'));
  }

  public function actionPut() {
    return $this->actionPost();
  }

  protected function guardarModelo(string $accion) {
    $id = trim($this->req->getBodyParam("id", ""));
    $modelo = null;

    if ($id !== "") {
      $modelo = $this->modelClass::findOne($id);
    }
    if ($modelo === null) {
      $modelo = new $this->modelClass();
      $modelo->uuid();
      $modelo->creado = new Expression('now()');
    } else {
      $modelo->modificado = new Expression('now()');
    }

    $this->prepararModelo($modelo, $accion);
    $modelo->load($this->req->getBodyParams(), '');
    if (!$modelo->save()) {
      return (new Respuesta($modelo))
        ->mensaje("Hubo un problema al guardar el {$this->nombreSingular}");
    }

    $modelo->refresh();
    return (new Respuesta($modelo))
      ->mensaje("{$this->nombreSingular} guardado");
  }

  public function actionDelete() {
    $id = trim($this->req->getBodyParam("id", ""));
    $modelo = null;

    if ($id !== "") {
      $modelo = $this->modelClass::findOne([
        "id" => $id,
        "eliminado" => null
      ]);
    }
    if ($modelo === null) {
      return (new Respuesta())
        ->esError()
        ->mensaje("{$this->nombreSingular} no encontrado");
    }
    $modelo->eliminado = new Expression('now()');
    if (!$modelo->save()) {
      return (new Respuesta($modelo))
        ->mensaje("No se pudo eliminar el {$this->nombreSingular}");
    }

    return (new Respuesta())
      ->mensaje("{$this->nombreSingular} eliminado");
  }

  public function actionImportarVistaPrevia() {
    $this->validarImportacionHabilitada();
    $archivo = UploadedFile::getInstanceByName('archivo');
    if ($archivo === null || strtolower($archivo->extension) !== 'xlsx') {
      return (new Respuesta())->esError()->mensaje('Seleccione un archivo XLSX válido.');
    }

    try {
      $importador = new ImportadorSpreadsheet();
      $lectura = $importador->leer($archivo->tempName, $this->modelClass);
      $revision = $importador->revisar($lectura['filas'], $this->modelClass, $this->preparadorImportacion());
      $token = bin2hex(random_bytes(24));
      $this->app->cache->set($this->llaveVistaPreviaImportacion($token), [
        'usuario' => $this->usuarioImportacion(),
        'filas' => $lectura['filas'],
      ], $this->duracionVistaPreviaImportacion);

      return (new Respuesta())->detalle([
        'token' => $token,
        'encabezados' => $lectura['encabezados'],
        'vistaPrevia' => $revision,
      ])->mensaje('Vista previa generada.');
    } catch (\Throwable $error) {
      return (new Respuesta())->esError()->mensaje($error->getMessage());
    }
  }

  public function actionImportarAplicar() {
    $this->validarImportacionHabilitada();
    $token = trim((string)$this->req->getBodyParam('token', ''));
    $vistaPrevia = $this->app->cache->get($this->llaveVistaPreviaImportacion($token));
    if (!is_array($vistaPrevia) || ($vistaPrevia['usuario'] ?? null) !== $this->usuarioImportacion()) {
      return (new Respuesta())->esError()->mensaje('La vista previa no existe, expiró o no pertenece al usuario actual.');
    }

    $transaccion = $this->app->db->beginTransaction();
    try {
      $importador = new ImportadorSpreadsheet();
      $resultado = $importador->guardar($vistaPrevia['filas'], $this->modelClass, $this->preparadorImportacion());
      if ($resultado['resumen']['errores'] > 0) {
        $transaccion->rollBack();
        return (new Respuesta())->esError()->detalle(['vistaPrevia' => $resultado])
          ->mensaje('La importación cambió desde la vista previa; revísela antes de aplicar.');
      }
      $transaccion->commit();
      $this->app->cache->delete($this->llaveVistaPreviaImportacion($token));

      return (new Respuesta())->detalle(['vistaPrevia' => $resultado])
        ->mensaje('Importación aplicada correctamente.');
    } catch (\Throwable $error) {
      $transaccion->rollBack();
      return (new Respuesta())->esError()->mensaje($error->getMessage());
    }
  }

  public function buscador(ActiveQuery &$query, Request $request) {
    $id = $request->get($this->modeloID, "");

    if ($id !== "") {
      $query->andWhere([$this->modeloID => $id]);
    }
  }

  /**
   * Prepara un modelo antes de cargar los datos de una acción.
   * Los controladores especializados pueden usarlo para aplicar escenarios.
   */
  protected function prepararModelo($modelo, string $accion): void {
  }

  protected function prepararModeloImportacion($modelo, array $fila): void {
    $this->prepararModelo($modelo, $modelo->getIsNewRecord() ? 'post' : 'put');
    if ($modelo->getIsNewRecord()) {
      $llaves = $modelo::primaryKey();
      if (count($llaves) === 1 && empty($modelo->{$llaves[0]})) {
        $modelo->uuid();
      }
      if ($modelo->hasProperty('creado')) {
        $modelo->creado = new Expression('now()');
      }
    } elseif ($modelo->hasProperty('modificado')) {
      $modelo->modificado = new Expression('now()');
    }
  }

  protected function preparadorImportacion(): \Closure {
    return function ($modelo, array $fila): void {
      $this->prepararModeloImportacion($modelo, $fila);
    };
  }

  protected function validarImportacionHabilitada(): void {
    if (!$this->importacionHabilitada || $this->modelClass === null) {
      throw new ForbiddenHttpException('La importación no está habilitada para este catálogo.');
    }
  }

  protected function llaveVistaPreviaImportacion(string $token): string {
    return 'importacion:' . str_replace('\\', '.', static::class) . ':' . $token;
  }

  protected function usuarioImportacion(): string {
    $usuario = $this->app->getUser()->getIdentity();
    return $usuario === null ? '' : (string)$usuario->getId();
  }

  protected function obtenerAccionActual(string $accionPredeterminada): string {
    return $accionPredeterminada;
  }

  protected function debeExportarTodo(): bool {
    if (!$this->esFormatoDocumento($this->formato)) {
      return false;
    }

    return filter_var($this->req->get('todo', false), FILTER_VALIDATE_BOOLEAN);
  }

  protected function esFormatoDocumento(string $formato): bool {
    return in_array($formato, [
      self::FORMATO_CSV,
      self::FORMATO_XLSX,
      self::FORMATO_PDF,
      self::FORMATO_DOCX,
    ], true);
  }
}
