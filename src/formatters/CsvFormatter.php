<?php

namespace eDesarrollos\formatters;

use hqsoft\reportkit\renderers\csv\CsvRenderer;

class CsvFormatter extends ReportFormatter {
  protected function getDocumentType(): string {
    return \hqsoft\reportkit\document\Document::TYPE_CSV;
  }

  public function format($response) {
    $doc = $this->convertToDocument($response->data);
    $renderer = new CsvRenderer();

    $this->prepararDescarga($response, $renderer->render($doc), 'csv', 'text/csv; charset=UTF-8');
  }
}
