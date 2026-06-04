<?php

namespace eDesarrollos\openapi\comandos;

use eDesarrollos\openapi\Generator;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class OpenapiController extends Controller {
  public function actionGenerar(): int {
    $config = Yii::$app->params['openapi'] ?? [];
    $generator = new Generator($config);

    $outputPath = Yii::getAlias($config['output'] ?? '@app/publico/openapi.json');
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
      mkdir($outputDir, 0777, true);
    }

    file_put_contents($outputPath, Json::encode($generator->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $this->stdout("OpenAPI generado en {$outputPath}\n");

    $scalarPath = Yii::getAlias($config['scalar']['output'] ?? '@app/publico/scalar.html');
    $scalarDir = dirname($scalarPath);
    if (!is_dir($scalarDir)) {
      mkdir($scalarDir, 0777, true);
    }

    file_put_contents($scalarPath, $generator->renderScalarHtml());
    $this->stdout("Scalar generado en {$scalarPath}\n");

    return ExitCode::OK;
  }
}
