<?php

namespace App\Console\Commands;

use App\Models\FilaAtendimento;
use App\Services\FilaAtendimentoService;
use Illuminate\Console\Command;

class ProcessarFilasPendentesCommand extends Command
{
    protected $signature = 'atendimento:processar-filas';
    protected $description = 'Verifica filas pendentes e atribui atendentes disponíveis';

    public function handle(FilaAtendimentoService $service): void
    {
        $times = FilaAtendimento::query()
            ->where('status', FilaAtendimento::STATUS_AGUARDANDO)
            ->distinct()
            ->pluck('time_atendimento_id');

        if ($times->isEmpty()) {
            return;
        }

        foreach ($times as $timeId) {
            $service->processarProximo($timeId);
        }
    }
}
