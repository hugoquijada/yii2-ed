<?php

namespace eDesarrollos\web;

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\QueryParamAuth;
use yii\filters\Cors;

class ExcelController extends \yii\web\Controller {

  const TIPO_EXCEL = "excel";
  const TIPO_PDF = "pdf";

  const COLOR_PRIMARIO = "FF1A95E8";
  const COLOR_NEGRO = "FF000000";
  const COLOR_GRIS = "FF959595";

  /**
   * Si es verdadero imprime el contenido en el web
   * @var boolean $html
   */
  public $html = false;

  /**
   * Texto para la marca de agua
   * @var string $marcaDeAguaTexto
   */
  public $marcaDeAguaTexto = "";

  /**
   * Nombre del archivo al descargar
   * @var string $nombreArchivo
   */
  public $nombreArchivo = "";

  /**
   * header
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

  public $renglonActual = 0;

  protected $spreadsheet;

  protected $activeSheet;

  
  public static $estiloCeldaNormal = [
    'alignment' => [
      'horizontal' => Alignment::HORIZONTAL_LEFT,
    ],
  ];

  public static $estiloCeldaCentrada = [
    'alignment' => [
      'horizontal' => Alignment::HORIZONTAL_CENTER,
      'vertical' => Alignment::VERTICAL_CENTER,
    ],
  ];

  public static $estiloEncabezado = [
    'font' => [
      'bold' => true,
      'size' => 9
    ],
    'alignment' => [
      'horizontal' => Alignment::HORIZONTAL_CENTER,
      'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
      'fillType' => Fill::FILL_SOLID,
      'startColor' => ['argb' => self::COLOR_GRIS]
    ],
    'font' => [
      'bold'  => true,
      'color' => ['argb' => "AAFFFFFF"],
    ],
    'borders' => [
      'left' => [
        'borderStyle' => Border::BORDER_THICK,
        'color' => ['argb' => 'FFFFFFFF'],
      ],
      'right' => [
        'borderStyle' => Border::BORDER_THICK,
        'color' => ['argb' => 'FFFFFFFF'],
      ]
    ],
  ];

  public static $estiloTitulo = [
    'font' => [
      'bold' => true,
      'size' => 13,
      'color' => ['argb' => self::COLOR_PRIMARIO],
    ],
    'alignment' => [
      'horizontal' => Alignment::HORIZONTAL_CENTER,
      'vertical' => Alignment::VERTICAL_CENTER,
    ],
  ];

  public static $CELDA_VERDE = [
    'alignment' => [
      'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'fill' => [
      'fillType' => Fill::FILL_SOLID,
      'startColor' => ['argb' => 'FFD8E4BD']
    ],
    'borders' => [
      'left' => [
        'borderStyle' => Border::BORDER_THICK,
        'color' => ['argb' => 'FFFFFFFF'],
      ],
      'right' => [
        'borderStyle' => Border::BORDER_THICK,
        'color' => ['argb' => 'FFFFFFFF'],
      ],
      'bottom' => [
        'borderStyle' => Border::BORDER_DASHED,
      ],
    ],
  ];

  public static $CELDA_AMARILLA = [
    'alignment' => [
      'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'fill' => [
      'fillType' => Fill::FILL_SOLID,
      'startColor' => ['argb' => 'FFFFE597']
    ],
    'borders' => [
      'left' => [
        'borderStyle' => Border::BORDER_THICK,
        'color' => ['argb' => 'FFFFFFFF'],
      ],
      'right' => [
        'borderStyle' => Border::BORDER_THICK,
        'color' => ['argb' => 'FFFFFFFF'],
      ],
      'bottom' => [
        'borderStyle' => Border::BORDER_DASHED,
      ],
    ],
  ];

  public static $CELDA_ROJA = [
    'alignment' => [
      'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'fill' => [
      'fillType' => Fill::FILL_SOLID,
      'startColor' => ['argb' => 'FFE6B8B7']
    ],
    'borders' => [
      'left' => [
        'borderStyle' => Border::BORDER_THICK,
        'color' => ['argb' => 'FFFFFFFF'],
      ],
      'right' => [
        'borderStyle' => Border::BORDER_THICK,
        'color' => ['argb' => 'FFFFFFFF'],
      ],
      'bottom' => [
        'borderStyle' => Border::BORDER_DASHED,
      ],
    ],
  ];

  public function __construct($spreadsheet = null) {
    if($spreadsheet !== null) {
      $this->spreadsheet = $spreadsheet;
    } else {
      $this->spreadsheet = new Spreadsheet();
    }
    $this->activeSheet = $this->spreadsheet->getActiveSheet();
    return $this;
  }

  public function obtenerHojaDeCalculo() {
    return $this->spreadsheet;
  }

  public function nuevaHoja($indice = null, $titulo = null) {
    $this->activeSheet = $this->spreadsheet->createSheet($indice);
    if($titulo !== null) {
      $this->activeSheet->setTitle($titulo);
    }
  }

  public function behaviors() {
    $behavior = parent::behaviors();
    $behavior["authenticator"] = [
      "class" => CompositeAuth::className(),
      "authMethods" => [
        QueryParamAuth::className(),
      ]
    ];
    return $behavior;
  }

  public function beforeAction($action) {
    parent::beforeAction($action);

    $this->req = \Yii::$app->getRequest();
    $this->res = \Yii::$app->getResponse();
    $this->html = intval($this->req->get("html", 0)) === 1;

    if ($this->html) {
      $this->res->format = \yii\web\Response::FORMAT_HTML;
    }

    return true;
  }
  
  public function logo($logo, $coordenada = "I1", $nombre = 'Logo', $descripcion = 'Logo', $x = 27, $y = 8, $heigth = 75) {
    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setName($nombre);
    $drawing->setDescription($descripcion);
    $drawing->setPath($logo);
    $drawing->setCoordinates($coordenada);
    $drawing->setOffsetX($x);
    $drawing->setOffsetY($y);
    $drawing->setHeight($heigth);
    $drawing->setWorksheet($this->activeSheet);
  }

  public function titulo($inicio = 1, $titulo, $columnaInicio = "A", $columnaFinal = "E") {
    if($inicio < $this->renglonActual) {
      $inicio = $this->renglonActual + 1;
    }
    $renglones = [
      "{$columnaInicio}{$inicio}" => [
        "valor" => "{$titulo}",
        "combinar" => "{$columnaFinal}{$inicio}",
        "estilo" => [
          'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
          ],
          'font' => [
            'bold'  => true,
            'color' => ['argb' => self::COLOR_PRIMARIO],
          ]
        ]
      ]
    ];

    foreach($renglones as $coordenada => $valor)  {
      $this->agregarCelda($coordenada, $valor);
    }

    $this->renglonActual = $inicio + 1;
    return $this;
  }

  public function agregarRenglones($renglones) {
    foreach($renglones as $coordenada => $valor)  {
      $this->agregarCelda($coordenada, [
        "valor" => $valor,
        "estilo" => self::$estiloCeldaNormal
      ]);
    }
    return $this;
  }

  public function agregarEncabezado($titulos){
    if(count($titulos) <= 0){
      return $this;
    }

    foreach($titulos as $k=>$v){
      $this->agregarCelda($k, [
        "valor" => "$v",
        "estilo" => self::$estiloEncabezado
      ]);
    }

    return $this;
  }

  public function agregarCelda($coordenada, $valor) {
    if(isset($valor["valor"])) {
      $this->activeSheet
        ->setCellValue($coordenada, $valor["valor"]);
    }
    if(isset($valor["combinar"])) {
      $coordenada = "{$coordenada}:{$valor["combinar"]}";
      $this->activeSheet
        ->mergeCells($coordenada);
    }
    if(isset($valor["estilo"])) {
      $this->activeSheet
        ->getStyle($coordenada)
        ->applyFromArray($valor["estilo"]);
    }
  }

  # Después de agregar toda la información elegir el ancho de las columnas
  public function anchoColumnas($columnas) {
    foreach($columnas as $columna => $c) {
      if(isset($c["auto"]) && $c["auto"]) {
        $this->activeSheet
          ->getColumnDimension($columna)
          ->setAutoSize(true);
      } elseif(isset($c["ancho"]) && $c["ancho"] > 0) {
        $this->activeSheet->getColumnDimension($columna)->setWidth($c["ancho"]);
      }
    }
    return $this;
  }

  # Generar el archivo excel
  public function generar($filename = null) {
    $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
    try {
      ob_start();
      $writer->save("php://output");
      $documento = ob_get_contents();
      ob_clean();
      return $documento;
    } catch (\Exception $exception) {
      return null;
    } 
  }

  # Descarga el archivo en formato excel o pdf
  public function crear($hc, $filename = null, $tipo = self::TIPO_EXCEL) {
    $tipo_writer = 'Xlsx';
    $extension = '.xlsx';
    if($tipo === self::TIPO_PDF) {
      $tipo_writer = 'Mpdf';
      $extension = '.pdf';
    }
    $writer = IOFactory::createWriter($hc, $tipo_writer);
    $filename .= $extension;
    try {
      ob_start();
      $writer->save("php://output");
      $content = ob_get_contents();
      ob_clean();
      \Yii::$app->getResponse()->sendContentAsFile($content, $filename);
    } catch (\Exception $exception) {
      return null;
    } 
  }
  
}
