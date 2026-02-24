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

    $rows = [];
    if ($data instanceof Respuesta) {
      $rows = $data->cuerpo['resultado'] ?? [];
    } elseif (is_array($data)) {
      $rows = $data;
    }

    $doc = new Document();

    if (empty($rows)) {
      return $doc;
    }

    // Obtener headers de las llaves del primer registro
    $sample = $rows[0];
    if (is_object($sample)) {
      $sample = (array)$sample;
    }

    $headers = array_keys($sample);
    $totalCols = count($headers);

    // Ajustar maxColumns si es necesario
    $doc = new Document(null); // DocumentConfig could be passed here

    // Agregar Header
    $doc->header(function (Row $row) use ($headers) {
      foreach ($headers as $header) {
        // Capitalizar y quitar guiones/puntos
        $label = ucwords(str_replace(['_', '.'], ' ', $header));
        $row->colText(1, $label, 'center', true);
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

          $row->colText(1, (string)$val);
        }
      });
    }

    return $doc;
  }
}
