#### Ejecutar la migración

```
php yii migrate --migrationPath=@app/modules/mail/migrations
```

### Revisar que la configuración para envío de correo este correcto

```
$config = [
  // ..
  'components' => [
    // ...
    'mailer' => [
      'class' => 'yii\swiftmailer\Mailer',
      'useFileTransport' => false,
      'transport' => [
        'class' => 'Swift_SmtpTransport',
        'host' => 'smtp.gmail.com',
        'username' => 'correo@gmail.com',
        'password' => 'contraseña',
        'port' => '587',
        'encryption' => 'tls',
      ],
    ],
  ]
  // ...
];
```

#### Correr el comando de migración

Agregar al archivo config/console.php las siguientes líneas

```
$config['bootstrap'][] = 'mail';
$config['modules']['mail'] = ['class' => 'app\modules\mail\Controller'];
```

#### Ejecutar el comando para enviar el correo
```
php yii mail/cron
```

#### Guardar notificaciones

##### Guardado manual de notificación
```
$modelo = NotificacionCorreo::crear($prioridad = 3)
  ->setReceptor(["mail@gmail.com" => "Nombre del Receptor"])
  ->setAsunto("Asunto del correo")
  ->setCuerpo("Cuerpo del corre puede ser <strong>HTML</strong>")

if($modelo->save()) {
  echo "Guardado correcto";
}
```

##### Guardado desde un arreglo
```
$parametros = [
  "prioridad" => NotificacionCorreo::PRIORIDAD_3,
  "asunto" => "Asunto del correo",
  "cuerpo" => "Este es el cuerpo del correo, puede ser <strong>HTML</strong>",
  "receptores" => [
    "hquijada@edesarrollos.com"
  ],
  "adjuntos" => [# Debe contener la(s) ruta(s) al(los) archivo(s)
  ]
];

$resultado = NotificacionCorreo::enviarMultiple($parametros);
```