<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class PostgresProcessRunner
{
    public function run(array $command, array $environment, int $timeout): Process
    {
        $process = new Process($command, null, $environment);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }
}
