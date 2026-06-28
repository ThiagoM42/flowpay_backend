<?php

namespace App\Jobs;

use App\Services\FilaAtendimentoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessarFilaAtendimentoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $timeAtendimentoId
    ) {}

    public function handle(FilaAtendimentoService $service): void
    {
        $service->processarProximo($this->timeAtendimentoId);
    }
}
