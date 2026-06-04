#### Ejecutar la migración

```
php yii migrate --migrationPath=@eDesarrollos/mail/migraciones
```

### Revisar que la configuración para envío de correo este correcto

```
$config = [
  // ..
  'components' => [
    // ...
    'mailer' => [
      'class' => \yii\symfonymailer\Mailer::class,
      'viewPath' => '@app/mail',
      'useFileTransport' => false,
      'transport' => [
        'scheme' => 'smtp',
        'host' => 'smtp.gmail.com',
        'username' => 'correo@gmail.com',
        'password' => 'contraseña',
        'port' => 587,
      ],
    ],
  ]
  // ...
];
```

#### Correr el comando de migración

Agregar al archivo config/consola.php las siguientes líneas

```
$config['bootstrap'][] = 'mail';
$config['modules']['mail'] = ['class' => 'eDesarrollos\mail\Module'];
```

#### Ejecutar el comando para enviar el correo
```
php yii mail/cron
```

Si deseas personalizar la plantilla HTML de los correos, crea o modifica `@app/mail/layouts/html.php`.

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
