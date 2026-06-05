<?php

namespace eDesarrollos\rest;

use eDesarrollos\data\Respuesta;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\web\Request;
use yii\web\Response;
use yii\rest\Controller;
use yii\filters\ContentNegotiator;
use Yii;

class JsonController extends Controller {

  const FORMATO_HTML = 'html';
  const FORMATO_JSON = 'json';
  const FORMATO_SQL = 'sql';
  const FORMATO_XML = 'xml';
  const FORMATO_CSV = 'csv';
  const FORMATO_XLSX = 'xlsx';
  const FORMATO_PDF = 'pdf';
  const FORMATO_DOCX = 'docx';

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

  public static function mostrarEnOpenapi(): bool {
    return true;
  }

  public static function accionesOcultasOpenapi(): array {
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
    $headers = $this->res->getHeaders();

    $origin = $this->req->headers->get('Origin');
    $headers->set('Access-Control-Allow-Methods', 'POST, GET, DELETE, PUT, OPTIONS');
    $headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With, Content-Disposition, Content-Length');
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
    }
    return true;
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

    $modelo->load($this->req->getBodyParams(), '');
    if (!$modelo->save()) {
      return (new Respuesta($modelo))
        ->mensaje("Hubo un problema al guardar el {$this->nombreSingular}");
    }

    $modelo->refresh();
    return (new Respuesta($modelo))
      ->mensaje("{$this->nombreSingular} guardado");
  }

  public function actionPut() {
    return $this->actionPost();
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

  public function buscador(ActiveQuery &$query, Request $request) {
    $id = $request->get($this->modeloID, "");

    if ($id !== "") {
      $query->andWhere([$this->modeloID => $id]);
    }
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
