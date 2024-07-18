<?php

namespace eDesarrollos\web;

use Mpdf\Mpdf;
use Yii;

class PdfController extends \yii\web\Controller {

  /**
   * Si es verdadero imprime el contenido en el web
   * @var boolean $html
   */
  public $html = false;

  /**
   * Mostrar vista previa del pdf o descargar
   * true = descargar
   * @var boolean $descargar
   */
  public $descargar = false;

  /**
   * Configuración para la librería mpdf
   * @var array $configuracion
   */
  public $configuracion = [
    "format" => "letter",
    "default_font" => "Roboto",
  ];

  /**
   * Texto para la marca de agua
   * @var string $marcaDeAguaTexto
   */
  public $marcaDeAguaTexto = "";

  /**
   * Habilitar la marca de agua
   * @var boolean $html
   */
  public $marcaDeAgua = false;

  /**
   * Encoger las tablas para que quepan
   * @var int $encogerTablas
   */
  public $encogerTablas = 0;

  /**
   * Mantener proporciones de tabla
   * @var boolean $mantenerProporcionTabla
   */
  public $mantenerProporcionTabla = true;

  /**
   * Nombre del archivo al descargar
   * @var string $nombreArchivo
   */
  public $nombreArchivo = "";

  /**
   * Estilos para el pdf
   * @var string $hojaDeEstilo
   */
  public $hojaDeEstilo = "";

  /**
   * header para el pdf
   * @var string $header
   */
  public $header;

  /**
   * @var \yii\web\Request $req
   */
  public $req;

  /**
   * @var \yii\web\Response $res
   */
  public $res;

  public function beforeAction($action) {
    parent::beforeAction($action);

    $this->req = \Yii::$app->getRequest();
    $this->res = \Yii::$app->getResponse();
    $this->html = intval($this->req->get("html", 0)) === 1;
    $this->descargar = intval($this->req->get("descargar", "")) === 1;

    if ($this->html) {
      $this->res->format = \yii\web\Response::FORMAT_HTML;
    }

    return true;
  }

  public function exportarPdf($contenido) {
    try {
      $mpdf = new Mpdf($this->configuracion);
      if (!empty($this->header)) {
        $mpdf->SetHTMLHeader($this->header);
      }
      $mpdf->WriteHTML($this->hojaDeEstilo, \Mpdf\HTMLParserMode::HEADER_CSS);
      $mpdf->SetWatermarkText($this->marcaDeAguaTexto);
      $mpdf->watermark_font = 'DejaVuSansCondensed';
      $mpdf->showWatermarkText = $this->marcaDeAgua;
      $mpdf->watermarkTextAlpha = 0.30;
      $mpdf->shrink_tables_to_fit = $this->encogerTablas;
      $mpdf->keep_table_proportions = $this->mantenerProporcionTabla;
      $mpdf->SetTitle($this->nombreArchivo);
      $mpdf->SetDisplayMode('default');
      $mpdf->SetFooter('Pag. {PAGENO} de {nbpg}');
      $mpdf->showImageErrors = false;
      $mpdf->useSubstitutions = false;
      $mpdf->simpleTables = false;
      $mpdf->WriteHTML($contenido, \Mpdf\HTMLParserMode::HTML_BODY);
      $dest = $this->descargar ? "D" : "I";
      if (strpos($this->nombreArchivo, '.pdf') === false) {
        $this->nombreArchivo .= ".pdf";
      }
      header('Access-Control-Allow-Origin: *');
      header('Access-Control-Expose-Headers: *');
      $mpdf->Output($this->nombreArchivo, $dest);
    } catch (\Exception $exception) {
      throw $exception;
    }
    \Yii::$app->end();
  }

  public function afterAction($action, $result) {
    if (!$this->html) {
      $result = str_replace('disabled="disabled"', '', $result);
      return $this->exportarPdf($result);
    }
    $basePath = Yii::getAlias("@app");
    $file = "{$basePath}/web/css/pdf.css";
    if(is_file($file)) {
      $this->hojaDeEstilo = file_get_contents($file);
    }
    $this->marcaDeAgua = intval($this->req->get("wm", 1)) === 1;
    $watermark = "background-image: url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' version='1.1' " .
      "height='100px' width='100px'><text transform='translate(20, 100) rotate(-45)' fill='rgb(210,210,210)' " .
      "font-size='18'>{$this->marcaDeAguaTexto}</text></svg>\");";
    if (!$this->marcaDeAgua) {
      $watermark = "";
    }
    $fondo = ".fondo-privado { background-color: rgb(141,216,169,0.7) !important; }";
    $result = str_replace("<pagebreak>", "<br>", $result);
    $result = "<style type=\"text/css\">{$this->hojaDeEstilo}\nbody{{$watermark}}\n{$fondo}</style>{$result}";
    return $result;
  }

}