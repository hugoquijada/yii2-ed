<?php

namespace eDesarrollos\formatters;

use eDesarrollos\formatters\renderers\SpreadsheetRenderer;

class SpreadsheetFormatter extends ReportFormatter {
  protected function getDocumentType(): string {
    return \hqsoft\reportkit\document\Document::TYPE_SPREADSHEET;
  }

  public function format($response) {
    $response->getHeaders()->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $doc = $this->convertToDocument($response->data);
    $renderer = new SpreadsheetRenderer();

    // El renderer de spreadsheet de ReportKit devuelve el contenido binario
    $response->content = $renderer->render($doc);
  }
}
