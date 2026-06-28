<?php

namespace App\Services;

use App\Jobs\ProcessarFilaAtendimentoJob;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\AtendimentoEvento;
use App\Models\FilaAtendimento;
use Illuminate\Support\Facades\DB;

class AtendimentoOperacaoService
{
    public function __construct(
        private readonly AtendimentoDistribuicaoService $distribuicao,
    ) {}

    public function atribuir(Atendimento $atendimento, Atendente $atendente): void
    {
        DB::transaction(function () use ($atendimento, $atendente): void {
            $this->encerrarFilaSeExistir($atendimento, FilaAtendimento::STATUS_PROCESSADO);

            $atribuicaoAtual = $atendimento->atribuicaoAtiva;

            if ($atribuicaoAtual) {
                $atribuicaoAtual->update([
                    'status' => AtendimentoAtribuicao::STATUS_TRANSFERIDO,
                    'finalizado_em' => now(),
                ]);
            }

            AtendimentoAtribuicao::create([
                'atendimento_id' => $atendimento->id,
                'atendente_id' => $atendente->id,
                'status' => AtendimentoAtribuicao::STATUS_ATIVO,
            ]);

            $atendimento->update([
                'status' => Atendimento::STATUS_EM_ATENDIMENTO,
                'iniciado_em' => $atendimento->iniciado_em ?? now(),
            ]);

            AtendimentoEvento::create([
                'atendimento_id' => $atendimento->id,
                'tipo' => 'atribuido_manual',
                'descricao' => "Atendimento atribuído manualmente para {$atendente->nome}.",
                'dados' => ['atendente_id' => $atendente->id],
            ]);
        });
    }

    public function transferir(Atendimento $atendimento, Atendente $novoAtendente): void
    {
        $atribuicaoAtual = $atendimento->atribuicaoAtiva;

        if (! $atribuicaoAtual) {
            throw new \RuntimeException('Nenhuma atribuição ativa encontrada.');
        }

        DB::transaction(function () use ($atendimento, $atribuicaoAtual, $novoAtendente): void {
            $query = $novoAtendente->atribuicoesAtivas();

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            if ($query->count() >= $novoAtendente->max_atendimentos_simultaneos) {
                throw new \RuntimeException('limit_exceeded');
            }

            $atribuicaoAtual->update([
                'status' => AtendimentoAtribuicao::STATUS_TRANSFERIDO,
                'finalizado_em' => now(),
            ]);

            AtendimentoAtribuicao::create([
                'atendimento_id' => $atendimento->id,
                'atendente_id' => $novoAtendente->id,
                'status' => AtendimentoAtribuicao::STATUS_ATIVO,
            ]);

            AtendimentoEvento::create([
                'atendimento_id' => $atendimento->id,
                'tipo' => 'transferido',
                'descricao' => "Atendimento transferido para {$novoAtendente->nome}.",
                'dados' => [
                    'atendente_anterior_id' => $atribuicaoAtual->atendente_id,
                    'atendente_novo_id' => $novoAtendente->id,
                ],
            ]);
        });
    }

    public function finalizar(Atendimento $atendimento): void
    {
        DB::transaction(function () use ($atendimento): void {
            $atribuicao = $atendimento->atribuicaoAtiva;

            if ($atribuicao) {
                $atribuicao->update([
                    'status' => AtendimentoAtribuicao::STATUS_FINALIZADO,
                    'finalizado_em' => now(),
                ]);
            }

            $this->encerrarFilaSeExistir($atendimento, FilaAtendimento::STATUS_PROCESSADO);

            $atendimento->update([
                'status' => Atendimento::STATUS_FINALIZADO,
                'finalizado_em' => now(),
            ]);

            AtendimentoEvento::create([
                'atendimento_id' => $atendimento->id,
                'tipo' => 'finalizado',
                'descricao' => 'Atendimento finalizado.',
                'dados' => ['atendente_id' => $atribuicao?->atendente_id],
            ]);
        });

        ProcessarFilaAtendimentoJob::dispatch($atendimento->time_atendimento_id);
    }

    public function cancelar(Atendimento $atendimento): void
    {
        DB::transaction(function () use ($atendimento): void {
            $atribuicao = $atendimento->atribuicaoAtiva;

            if ($atribuicao) {
                $atribuicao->update([
                    'status' => AtendimentoAtribuicao::STATUS_FINALIZADO,
                    'finalizado_em' => now(),
                ]);
            }

            $this->encerrarFilaSeExistir($atendimento, FilaAtendimento::STATUS_CANCELADO);

            $atendimento->update([
                'status' => Atendimento::STATUS_CANCELADO,
                'finalizado_em' => now(),
            ]);

            AtendimentoEvento::create([
                'atendimento_id' => $atendimento->id,
                'tipo' => 'cancelado',
                'descricao' => 'Atendimento cancelado.',
                'dados' => ['atendente_id' => $atribuicao?->atendente_id],
            ]);
        });
    }

    private function encerrarFilaSeExistir(Atendimento $atendimento, string $status): void
    {
        $atendimento->filaAtendimento()?->update([
            'status' => $status,
        ]);
    }
}