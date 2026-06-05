<?php

namespace eDesarrollos\formatters;

use eDesarrollos\formatters\renderers\DocxRenderer;
use hqsoft\reportkit\document\Document;

class DocxFormatter extends ReportFormatter {
  protected function getDocumentType(): string {
    return 'docx';
  }

  public function format($response) {
    $response->getHeaders()->set('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $doc = $this->convertToDocument($response->data);
    $renderer = new DocxRenderer();

    $response->content = $renderer->render($doc);
  }
}
