<?php

namespace eDesarrollos\formatters;

use hqsoft\reportkit\renderers\pdf\PdfRenderer;

class PdfFormatter extends ReportFormatter {
  protected function getDocumentType(): string {
    return \hqsoft\reportkit\document\Document::TYPE_PDF;
  }

  public function format($response) {
    $doc = $this->convertToDocument($response->data);
    $renderer = new PdfRenderer();

    $this->prepararDescarga($response, $renderer->render($doc), 'pdf', 'application/pdf');
  }
}
