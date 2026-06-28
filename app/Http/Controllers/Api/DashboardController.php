<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $hoje = Carbon::today();

        return response()->json([
            'total_criados_hoje'              => Atendimento::whereDate('criado_em', $hoje)->count(),
            'em_andamento'                    => Atendimento::where('status', Atendimento::STATUS_EM_ATENDIMENTO)->count(),
            'aguardando'                      => Atendimento::where('status', Atendimento::STATUS_AGUARDANDO)->count(),
            'finalizados_hoje'                => Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
                                                             ->whereDate('finalizado_em', $hoje)
                                                             ->count(),
            'tempo_medio_espera_minutos'      => $this->tempoMedioEspera(),
            'tempo_medio_atendimento_minutos' => $this->tempoMedioAtendimento(),
            'atendentes_online'               => $this->atendentesOnline(),
            'volume_por_time'                 => $this->volumePorTime(),
            'volume_por_assunto'              => $this->volumePorAssunto(),
        ]);
    }

    private function tempoMedioEspera(): float|null
    {
        $driver = DB::connection()->getDriverName();

        $expr = match ($driver) {
            'sqlite' => 'AVG(CAST((julianday(iniciado_em) - julianday(entrou_na_fila_em)) * 1440 AS REAL))',
            'pgsql'  => 'AVG(EXTRACT(EPOCH FROM (iniciado_em::timestamp - entrou_na_fila_em::timestamp)) / 60)',
            default  => 'AVG(TIMESTAMPDIFF(MINUTE, entrou_na_fila_em, iniciado_em))',
        };

        $result = Atendimento::whereNotNull('entrou_na_fila_em')
            ->whereNotNull('iniciado_em')
            ->selectRaw("$expr as media")
            ->value('media');

        return $result !== null ? round((float) $result, 2) : null;
    }

    private function tempoMedioAtendimento(): float|null
    {
        $driver = DB::connection()->getDriverName();

        $expr = match ($driver) {
            'sqlite' => 'AVG(CAST((julianday(finalizado_em) - julianday(iniciado_em)) * 1440 AS REAL))',
            'pgsql'  => 'AVG(EXTRACT(EPOCH FROM (finalizado_em::timestamp - iniciado_em::timestamp)) / 60)',
            default  => 'AVG(TIMESTAMPDIFF(MINUTE, iniciado_em, finalizado_em))',
        };

        $result = Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
            ->whereNotNull('iniciado_em')
            ->whereNotNull('finalizado_em')
            ->selectRaw("$expr as media")
            ->value('media');

        return $result !== null ? round((float) $result, 2) : null;
    }

    private function atendentesOnline(): array
    {
        return Atendente::where('status', Atendente::STATUS_ONLINE)
            ->withCount([
                'atribuicoes as ativas_count' => fn($q) => $q->where('status', AtendimentoAtribuicao::STATUS_ATIVO),
            ])
            ->get()
            ->map(fn($a) => [
                'id'                           => $a->id,
                'nome'                         => $a->nome,
                'time_atendimento_id'          => $a->time_atendimento_id,
                'ativas_count'                 => $a->ativas_count,
                'max_atendimentos_simultaneos' => $a->max_atendimentos_simultaneos,
            ])
            ->toArray();
    }

    private function volumePorTime(): array
    {
        return Atendimento::join('times_atendimento', 'atendimentos.time_atendimento_id', '=', 'times_atendimento.id')
            ->selectRaw('times_atendimento.nome as time, COUNT(atendimentos.id) as total')
            ->groupBy('times_atendimento.nome')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    private function volumePorAssunto(): array
    {
        return Atendimento::join('assuntos', 'atendimentos.assunto_id', '=', 'assuntos.id')
            ->selectRaw('assuntos.nome as assunto, COUNT(atendimentos.id) as total')
            ->groupBy('assuntos.nome')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }
}
