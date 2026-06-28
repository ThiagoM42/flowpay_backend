<?php

namespace App\Services;

use App\Models\FilaAtendimento;
use Illuminate\Support\Facades\DB;

class FilaAtendimentoService
{
    public function __construct(
        private readonly AtendimentoDistribuicaoService $distribuicao
    ) {}

    public function processarProximo(int $timeAtendimentoId): void
    {
        while (true) {
            $entrada = FilaAtendimento::query()
                ->where('time_atendimento_id', $timeAtendimentoId)
                ->where('status', FilaAtendimento::STATUS_AGUARDANDO)
                ->orderBy('entrou_em')
                ->with('atendimento')
                ->first();

            if (!$entrada) {
                return;
            }

            $atendente = $this->distribuicao->encontrarAtendenteDisponivel($timeAtendimentoId);

            if (!$atendente) {
                return;
            }

            DB::transaction(function () use ($entrada, $atendente) {
                $entrada->update(['status' => FilaAtendimento::STATUS_PROCESSADO]);
                $this->distribuicao->atribuirAtendente($entrada->atendimento, $atendente);
            });
        }
    }
}
