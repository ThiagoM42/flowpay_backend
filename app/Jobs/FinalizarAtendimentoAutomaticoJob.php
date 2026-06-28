<?php

namespace App\Jobs;

use App\Models\Atendimento;
use App\Services\AtendimentoOperacaoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizarAtendimentoAutomaticoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $atendimentoId
    ) {}

    public function handle(AtendimentoOperacaoService $service): void
    {
        $atendimento = Atendimento::find($this->atendimentoId);

        if ($atendimento?->status !== Atendimento::STATUS_EM_ATENDIMENTO) {
            return;
        }

        $service->finalizar($atendimento);
    }
}
