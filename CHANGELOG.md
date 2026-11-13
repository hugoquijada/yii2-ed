eDesarrollos Librerías
========================================

Unreleased
----------

- Se agregó el formato REST `blob` para respuestas binarias.
- Las respuestas REST validan el formato solicitado en la URL y devuelven HTTP
  406 cuando una acción intenta enviar un formato distinto.
- Los reportes usan `SCENARIO_REPORTE` al resolver `fields()` y los
  formateadores CSV, XLSX, PDF y DOCX preparan la descarga con su MIME.

1.0.0-alpha, 14 de Julio de 2023
--------------------------------

- Initial release.
