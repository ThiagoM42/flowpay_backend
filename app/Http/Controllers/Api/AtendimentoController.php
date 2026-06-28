<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessarFilaAtendimentoJob;
use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\AtendimentoEvento;
use App\Models\Cliente;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtendimentoController extends Controller
{
    public function __construct(
        private readonly AtendimentoDistribuicaoService $distribuicao
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'documento' => 'required|string|max:20',
            'assunto_id' => 'required|integer|exists:assuntos,id,ativo,1',
        ]);

        $assunto = Assunto::findOrFail($validated['assunto_id']);

        $cliente = Cliente::firstOrCreate(
            ['documento' => $validated['documento']],
            [
                'nome' => $validated['nome'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
            ]
        );

        $atendimento = Atendimento::create([
            'cliente_id' => $cliente->id,
            'assunto_id' => $assunto->id,
            'time_atendimento_id' => $assunto->time_atendimento_id,
            'status' => Atendimento::STATUS_AGUARDANDO,
        ]);

        $this->distribuicao->distribuir($atendimento);

        return response()->json(
            $atendimento->fresh()->load(['cliente', 'assunto', 'atribuicaoAtiva.atendente']),
            201
        );
    }

    public function show(Atendimento $atendimento): JsonResponse
    {
        return response()->json(
            $atendimento->load(['cliente', 'assunto', 'time', 'atribuicaoAtiva.atendente', 'eventos'])
        );
    }

    public function finalizar(Atendimento $atendimento): JsonResponse
    {
        if ($atendimento->status !== Atendimento::STATUS_EM_ATENDIMENTO) {
            return response()->json(
                ['error' => 'Atendimento não está em andamento.'],
                422
            );
        }

        $atribuicao = $atendimento->atribuicaoAtiva;
        if (!$atribuicao) {
            return response()->json(['error' => 'Nenhuma atribuição ativa encontrada.'], 422);
        }

        DB::transaction(function () use ($atendimento, $atribuicao) {
            $atribuicao->update([
                'status' => AtendimentoAtribuicao::STATUS_FINALIZADO,
                'finalizado_em' => now(),
            ]);

            $atendimento->update([
                'status' => Atendimento::STATUS_FINALIZADO,
                'finalizado_em' => now(),
            ]);

            AtendimentoEvento::create([
                'atendimento_id' => $atendimento->id,
                'tipo' => 'finalizado',
                'descricao' => 'Atendimento finalizado pelo atendente.',
                'dados' => ['atendente_id' => $atribuicao->atendente_id],
            ]);
        });

        ProcessarFilaAtendimentoJob::dispatch($atendimento->time_atendimento_id);

        return response()->json($atendimento->fresh());
    }

    public function transferir(Request $request, Atendimento $atendimento): JsonResponse
    {
        if ($atendimento->status !== Atendimento::STATUS_EM_ATENDIMENTO) {
            return response()->json(['error' => 'Atendimento não está em andamento.'], 422);
        }

        $validated = $request->validate([
            'atendente_id' => 'required|integer|exists:atendentes,id',
        ]);

        $novoAtendente = Atendente::findOrFail($validated['atendente_id']);

        if (!$novoAtendente->estaDisponivel()) {
            return response()->json(
                ['error' => "Atendente {$novoAtendente->nome} não está disponível ou atingiu o limite de atendimentos."],
                422
            );
        }

        $atribuicaoAtual = $atendimento->atribuicaoAtiva;
        if (!$atribuicaoAtual) {
            return response()->json(['error' => 'Nenhuma atribuição ativa encontrada.'], 422);
        }

        if ($novoAtendente->id === $atribuicaoAtual->atendente_id) {
            return response()->json(['error' => 'Atendimento já está com este atendente.'], 422);
        }

        try {
            DB::transaction(function () use ($atendimento, $atribuicaoAtual, $novoAtendente) {
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
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'limit_exceeded') {
                return response()->json(
                    ['error' => "Atendente {$novoAtendente->nome} não está disponível ou atingiu o limite de atendimentos."],
                    422
                );
            }
            throw $e;
        }

        return response()->json(
            $atendimento->fresh()->load('atribuicaoAtiva.atendente')
        );
    }
}
