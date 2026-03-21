<?php

namespace eDesarrollos\cron;

class Scheduler {
  private $jobs = [];

  public function command($route) {
    $job = new ScheduledJob($route);
    $this->jobs[] = $job;
    return $job;
  }

  public function getJobs() {
    return $this->jobs;
  }
}
