<?php

use yii\db\Migration;

/**
 * Class m231004_195249_mailer
 */
class m231004_195249_mailer extends Migration {

  /**
   * {@inheritdoc}
   */
  public function safeUp() {

    $this->createTable("NotificacionCorreo", [
      "id" => $this->primaryKey(),
      "receptor" => $this->json()->notNull(),
      "asunto" => $this->string()->notNull(),
      "cuerpo" => $this->text()->notNull(),
      "estatus" => $this->string(20),
      "detalle" => $this->text(),
      "prioridad" => $this->smallInteger()->comment("1, 2 o 3"),
      "enviado" => $this->timestamp() . ' with time zone',
      "creado" => $this->timestamp() . ' with time zone',
      "modificado" => $this->timestamp() . ' with time zone',
      "eliminado" => $this->timestamp() . ' with time zone',
    ]);

    $this->createTable("NotificacionCorreoAdjunto", [
      "id" => $this->primaryKey(),
      "idNotificacionCorreo" => $this->integer(),
      "ruta" => $this->string()
    ]);

    $this->addForeignKey("NCAidNotificacionCorreoFK", "NotificacionCorreoAdjunto", "idNotificacionCorreo", "NotificacionCorreo", "id");

  }

  /**
   * {@inheritdoc}
   */
  public function safeDown() {
    $this->dropForeignKey("NCAidNotificacionCorreoFK", "NotificacionCorreoAdjunto");
    $this->dropTable("NotificacionCorreoAdjunto");
    $this->dropTable("NotificacionCorreo");
  }
}
