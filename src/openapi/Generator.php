<?php

namespace eDesarrollos\openapi;

use eDesarrollos\rest\AuthController;
use eDesarrollos\rest\JsonController;
use ReflectionClass;
use ReflectionMethod;
use Yii;
use yii\db\ColumnSchema;
use yii\base\Controller;
use yii\helpers\Inflector;

class Generator {
  protected array $config;

  public function __construct(array $config = []) {
    $this->config = $config;
  }

  public function generate(): array {
    $document = [
      'openapi' => '3.1.0',
      'info' => [
        'title' => $this->config['title'] ?? 'API',
        'version' => $this->config['version'] ?? '1.0.0',
        'description' => $this->config['description'] ?? '',
      ],
      'servers' => $this->config['servers'] ?? [],
      'tags' => [],
      'paths' => [],
      'components' => [
        'securitySchemes' => [
          'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
          ],
        ],
        'schemas' => [],
      ],
    ];

    foreach ($this->config['controllers'] ?? [] as $source) {
      $this->appendControllerSource($document, $source);
    }

    $document['tags'] = array_values($document['tags']);
    ksort($document['paths']);
    ksort($document['components']['schemas']);

    return $document;
  }

  public function renderScalarHtml(): string {
    $title = $this->config['scalar']['title'] ?? ($this->config['title'] ?? 'API Docs');
    $specUrl = $this->config['scalar']['specUrl'] ?? './openapi.json';
    $cdnUrl = $this->config['scalar']['cdnUrl'] ?? 'https://cdn.jsdelivr.net/npm/@scalar/api-reference';

    return <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$this->escapeHtml($title)}</title>
  <style>
    body { margin: 0; }
  </style>
</head>
<body>
  <script
    id="api-reference"
    data-url="{$this->escapeHtml($specUrl)}"></script>
  <script src="{$this->escapeHtml($cdnUrl)}"></script>
