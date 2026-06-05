<?php

namespace eDesarrollos\formatters;

use hqsoft\reportkit\document\Document;
use hqsoft\reportkit\document\Row;
use eDesarrollos\data\Respuesta;
use yii\web\ResponseFormatterInterface;

abstract class ReportFormatter implements ResponseFormatterInterface {
  /**
   * Convierte los datos de la respuesta en un documento de ReportKit
   * 
   * @param mixed $data
   * @return Document
   */
  protected function convertToDocument($data): Document {
    if ($data instanceof Document) {
      return $data;
    }

    if ($data instanceof Respuesta) {
      $documento = $data->crearDocumentoReporte($this->getDocumentType());
      if ($documento instanceof Document && (!empty($documento->getRows()) || !empty($documento->getHeaderRows()) || !empty($documento->getFooterRows()))) {
        return $documento;
      }
    }

    $rows = [];
    if ($data instanceof Respuesta) {
      $rows = $data->cuerpo['resultado'] ?? [];
    } elseif (is_array($data)) {
      if (isset($data['resultado']) && is_array($data['resultado'])) {
        $rows = $data['resultado'];
      } else {
        $rows = $data;
      }
    }

    $doc = new Document();

    if (empty($rows)) {
      return $doc;
    }

    if (!isset($rows[0])) {
      $rows = [$rows];
    }

    // Obtener headers de las llaves del primer registro
    $sample = $rows[0];
    if (is_object($sample)) {
      $sample = (array)$sample;
    }
    if (!is_array($sample)) {
      $sample = ['valor' => $sample];
      $rows = array_map(function ($row) {
        if (is_array($row) || is_object($row)) {
          return $row;
        }
        return ['valor' => $row];
      }, $rows);
    }

    $headers = array_keys($sample);
    $totalCols = count($headers);

    // Ajustar maxColumns si es necesario
    $doc = new Document(null); // DocumentConfig could be passed here

    // Agregar encabezado como primera fila de contenido para evitar
    // que en PDF se use como encabezado fijo de página.
    $doc->row(function (Row $row) use ($headers) {
      foreach ($headers as $header) {
        // Capitalizar y quitar guiones/puntos
        $label = ucwords(str_replace(['_', '.'], ' ', $header));
        $row->col(1)
          ->align('center')
          ->text($label, true);
      }
    });

    // Agregar Filas
    foreach ($rows as $record) {
      $doc->row(function (Row $row) use ($headers, $record) {
        foreach ($headers as $header) {
          $val = "";
          if (is_array($record)) {
            $val = $record[$header] ?? "";
          } elseif (is_object($record)) {
            $val = $record->$header ?? "";
          }

          if (is_array($val) || is_object($val)) {
            $val = json_encode($val);
          }

          $row->col(1)
            ->text((string)$val);
        }
      });
    }

    return $doc;
  }

  abstract protected function getDocumentType(): string;
}
