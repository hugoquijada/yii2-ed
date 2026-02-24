<?php

namespace eDesarrollos\formatters;

use hqsoft\reportkit\renderers\spreadsheet\SpreadsheetRenderer;
use Yii;

class SpreadsheetFormatter extends ReportFormatter {
  public function format($response) {
    $response->getHeaders()->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $doc = $this->convertToDocument($response->data);
    $renderer = new SpreadsheetRenderer();

    // El renderer de spreadsheet de ReportKit devuelve el contenido binario
    $response->content = $renderer->render($doc);
  }
}
