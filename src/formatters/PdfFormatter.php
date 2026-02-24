<?php

namespace eDesarrollos\formatters;

use hqsoft\reportkit\renderers\pdf\PdfRenderer;

class PdfFormatter extends ReportFormatter {
  public function format($response) {
    $response->getHeaders()->set('Content-Type', 'application/pdf');

    $doc = $this->convertToDocument($response->data);
    $renderer = new PdfRenderer();

    $response->content = $renderer->render($doc);
  }
}
