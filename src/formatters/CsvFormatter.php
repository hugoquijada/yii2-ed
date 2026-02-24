<?php

namespace eDesarrollos\formatters;

use hqsoft\reportkit\renderers\csv\CsvRenderer;

class CsvFormatter extends ReportFormatter {
  public function format($response) {
    $response->getHeaders()->set('Content-Type', 'text/csv; charset=UTF-8');

    $doc = $this->convertToDocument($response->data);
    $renderer = new CsvRenderer();

    $response->content = $renderer->render($doc);
  }
}
