<?php

namespace eDesarrollos\openapi;

use eDesarrollos\rest\AuthController;
use eDesarrollos\rest\JsonController;
use ReflectionClass;
use ReflectionMethod;
use Yii;
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

      $controllerName = preg_replace('/Controller$/', '', $reflection->getShortName());
      $controllerId = Inflector::camel2id($controllerName);
      $basePath = '/' . ltrim(trim($moduleId . '/' . $controllerId, '/'), '/');
      $tagName = trim($moduleId . ' ' . $controllerName);
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
    $supportsWrite = $modelClass !== null || $this->declaresMethod($controller, 'actionGuardar');
    $supportsDelete = $modelClass !== null || $this->declaresMethod($controller, 'actionEliminar');

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

    foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
      if ($method->getDeclaringClass()->getName() !== $controller->getName()) {
        continue;
      }
      if (!preg_match('/^action([A-Z].+)$/', $method->getName(), $matches)) {
        continue;
      }

      $actionId = Inflector::camel2id($matches[1]);
      if (in_array($actionId, ['index', 'guardar', 'eliminar', 'options', 'error'], true)) {
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
    $schemaName = $reflection->getShortName();
    if (isset($schemas[$schemaName])) {
      return $schemaName;
    }

    $model = new $modelClass();
    $fields = $model->fields();
    $labels = $model->attributeLabels();
    $rules = method_exists($model, 'rules') ? $model->rules() : [];

    $properties = [];
    $required = [];

    foreach ($fields as $key => $value) {
      $attribute = is_string($key) ? $key : $value;
      if (!is_string($attribute)) {
        continue;
      }
      $properties[$attribute] = [
        'type' => 'string',
        'description' => $labels[$attribute] ?? $attribute,
      ];
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

  protected function resolveModelClass(ReflectionClass $controller): ?string {
    $defaults = $controller->getDefaultProperties();
    $modelClass = $defaults['modelClass'] ?? null;
    if (!is_string($modelClass) || $modelClass === '') {
      return null;
    }

    $modelClass = ltrim($modelClass, '\\');
    return class_exists($modelClass) ? $modelClass : null;
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
        'errores' => ['type' => 'object', 'additionalProperties' => true],
        'detalle' => ['type' => 'object', 'additionalProperties' => true],
        'paginacion' => [
          'type' => 'object',
          'properties' => [
            'total' => ['type' => 'integer'],
            'pagina' => ['type' => 'integer'],
            'limite' => ['type' => 'integer'],
          ],
        ],
      ],
      'additionalProperties' => true,
    ];
  }

  protected function buildDetailResponseSchema(?string $schemaName): array {
    $detalle = $schemaName !== null
      ? ['$ref' => '#/components/schemas/' . $schemaName]
      : ['type' => 'object', 'additionalProperties' => true];

    return [
      'type' => 'object',
      'properties' => [
        'resultado' => ['type' => 'array', 'items' => ['type' => 'object']],
        'mensaje' => ['type' => 'string'],
        'errores' => ['type' => 'object', 'additionalProperties' => true],
        'detalle' => $detalle,
        'paginacion' => ['type' => 'object', 'additionalProperties' => true],
      ],
      'additionalProperties' => true,
    ];
  }

  protected function buildGenericResponseSchema(): array {
    return [
      'type' => 'object',
      'properties' => [
        'resultado' => ['type' => 'array', 'items' => ['type' => 'object']],
        'mensaje' => ['type' => 'string'],
        'errores' => ['type' => 'object', 'additionalProperties' => true],
        'detalle' => ['type' => 'object', 'additionalProperties' => true],
        'paginacion' => ['type' => 'object', 'additionalProperties' => true],
      ],
      'additionalProperties' => true,
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
