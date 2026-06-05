<?php

namespace eDesarrollos\formatters\renderers;

use hqsoft\reportkit\document\CellContent;
use hqsoft\reportkit\document\CellImage;
use hqsoft\reportkit\document\Document;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\Table;

class DocxRenderer {
  public function render(Document $doc): string {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $table = $section->addTable([
      'borderSize' => 4,
      'borderColor' => 'D9D9D9',
      'cellMargin' => 80,
      'layout' => Table::LAYOUT_FIXED,
    ]);

    foreach ($doc->getHeaderRows() as $row) {
      $this->renderRow($table, $row, $doc);
    }
    foreach ($doc->getRows() as $row) {
      $this->renderRow($table, $row, $doc);
    }
    foreach ($doc->getFooterRows() as $row) {
      $this->renderRow($table, $row, $doc);
    }

    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    ob_start();
    $writer->save('php://output');
    return ob_get_clean();
  }

  private function renderRow($table, $row, Document $doc): void {
    $table->addRow();
    foreach ($row->getColumns() as $col) {
      $data = $col->toArray();
      $span = max(1, (int)$data['span']);
      $width = Converter::cmToTwip(($span / max(1, $doc->getMaxColumns())) * 16.5);
      $cell = $table->addCell((int)$width, $this->buildCellStyle($data));

      foreach ($data['contents'] as $item) {
        if (is_string($item)) {
          $trim = trim($item);
          if ($trim === '<br>' || $trim === '<br/>' || $trim === '<br />') {
            $cell->addTextBreak();
            continue;
          }
          $cell->addText(strip_tags($item), $this->buildFontStyle($data), $this->buildParagraphStyle($data));
          continue;
        }

        if ($item instanceof CellContent) {
          $cell->addText($item->text, $this->buildContentFontStyle($item, $data), $this->buildParagraphStyle($data));
          continue;
        }

        if ($item instanceof CellImage && is_file($item->src)) {
          $imageStyle = [];
          if ($item->width) {
            $imageStyle['width'] = $item->width;
          }
          if ($item->height) {
            $imageStyle['height'] = $item->height;
          }
          $cell->addImage($item->src, $imageStyle);
        }
      }
    }
  }

  private function buildCellStyle(array $data): array {
    return $this->mapCellStyle($this->resolveStyleArray($data));
  }

  private function buildFontStyle(array $data): array {
    return $this->mapFontStyle($this->resolveStyleArray($data));
  }

  private function buildContentFontStyle(CellContent $item, array $data): array {
    $style = $this->buildFontStyle($data);
    if ($item->bold) {
      $style['bold'] = true;
    }
    if ($item->italic) {
      $style['italic'] = true;
    }
    if ($item->color) {
      $style['color'] = strtoupper(ltrim($item->color, '#'));
    }
    if ($item->size) {
      $style['size'] = (int)$item->size;
    }
    return $style;
  }

  private function buildParagraphStyle(array $data): array {
    $style = [];
    if (!empty($data['align'])) {
      $style['alignment'] = $this->mapAlignment($data['align']);
    }

    $raw = $this->resolveStyleArray($data);
    if (!empty($raw['text-align'])) {
      $style['alignment'] = $this->mapAlignment($raw['text-align']);
    }

    return $style;
  }

  private function mapCellStyle(array $style): array {
    $mapped = [];
    if (!empty($style['background-color'])) {
      $mapped['bgColor'] = strtoupper(ltrim($style['background-color'], '#'));
    }
    if (!empty($style['vertical-align'])) {
      $mapped['valign'] = $style['vertical-align'] === 'middle' ? 'center' : $style['vertical-align'];
    }
    return $mapped;
  }

  private function mapFontStyle(array $style): array {
    $mapped = [];
    if (($style['font-weight'] ?? null) === 'bold') {
      $mapped['bold'] = true;
    }
    if (($style['font-style'] ?? null) === 'italic') {
      $mapped['italic'] = true;
    }
    if (!empty($style['font-size']) && preg_match('/^\s*(\d+)/', (string)$style['font-size'], $matches)) {
      $mapped['size'] = (int)$matches[1];
    }
    if (!empty($style['color'])) {
      $mapped['color'] = strtoupper(ltrim($style['color'], '#'));
    }
    return $mapped;
  }

  private function mapAlignment(string $align): string {
    switch ($align) {
      case 'center':
        return 'center';
      case 'right':
        return 'right';
      default:
        return 'left';
    }
  }

  private function resolveStyleArray(array $data): array {
    $style = $data['style'] ?? [];
    return is_array($style) ? $style : [];
  }
}
