<?php

namespace App\Services;

use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\AtendimentoEvento;
use App\Models\FilaAtendimento;
use Illuminate\Support\Facades\DB;

class AtendimentoDistribuicaoService
{
    public function distribuir(Atendimento $atendimento): void
    {
        $atendente = $this->encontrarAtendenteDisponivel($atendimento->time_atendimento_id);

        if (!$atendente) {
            $this->enfileirar($atendimento);
            return;
        }

        try {
            $this->atribuirAtendente($atendimento, $atendente);
        } catch (\RuntimeException) {
            $this->enfileirar($atendimento);
        }
    }

    public function encontrarAtendenteDisponivel(int $timeId): ?Atendente
    {
        $atendentes = Atendente::query()
            ->where('time_atendimento_id', $timeId)
            ->where('status', Atendente::STATUS_ONLINE)
            ->where('ativo', true)
            ->withCount([
                'atribuicoes as ativas_count' => fn($q) => $q->where('status', AtendimentoAtribuicao::STATUS_ATIVO),
            ])
            ->orderBy('ativas_count')
            ->get();

        return $atendentes->first(fn($a) => $a->ativas_count < $a->max_atendimentos_simultaneos);
    }

    public function atribuirAtendente(Atendimento $atendimento, Atendente $atendente): void
    {
        DB::transaction(function () use ($atendimento, $atendente) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                Atendente::where('id', $atendente->id)->lockForUpdate()->first();
            }
            $ativasCount = $atendente->atribuicoesAtivas()->count();
            if ($ativasCount >= $atendente->max_atendimentos_simultaneos) {
                throw new \RuntimeException(
                    "Atendente {$atendente->id} atingiu o limite de {$atendente->max_atendimentos_simultaneos} atendimentos simultâneos."
                );
            }

            AtendimentoAtribuicao::create([
                'atendimento_id' => $atendimento->id,
                'atendente_id'   => $atendente->id,
                'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
            ]);

            $atendimento->update([
                'status'      => Atendimento::STATUS_EM_ATENDIMENTO,
                'iniciado_em' => now(),
            ]);

            AtendimentoEvento::create([
                'atendimento_id' => $atendimento->id,
                'tipo'           => 'atribuido',
                'descricao'      => "Atendimento atribuído ao atendente {$atendente->nome}.",
                'dados'          => ['atendente_id' => $atendente->id],
            ]);
        });
    }

    private function enfileirar(Atendimento $atendimento): void
    {
        $atendimento->update([
            'status'            => Atendimento::STATUS_AGUARDANDO,
            'entrou_na_fila_em' => now(),
        ]);

        FilaAtendimento::create([
            'atendimento_id'      => $atendimento->id,
            'time_atendimento_id' => $atendimento->time_atendimento_id,
            'status'              => FilaAtendimento::STATUS_AGUARDANDO,
        ]);

        AtendimentoEvento::create([
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'enfileirado',
            'descricao'      => 'Nenhum atendente disponível. Atendimento adicionado à fila.',
        ]);
    }
}
