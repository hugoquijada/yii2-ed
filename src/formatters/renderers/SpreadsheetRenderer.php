<?php

namespace eDesarrollos\formatters\renderers;

use hqsoft\reportkit\document\CellContent;
use hqsoft\reportkit\document\CellImage;
use hqsoft\reportkit\document\Document;
use hqsoft\reportkit\Renderers\IRenderer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpreadsheetRenderer implements IRenderer {
  private int $currentRow = 1;
  private array $columnWidths = [];

  public function render(Document $doc): string {
    $spreadsheet = new Spreadsheet();
    $ws = $spreadsheet->getActiveSheet();
    $maxCols = $doc->getMaxColumns();

    for ($i = 1; $i <= $maxCols; $i++) {
      $this->columnWidths[$i] = 10;
    }

    foreach ($doc->getHeaderRows() as $row) {
      $this->renderRow($ws, $row, $doc);
    }

    foreach ($doc->getRows() as $row) {
      $this->renderRow($ws, $row, $doc);
    }

    foreach ($doc->getFooterRows() as $row) {
      $this->renderRow($ws, $row, $doc);
    }

    foreach ($this->columnWidths as $index => $width) {
      $ws->getColumnDimension(Coordinate::stringFromColumnIndex($index))
        ->setWidth(min(60, max(8, $width)));
    }

    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    return ob_get_clean();
  }

  private function renderRow($ws, $row, Document $doc): void {
    $colIndex = 1;
    $maxRowRangeStart = null;
    $maxRowRangeEnd = null;

    foreach ($row->getColumns() as $col) {
      $data = $col->toArray();
      $span = $data['span'];

      $start = Coordinate::stringFromColumnIndex($colIndex);
      $end = Coordinate::stringFromColumnIndex($colIndex + $span - 1);

      $cellRef = "{$start}{$this->currentRow}";
      $range = "{$start}{$this->currentRow}:{$end}{$this->currentRow}";

      if ($span > 1) {
        $ws->mergeCells($range);
      }

      $plainText = $this->renderCellContents($data['contents'], $ws, $cellRef);
      $this->applyStyles($ws, $range, $data, $doc);
      $ws->getStyle($range)->getAlignment()->setWrapText(true);

      $this->updateColumnWidths($colIndex, $span, $plainText, $data);

      if ($maxRowRangeStart === null) {
        $maxRowRangeStart = $start;
      }
      $maxRowRangeEnd = $end;

      $colIndex += $span;
    }

    if ($maxRowRangeStart !== null && $maxRowRangeEnd !== null) {
      $fullRange = "{$maxRowRangeStart}{$this->currentRow}:{$maxRowRangeEnd}{$this->currentRow}";
      $ws->getStyle($fullRange)->getAlignment()->setWrapText(true);
    }

    $this->currentRow++;
  }

  private function renderCellContents(array $contents, $ws, string $cellRef): string {
    $text = '';
    foreach ($contents as $item) {
      if (is_string($item)) {
        $trim = trim($item);
        if ($trim === '<br>' || $trim === '<br/>' || $trim === '<br />') {
          $text .= "\n";
          continue;
        }

        $text .= strip_tags($item) . ' ';
        continue;
      }

      if ($item instanceof CellContent) {
        $text .= $item->text . ' ';
        continue;
      }

      if ($item instanceof CellImage) {
        if (is_file($item->src)) {
          $drawing = new Drawing();
          $drawing->setPath($item->src);
          $drawing->setCoordinates($cellRef);

          if ($item->width) {
            $drawing->setWidth($item->width);
          }
          if ($item->height) {
            $drawing->setHeight($item->height);
          }

          $drawing->setWorksheet($ws);
        }
      }
    }

    $text = trim($text);
    $ws->setCellValue($cellRef, $text);
    $ws->getStyle($cellRef)->getAlignment()->setWrapText(true);
    return $text;
  }

  private function updateColumnWidths(int $startIndex, int $span, string $text, array $data): void {
    $length = $this->estimateTextWidth($text);
    $styleWidth = $this->extractConfiguredWidth($data);
    $widthPerColumn = $span > 1 ? max(8, ($styleWidth ?? $length) / $span) : ($styleWidth ?? $length);

    for ($i = 0; $i < $span; $i++) {
      $index = $startIndex + $i;
      $this->columnWidths[$index] = max($this->columnWidths[$index] ?? 8, $widthPerColumn);
    }
  }

  private function estimateTextWidth(string $text): int {
    if ($text === '') {
      return 10;
    }

    $lines = preg_split('/\R/', $text) ?: [$text];
    $max = 0;
    foreach ($lines as $line) {
      $max = max($max, strlen($line));
    }

    return min(60, max(10, $max + 2));
  }

  private function extractConfiguredWidth(array $data): ?int {
    if (empty($data['style']) || !is_array($data['style'])) {
      return null;
    }

    $width = null;
    foreach ($data['style'] as $key => $value) {
      if ($key === 'width') {
        $width = $this->normalizeWidth($value);
      }
    }

    return $width;
  }

  private function normalizeWidth($value): ?int {
    if (is_numeric($value)) {
      return (int)ceil(((float)$value) / 8);
    }

    if (is_string($value) && preg_match('/^\s*(\d+)/', $value, $matches)) {
      return (int)ceil(((float)$matches[1]) / 8);
    }

    return null;
  }

  private function applyStyles($ws, string $range, array $data, Document $doc): void {
    $finalStyle = [];

    if (!empty($data['style'])) {
      $stylesToApply = is_array($data['style']) ? $data['style'] : [$data['style']];
      foreach ($stylesToApply as $s) {
        if (is_string($s)) {
          $resolved = $doc->getStyle($s);
          if ($resolved) {
            $this->mergeStyleArray($finalStyle, $resolved->toArray());
          }
        } elseif ($s instanceof \hqsoft\reportkit\document\CellStyle) {
          $this->mergeStyleArray($finalStyle, $s->toArray());
        }
      }
    }

    if (!empty($data['align'])) {
      $finalStyle['text-align'] = $data['align'];
    }

    if (!empty($data['format'])) {
      $finalStyle['format'] = $this->getExcelFormat($data['format']);
    }

    foreach ($data['contents'] as $item) {
      if ($item instanceof CellContent && $item->background) {
        $finalStyle['background-color'] = $item->background;
        break;
      }
    }

    if (!empty($data['borders'])) {
      foreach ($data['borders'] as $borderDef) {
        list($side, $color, $thick) = $borderDef;
        if (!isset($finalStyle['borders'])) {
          $finalStyle['borders'] = [];
        }
        $finalStyle['borders'][] = ['side' => $side, 'color' => $color, 'style' => $thick > 1 ? 'thick' : 'thin'];
      }
    }

    $phpSpreadsheetStyle = [];
    foreach ($finalStyle as $k => $v) {
      if ($k === 'borders') {
        foreach ($v as $borderDef) {
          $this->applyBorder($phpSpreadsheetStyle, $borderDef['side'], $borderDef['color'], $borderDef['style']);
        }
      } else {
        $this->mapCellStyle($phpSpreadsheetStyle, $k, $v);
      }
    }

    if ($phpSpreadsheetStyle) {
      $ws->getStyle($range)->applyFromArray($phpSpreadsheetStyle);
    }

    $startCell = explode(':', $range)[0];
    $colLetter = preg_replace('/[0-9]+/', '', $startCell);
    $rowNumber = (int)preg_replace('/[A-Z]+/', '', $startCell);

    $this->applyDimensions($ws, $colLetter, $rowNumber, $finalStyle);
  }

  private function mergeStyleArray(array &$target, array $source): void {
    foreach ($source as $key => $value) {
      if ($key === 'borders' && isset($target['borders'])) {
        $target['borders'] = array_merge($target['borders'], $value);
      } else {
        $target[$key] = $value;
      }
    }
  }

  private function mapAlign(string $align): string {
    switch ($align) {
      case 'left':
        return Alignment::HORIZONTAL_LEFT;
      case 'right':
        return Alignment::HORIZONTAL_RIGHT;
      case 'center':
        return Alignment::HORIZONTAL_CENTER;
      default:
        return Alignment::HORIZONTAL_LEFT;
    }
  }

  private function mapValign(string $align): string {
    switch ($align) {
      case 'top':
        return Alignment::VERTICAL_TOP;
      case 'middle':
        return Alignment::VERTICAL_CENTER;
      case 'bottom':
        return Alignment::VERTICAL_BOTTOM;
      default:
        return Alignment::VERTICAL_TOP;
    }
  }

  private function getExcelFormat(string $format): string {
    switch ($format) {
      case 'number':
        return '#,##0.00';
      case 'currency':
        return '$#,##0.00';
      case 'percentage':
        return '0.00%';
      case 'date':
        return 'DD/MM/YYYY';
      case 'datetime':
        return 'DD/MM/YYYY HH:MM:SS';
      case 'time':
        return 'HH:MM:SS';
      case 'text':
        return '@';
      default:
        return $format;
    }
  }

  private function mapCellStyle(array &$style, string $key, $value): void {
    switch ($key) {
      case 'font-weight':
        if ($value === 'bold') {
          $style['font']['bold'] = true;
        }
        break;
      case 'font-style':
        if ($value === 'italic') {
          $style['font']['italic'] = true;
        }
        break;
      case 'font-size':
        $style['font']['size'] = (int)$value;
        break;
      case 'color':
        $style['font']['color'] = ['argb' => 'FF' . strtoupper(ltrim($value, '#'))];
        break;
      case 'background-color':
        $hex = strtoupper(ltrim($value, '#'));
        $style['fill'] = [
          'fillType' => Fill::FILL_SOLID,
          'startColor' => ['argb' => 'FF' . $hex],
        ];
        break;
      case 'text-align':
        $style['alignment']['horizontal'] = $this->mapAlign($value);
        break;
      case 'vertical-align':
        $style['alignment']['vertical'] = $this->mapValign($value);
        break;
      case 'wrap-text':
        $style['alignment']['wrapText'] = (bool)$value;
        break;
      case 'format':
        $style['numberFormat']['formatCode'] = $value;
        break;
      case 'text-decoration':
        if ($value === 'underline') {
          $style['font']['underline'] = \PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE;
        } elseif ($value === 'line-through') {
          $style['font']['strikethrough'] = true;
        }
        break;
      case 'padding':
        $px = (int)$value;
        if ($px > 0) {
          $style['alignment']['indent'] = min(15, (int)($px / 8));
        }
        break;
    }
  }

  private function applyBorder(array &$style, string $side, string $color, string $borderStyle): void {
    $styleMap = [
      'thin' => Border::BORDER_THIN,
      'thick' => Border::BORDER_THICK,
      'medium' => Border::BORDER_MEDIUM,
    ];
    $phpBorder = $styleMap[$borderStyle] ?? Border::BORDER_THIN;
    $borderData = [
      'borderStyle' => $phpBorder,
      'color' => ['argb' => 'FF' . strtoupper(ltrim($color, '#'))],
    ];

    if (!isset($style['borders'])) {
      $style['borders'] = [];
    }

    if ($side === 'all') {
      $style['borders']['allBorders'] = $borderData;
      return;
    }

    $map = [
      'top' => 'top',
      'bottom' => 'bottom',
      'left' => 'left',
      'right' => 'right',
    ];
    if (isset($map[$side])) {
      $style['borders'][$map[$side]] = $borderData;
    }
  }

  private function applyDimensions($ws, string $colLetter, int $rowNumber, array $style): void {
    if (!empty($style['width'])) {
      $normalized = $this->normalizeWidth($style['width']);
      if ($normalized !== null) {
        $ws->getColumnDimension($colLetter)->setWidth($normalized);
        $this->columnWidths[Coordinate::columnIndexFromString($colLetter)] = max($this->columnWidths[Coordinate::columnIndexFromString($colLetter)] ?? 8, $normalized);
      }
    }

    if (!empty($style['height'])) {
      $value = $style['height'];
      if (is_string($value) && preg_match('/^\s*(\d+)/', $value, $matches)) {
        $value = (int)$matches[1];
      }
      if (is_numeric($value)) {
        $ws->getRowDimension($rowNumber)->setRowHeight((float)$value * 0.75);
      }
    }
  }
}
