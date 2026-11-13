<?php

namespace eDesarrollos\formatters;

use eDesarrollos\formatters\renderers\SpreadsheetRenderer;

class SpreadsheetFormatter extends ReportFormatter {
  protected function getDocumentType(): string {
    return \hqsoft\reportkit\document\Document::TYPE_SPREADSHEET;
  }

  public function format($response) {
    $doc = $this->convertToDocument($response->data);
    $renderer = new SpreadsheetRenderer();

    $this->prepararDescarga(
      $response,
      $renderer->render($doc),
      'xlsx',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
  }
}