</body>
</html>
HTML;
  }

  protected function appendControllerSource(array &$document, array $source): void {
    $path = Yii::getAlias($source['path'] ?? '', false);
    $namespace = trim($source['namespace'] ?? '', '\\');
    if ($path === false || $namespace === '' || !is_dir($path)) {
      return;
    }

    $moduleId = trim($source['module'] ?? '', '/');
    $files = glob($path . '/*Controller.php') ?: [];
    sort($files);

    foreach ($files as $file) {
      $className = $namespace . '\\' . basename($file, '.php');
      if (!class_exists($className)) {
        require_once $file;
      }
      if (!class_exists($className)) {
        continue;
      }

      $reflection = new ReflectionClass($className);
      if (!$reflection->isSubclassOf(Controller::class) || $reflection->isAbstract()) {
        continue;
      }
      if (!$this->isVisibleInOpenapi($reflection)) {
        continue;
      }

      $controllerName = preg_replace('/Controller$/', '', $reflection->getShortName());
      $controllerId = Inflector::camel2id($controllerName);
      $basePath = '/' . ltrim(trim($moduleId . '/' . $controllerId, '/'), '/');
      $tagName = $basePath . '.json';
      $document['tags'][$tagName] = [
        'name' => $tagName,
        'description' => $this->extractSummary($reflection->getDocComment(), $controllerName),
      ];

      $modelClass = $this->resolveModelClass($reflection);
      $schemaName = null;
      if ($modelClass !== null) {
        $schemaName = $this->appendModelSchema($document['components']['schemas'], $modelClass);
      }

      $this->appendBaseOperations($document['paths'], $reflection, $basePath, $tagName, $modelClass, $schemaName);
      $this->appendCustomOperations($document['paths'], $reflection, $basePath, $tagName);
    }
  }

  protected function appendBaseOperations(array &$paths, ReflectionClass $controller, string $basePath, string $tagName, ?string $modelClass, ?string $schemaName): void {
    $supportsGet = $modelClass !== null || $this->declaresMethod($controller, 'actionIndex');
    $supportsWrite = $modelClass !== null || $this->declaresMethod($controller, 'actionPost');
    $supportsDelete = $modelClass !== null || $this->declaresMethod($controller, 'actionDelete');

    if (!$supportsGet && !$supportsWrite && !$supportsDelete) {
      return;
    }

    $path = $basePath . '.{format}';
    $security = $controller->isSubclassOf(AuthController::class) ? [['bearerAuth' => []]] : [];
    $modelTitle = $schemaName ?? $controller->getShortName();

    if ($supportsGet) {
      $paths[$path]['get'] = [
        'tags' => [$tagName],
        'summary' => $modelClass !== null ? "Lista {$modelTitle}" : 'Consulta registros',
        'parameters' => [$this->buildFormatParameter()],
        'responses' => [
          '200' => [
            'description' => 'Respuesta exitosa',
            'content' => [
              'application/json' => [
                'schema' => $this->buildCollectionResponseSchema($schemaName),
              ],
            ],
          ],
        ],
        'security' => $security,
      ];
    }

    if ($supportsWrite) {
      $writeSchema = $schemaName !== null ? ['$ref' => '#/components/schemas/' . $schemaName] : ['type' => 'object'];
      foreach (['post' => 'Guarda registro', 'put' => 'Actualiza registro'] as $method => $summary) {
        $paths[$path][$method] = [
          'tags' => [$tagName],
          'summary' => $modelClass !== null ? $summary . " de {$modelTitle}" : $summary,
          'parameters' => [$this->buildFormatParameter()],
          'requestBody' => [
            'required' => true,
            'content' => [
              'application/json' => [
                'schema' => $writeSchema,
              ],
            ],
          ],
          'responses' => [
            '200' => [
              'description' => 'Respuesta exitosa',
              'content' => [
                'application/json' => [
                  'schema' => $this->buildDetailResponseSchema($schemaName),
                ],
              ],
            ],
            '400' => [
              'description' => 'Error de validacion al guardar el registro',
              'content' => [
                'application/json' => [
                  'schema' => $this->buildValidationErrorResponseSchema(),
                  'example' => [
                    'errores' => [
                      'nombre_campo' => 'El campo nombre_campo no puede estar vacío',
                      'otra_columna' => 'El campo otra_columna tiene otro error',
                    ],
                    'mensaje' => 'Ocurrió un problema al guardar el registro, por favor contacta a soporte técnico',
                  ],
                ],
              ],
            ],
          ],
          'security' => $security,
        ];
      }
    }

    if ($supportsDelete) {
      $paths[$path]['delete'] = [
        'tags' => [$tagName],
        'summary' => $modelClass !== null ? "Elimina {$modelTitle}" : 'Elimina registro',
        'parameters' => [$this->buildFormatParameter()],
        'responses' => [
          '200' => [
            'description' => 'Respuesta exitosa',
            'content' => [
              'application/json' => [
                'schema' => $this->buildGenericResponseSchema(),
              ],
            ],
          ],
        ],
        'security' => $security,
      ];
    }
  }

  protected function appendCustomOperations(array &$paths, ReflectionClass $controller, string $basePath, string $tagName): void {
    $security = $controller->isSubclassOf(AuthController::class) ? [['bearerAuth' => []]] : [];
    $hiddenActions = $this->resolveHiddenActions($controller);

    foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
      if ($method->getDeclaringClass()->getName() !== $controller->getName()) {
        continue;
      }
      if (!preg_match('/^action([A-Z].+)$/', $method->getName(), $matches)) {
        continue;
      }

      $actionId = Inflector::camel2id($matches[1]);
      if (in_array($actionId, ['index', 'post', 'put', 'delete', 'options', 'error'], true)) {
        continue;
      }
      if (in_array($actionId, $hiddenActions, true)) {
        continue;
      }

      $path = $basePath . '/' . $actionId . '.{format}';
      $httpMethod = $this->guessHttpMethod($actionId);

      $paths[$path][$httpMethod] = [
        'tags' => [$tagName],
        'summary' => $this->extractSummary($method->getDocComment(), ucfirst(str_replace('-', ' ', $actionId))),
        'parameters' => [$this->buildFormatParameter()],
        'responses' => [
          '200' => [
            'description' => 'Respuesta exitosa',
            'content' => [
              'application/json' => [
                'schema' => $this->buildGenericResponseSchema(),
              ],
            ],
          ],
        ],
        'security' => $security,
      ];
    }
  }

  protected function appendModelSchema(array &$schemas, string $modelClass): string {
    $reflection = new ReflectionClass($modelClass);
    if (!$this->isVisibleInOpenapi($reflection)) {
      return $reflection->getShortName();
    }

    $schemaName = $reflection->getShortName();
    if (isset($schemas[$schemaName])) {
      return $schemaName;
    }

    $model = new $modelClass();
    $fields = $model->fields();
    $labels = $model->attributeLabels();
    $rules = method_exists($model, 'rules') ? $model->rules() : [];
    $tableSchema = method_exists($modelClass, 'getTableSchema') ? $modelClass::getTableSchema() : null;

    $properties = [];
    $required = [];

    foreach ($fields as $key => $value) {
      $attribute = is_string($key) ? $key : $value;
      if (!is_string($attribute)) {
        continue;
      }
      $properties[$attribute] = $this->buildPropertySchema($attribute, $labels[$attribute] ?? $attribute, $tableSchema->columns[$attribute] ?? null);
    }

    foreach ($rules as $rule) {
      $attributes = (array)($rule[0] ?? []);
      $validator = $rule[1] ?? null;

      foreach ($attributes as $attribute) {
        if (!isset($properties[$attribute])) {
          continue;
        }

        switch ($validator) {
          case 'required':
            $required[] = $attribute;
            break;
          case 'integer':
            $properties[$attribute]['type'] = 'integer';
            break;
          case 'number':
          case 'double':
            $properties[$attribute]['type'] = 'number';
            break;
          case 'boolean':
            $properties[$attribute]['type'] = 'boolean';
            break;
          case 'string':
            $properties[$attribute]['type'] = 'string';
            if (isset($rule['max'])) {
              $properties[$attribute]['maxLength'] = (int)$rule['max'];
            }
            if (isset($rule['min'])) {
              $properties[$attribute]['minLength'] = (int)$rule['min'];
            }
            break;
          case 'each':
            $properties[$attribute]['type'] = 'array';
            break;
          case 'in':
            $properties[$attribute]['enum'] = array_values($rule['range'] ?? []);
            break;
          case 'safe':
            $this->applySafeRuleSchema($properties[$attribute], $attribute, $tableSchema->columns[$attribute] ?? null);
            break;
        }
      }
    }

    $schema = [
      'type' => 'object',
      'properties' => $properties,
      'additionalProperties' => false,
    ];
    if (!empty($required)) {
      $schema['required'] = array_values(array_unique($required));
    }

    $summary = $this->extractSummary($reflection->getDocComment(), $schemaName);
    if ($summary !== '') {
      $schema['description'] = $summary;
    }

    $schemas[$schemaName] = $schema;
    return $schemaName;
  }

  protected function buildPropertySchema(string $attribute, string $description, ?ColumnSchema $columnSchema): array {
    $schema = [
      'type' => 'string',
      'description' => $description,
    ];

    if ($columnSchema === null) {
      return $schema;
    }

    $type = $columnSchema->type;
    if (in_array($type, ['integer', 'smallint', 'bigint'], true)) {
      $schema['type'] = 'integer';
    } elseif (in_array($type, ['float', 'double', 'decimal', 'money'], true)) {
      $schema['type'] = 'number';
    } elseif (in_array($type, ['boolean'], true)) {
      $schema['type'] = 'boolean';
    } elseif ($type === 'date') {
      $schema['type'] = 'string';
      $schema['format'] = 'date';
    } elseif (in_array($type, ['datetime', 'timestamp', 'time'], true)) {
      $schema['type'] = 'string';
      $schema['format'] = $type === 'time' ? 'time' : 'date-time';
    } else {
      $schema['type'] = 'string';
    }

    if (is_int($columnSchema->size) && $columnSchema->size > 0 && $schema['type'] === 'string') {
      $schema['maxLength'] = $columnSchema->size;
    }

    return $schema;
  }

  protected function applySafeRuleSchema(array &$property, string $attribute, ?ColumnSchema $columnSchema): void {
    if ($columnSchema !== null) {
      $type = $columnSchema->type;
      if ($type === 'date') {
        $property['type'] = 'string';
        $property['format'] = 'date';
        return;
      }
      if (in_array($type, ['datetime', 'timestamp', 'time'], true)) {
        $property['type'] = 'string';
        $property['format'] = $type === 'time' ? 'time' : 'date-time';
        return;
      }
    }

    if (preg_match('/(^|_)(fecha|creado|modificado|eliminado|updated|created|deleted|timestamp|hora)(_|$)/i', $attribute)) {
      $property['type'] = 'string';
      $property['format'] = 'date-time';
    }
  }

  protected function resolveModelClass(ReflectionClass $controller): ?string {
    $defaults = $controller->getDefaultProperties();
    $modelClass = $defaults['modelClass'] ?? null;
    if (!is_string($modelClass) || $modelClass === '') {
      return null;
    }

    $modelClass = ltrim($modelClass, '\\');
    return class_exists($modelClass) ? $modelClass : null;
  }

  protected function isVisibleInOpenapi(ReflectionClass $reflection): bool {
    if (!$reflection->hasMethod('mostrarEnOpenapi')) {
      return true;
    }

    $method = $reflection->getMethod('mostrarEnOpenapi');
    if (!$method->isStatic() || !$method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
      return true;
    }

    try {
      return (bool)$method->invoke(null);
    } catch (\Throwable $th) {
      return true;
    }
  }

  protected function resolveHiddenActions(ReflectionClass $controller): array {
    if (!$controller->hasMethod('accionesOcultasOpenapi')) {
      return [];
    }

    $method = $controller->getMethod('accionesOcultasOpenapi');
    if (!$method->isStatic() || !$method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
      return [];
    }

    try {
      $result = $method->invoke(null);
      if (!is_array($result)) {
        return [];
      }

      return array_values(array_filter(array_map(function ($action) {
        return is_string($action) ? trim($action) : '';
      }, $result)));
    } catch (\Throwable $th) {
      return [];
    }
  }

  protected function declaresMethod(ReflectionClass $controller, string $method): bool {
    return $controller->hasMethod($method) && $controller->getMethod($method)->getDeclaringClass()->getName() === $controller->getName();
  }

  protected function buildFormatParameter(): array {
    return [
      'name' => 'format',
      'in' => 'path',
      'required' => true,
      'schema' => [
        'type' => 'string',
        'enum' => $this->config['formats'] ?? ['json'],
      ],
      'description' => 'Formato de respuesta',
    ];
  }

  protected function buildCollectionResponseSchema(?string $schemaName): array {
    $resultado = ['type' => 'array'];
    if ($schemaName !== null) {
      $resultado['items'] = ['$ref' => '#/components/schemas/' . $schemaName];
    } else {
      $resultado['items'] = ['type' => 'object'];
    }

    return [
      'type' => 'object',
      'properties' => [
        'resultado' => $resultado,
        'mensaje' => ['type' => 'string'],
        'paginacion' => [
          'type' => 'object',
          'properties' => [
            'total' => ['type' => 'integer'],
            'pagina' => ['type' => 'integer'],
            'limite' => ['type' => 'integer'],
          ],
          'required' => ['total', 'pagina', 'limite'],
          'additionalProperties' => false,
        ],
      ],
      'required' => ['resultado', 'paginacion'],
      'additionalProperties' => false,
    ];
  }

  protected function buildDetailResponseSchema(?string $schemaName): array {
    $detalle = $schemaName !== null
      ? ['$ref' => '#/components/schemas/' . $schemaName]
      : ['type' => 'object', 'additionalProperties' => true];

    return [
      'type' => 'object',
      'properties' => [
        'mensaje' => ['type' => 'string'],
        'errores' => ['type' => 'object', 'additionalProperties' => true],
        'detalle' => $detalle,
      ],
      'additionalProperties' => false,
    ];
  }

  protected function buildGenericResponseSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'mensaje' => ['type' => 'string'],
        'errores' => ['type' => 'object', 'additionalProperties' => true],
        'detalle' => ['type' => 'object', 'additionalProperties' => true],
      ],
      'additionalProperties' => false,
    ];
  }

  protected function buildValidationErrorResponseSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'errores' => [
          'type' => 'object',
          'additionalProperties' => [
            'type' => 'string',
          ],
        ],
        'mensaje' => [
          'type' => 'string',
        ],
      ],
      'required' => ['errores', 'mensaje'],
      'additionalProperties' => false,
    ];
  }

  protected function guessHttpMethod(string $actionId): string {
    if (preg_match('/^(guardar|crear|actualizar|iniciar-sesion|refrescar-token)/', $actionId)) {
      return 'post';
    }

    if (preg_match('/^(eliminar|borrar)/', $actionId)) {
      return 'delete';
    }

    return 'get';
  }

  protected function extractSummary($docComment, string $fallback): string {
    if (!is_string($docComment) || trim($docComment) === '') {
      return $fallback;
    }

    $lines = preg_split('/\R/', $docComment) ?: [];
    foreach ($lines as $line) {
      $line = trim($line, "/* \t\n\r\0\x0B");
      if ($line === '' || strpos($line, '@') === 0) {
        continue;
      }
      return $line;
    }

    return $fallback;
  }

  protected function escapeHtml(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}
