<?php

namespace eDesarrollos\cron;

class ScheduledJob {
  public $command;
  public $expression = '* * * * *';

  public function __construct($command) {
    $this->command = $command;
  }

  public function everyMinute() {
    $this->expression = '* * * * *';
    return $this;
  }

  public function hourly() {
    $this->expression = '0 * * * *';
    return $this;
  }

  public function daily($hour = 0, $minute = 0) {
    $this->expression = "$minute $hour * * *";
    return $this;
  }

  public function cron($expression) {
    $this->expression = $expression;
    return $this;
  }
}
