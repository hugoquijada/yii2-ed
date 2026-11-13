<?php

namespace eDesarrollos\formatters;

use hqsoft\reportkit\renderers\docx\DocxRenderer;

class DocxFormatter extends ReportFormatter {
  protected function getDocumentType(): string {
    return 'docx';
  }

  public function format($response) {
    $doc = $this->convertToDocument($response->data);
    $renderer = new DocxRenderer();

    $this->prepararDescarga(
      $response,
      $renderer->render($doc),
      'docx',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );
  }
}
