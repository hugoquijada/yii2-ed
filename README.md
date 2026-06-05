# yii2-ed

Libreria compartida de eDesarrollos para Yii2. Centraliza controladores REST, modelos base, respuesta estandar, exportacion de reportes, correo, OpenAPI y utilidades reutilizables.

## Requisitos

- PHP `>= 7.4`
- Yii2 `^2.0.46`

## Instalacion

```bash
composer require edesarrollos/yii2-ed
```

## Dependencias principales

- `firebase/php-jwt`
- `ramsey/uuid`
- `hqsoft/reportkit`
- `phpoffice/phpword`
- `yiisoft/yii2-symfonymailer`

## Estructura principal

- `src/data/`: `Respuesta` y estructuras compartidas
- `src/models/`: modelos base reutilizables
- `src/rest/`: controladores REST, serializer y url manager
- `src/formatters/`: exportacion CSV, XLSX, PDF y DOCX
- `src/openapi/`: generador de OpenAPI
- `src/mail/`: modulo reusable de correo
- `src/cron/`: scheduler y jobs
- `src/gii/`: extensiones para Gii

## REST

Base principal:

- `src/rest/JsonController.php`
- `src/rest/AuthController.php`

Convencion actual:

- `GET` -> `actionIndex()`
- `POST` -> `actionPost()`
- `PUT` -> `actionPut()`
- `DELETE` -> `actionDelete()`

`AuthController` hereda de `JsonController` y agrega autenticacion sobre la misma base de formatos y respuestas.

## Formatos soportados

`UrlManager` soporta:

- `json`
- `xml`
- `html`
- `sql`
- `csv`
- `xlsx`
- `pdf`
- `docx`

Ejemplo:

```text
/v1/usuario.json
/v1/usuario.xlsx
/v1/usuario.pdf
```

## Respuesta estandar

`src/data/Respuesta.php` se usa para:

- respuestas CRUD
- errores de validacion
- respuestas paginadas
- exportacion automatica
- devolver un `Document` manual de ReportKit

## Reportes

`src/models/ModeloBase.php` integra la configuracion global de exportacion desde `Yii::$app->params['exportacion']`.

Metodos importantes:

- `configuracionReporte()`
- `columnasReporte()`
- `filaReporte()`
- `documentoReporte()`

Los formateadores disponibles son:

- `CsvFormatter`
- `SpreadsheetFormatter`
- `PdfFormatter`
- `DocxFormatter`

## OpenAPI

El generador esta en:

- `src/openapi/Generator.php`
- `src/openapi/comandos/OpenapiController.php`

Convenciones disponibles:

- `mostrarEnOpenapi(): bool`
- `accionesOcultasOpenapi(): array`

## Correo

El modulo reusable de correo vive en `src/mail/`.

Los proyectos consumidores deben colocar sus vistas en `@app/mail` para evitar personalizaciones dentro de `vendor`.

## Notas

- esta libreria esta pensada para proyectos Yii2 internos
- cuando cambien las convenciones REST base, conviene subir version mayor
- si agregas dependencias aqui, los proyectos consumidores deben actualizar su `vendor`
- para mas detalle operativo, revisa `DOCUMENTACION.md`
