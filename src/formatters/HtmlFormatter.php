<?php

namespace eDesarrollos\formatters;

use hqsoft\reportkit\renderers\html\HtmlRenderer;

class HtmlFormatter extends ReportFormatter {
  protected function getDocumentType(): string {
    return 'html';
  }

  public function format($response) {
    $response->getHeaders()->set('Content-Type', 'text/html; charset=UTF-8');

    $doc = $this->convertToDocument($response->data);
    $renderer = new HtmlRenderer();

    $response->content = $renderer->render($doc);
  }
}
