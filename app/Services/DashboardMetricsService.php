<?php

namespace App\Services;

use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function overview(): array
    {
        return $this->cache('dashboard:overview', 20, function (): array {
            $hoje = now()->toDateString();

            return [
                'criados_hoje' => Atendimento::whereDate('criado_em', $hoje)->count(),
                'em_andamento' => Atendimento::where('status', Atendimento::STATUS_EM_ATENDIMENTO)->count(),
                'aguardando' => Atendimento::where('status', Atendimento::STATUS_AGUARDANDO)->count(),
                'finalizados_hoje' => Atendimento::where('status', Atendimento::STATUS_FINALIZADO)->whereDate('finalizado_em', $hoje)->count(),
                'series_7_dias' => $this->serieUltimos7Dias(),
            ];
        });
    }

    public function attendants(): array
    {
        return $this->cache('dashboard:attendants', 20, function (): array {
            $base = Atendente::query()->withCount([
                'atribuicoes as atendimentos_ativos_count' => fn ($query) => $query->where('status', AtendimentoAtribuicao::STATUS_ATIVO),
            ]);

            $online = (clone $base)->where('status', Atendente::STATUS_ONLINE)->count();
            $pausados = (clone $base)->where('status', Atendente::STATUS_PAUSADO)->count();
            $slotsLivres = (clone $base)->get()->sum(fn (Atendente $atendente): int => max($atendente->max_atendimentos_simultaneos - (int) $atendente->atendimentos_ativos_count, 0));
            $ocupacao = (clone $base)->get();

            $slotsTotais = $ocupacao->sum('max_atendimentos_simultaneos') ?: 1;
            $slotsOcupados = $ocupacao->sum('atendimentos_ativos_count');

            return [
                'online' => $online,
                'pausados' => $pausados,
                'slots_livres' => $slotsLivres,
                'taxa_ocupacao' => round(($slotsOcupados / $slotsTotais) * 100, 1),
            ];
        });
    }

    public function temposMedios(): array
    {
        return $this->cache('dashboard:tempos-medios', 20, function (): array {
            return [
                'espera_atual' => $this->mediaTempoEspera(now()->subDays(7), now()),
                'atendimento_atual' => $this->mediaTempoAtendimento(now()->subDays(7), now()),
                'total_atual' => $this->mediaTempoTotal(now()->subDays(7), now()),
                'espera_anterior' => $this->mediaTempoEspera(now()->subDays(14), now()->subDays(7)),
                'atendimento_anterior' => $this->mediaTempoAtendimento(now()->subDays(14), now()->subDays(7)),
                'total_anterior' => $this->mediaTempoTotal(now()->subDays(14), now()->subDays(7)),
            ];
        });
    }

    public function atendimentosPorTime(string $periodo = 'today'): array
    {
        return $this->cache("dashboard:time:{$periodo}", 20, function () use ($periodo): array {
            [$inicio, $fim] = $this->periodo($periodo);

            return Atendimento::query()
                ->join('times_atendimento', 'atendimentos.time_atendimento_id', '=', 'times_atendimento.id')
                ->whereBetween('atendimentos.criado_em', [$inicio, $fim])
                ->groupBy('times_atendimento.nome')
                ->orderByDesc(DB::raw('COUNT(atendimentos.id)'))
                ->get([
                    DB::raw('times_atendimento.nome as label'),
                    DB::raw('COUNT(atendimentos.id) as value'),
                ])
                ->toArray();
        });
    }

    public function atendimentosPorAssunto(string $periodo = 'week'): array
    {
        return $this->cache("dashboard:assunto:{$periodo}", 20, function () use ($periodo): array {
            [$inicio, $fim] = $this->periodo($periodo);

            return Atendimento::query()
                ->join('assuntos', 'atendimentos.assunto_id', '=', 'assuntos.id')
                ->whereBetween('atendimentos.criado_em', [$inicio, $fim])
                ->groupBy('assuntos.nome')
                ->orderByDesc(DB::raw('COUNT(atendimentos.id)'))
                ->limit(10)
                ->get([
                    DB::raw('assuntos.nome as label'),
                    DB::raw('COUNT(atendimentos.id) as value'),
                ])
                ->toArray();
        });
    }

    public function ultimos7Dias(): array
    {
        return $this->cache('dashboard:ultimos7dias', 20, fn (): array => $this->serieUltimos7Dias());
    }

    public function cargaAtendentes(): array
    {
        return $this->cache('dashboard:carga-atendentes', 15, function (): array {
            return Atendente::query()
                ->where('status', Atendente::STATUS_ONLINE)
                ->with('time')
                ->withCount([
                    'atribuicoes as atendimentos_ativos_count' => fn ($query) => $query->where('status', AtendimentoAtribuicao::STATUS_ATIVO),
                ])
                ->get()
                ->map(fn (Atendente $atendente): array => [
                    'id' => $atendente->id,
                    'nome' => $atendente->nome,
                    'time' => $atendente->time?->nome,
                    'ativos' => $atendente->atendimentos_ativos_count,
                    'max' => $atendente->max_atendimentos_simultaneos,
                    'status' => $atendente->status,
                    'tempo_medio_dia' => $this->tempoMedioAtendenteNoDia($atendente),
                ])
                ->sortByDesc('ativos')
                ->values()
                ->toArray();
        });
    }

    public function filaAtual(): array
    {
        return $this->cache('dashboard:fila-atual', 15, function (): array {
            return Atendimento::query()
                ->where('status', Atendimento::STATUS_AGUARDANDO)
                ->with(['cliente', 'assunto', 'time'])
                ->orderBy('entrou_na_fila_em')
                ->get()
                ->map(fn (Atendimento $atendimento): array => [
                    'id' => $atendimento->id,
                    'time' => $atendimento->time?->nome,
                    'cliente' => $atendimento->cliente?->nome,
                    'assunto' => $atendimento->assunto?->nome,
                    'prioridade' => $atendimento->prioridade,
                    'entrou_na_fila_em' => $atendimento->entrou_na_fila_em,
                ])
                ->toArray();
        });
    }

    private function cache(string $key, int $seconds, callable $callback): array
    {
        return Cache::store($this->cacheStore())
            ->remember($key, now()->addSeconds($seconds), $callback);
    }

    private function cacheStore(): string
    {
        return array_key_exists('redis', config('cache.stores', [])) ? 'redis' : config('cache.default');
    }

    private function periodo(string $periodo): array
    {
        return match ($periodo) {
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function serieUltimos7Dias(): array
    {
        $labels = [];
        $criados = [];
        $finalizados = [];

        for ($days = 6; $days >= 0; $days--) {
            $data = CarbonImmutable::today()->subDays($days);
            $labels[] = $data->format('d/m');
            $criados[] = Atendimento::whereDate('criado_em', $data)->count();
            $finalizados[] = Atendimento::where('status', Atendimento::STATUS_FINALIZADO)->whereDate('finalizado_em', $data)->count();
        }

        return [
            'labels' => $labels,
            'criados' => $criados,
            'finalizados' => $finalizados,
        ];
    }

    private function mediaTempoEspera($inicio, $fim): ?float
    {
        $driver = DB::connection()->getDriverName();

        $expr = match ($driver) {
            'sqlite' => 'AVG(CAST((julianday(iniciado_em) - julianday(entrou_na_fila_em)) * 1440 AS REAL))',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (iniciado_em::timestamp - entrou_na_fila_em::timestamp)) / 60)',
            default => 'AVG(TIMESTAMPDIFF(MINUTE, entrou_na_fila_em, iniciado_em))',
        };

        $result = Atendimento::whereNotNull('entrou_na_fila_em')
            ->whereNotNull('iniciado_em')
            ->whereBetween('criado_em', [$inicio, $fim])
            ->selectRaw("$expr as media")
            ->value('media');

        return $result !== null ? round((float) $result, 2) : null;
    }

    private function mediaTempoAtendimento($inicio, $fim): ?float
    {
        $driver = DB::connection()->getDriverName();

        $expr = match ($driver) {
            'sqlite' => 'AVG(CAST((julianday(finalizado_em) - julianday(iniciado_em)) * 1440 AS REAL))',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (finalizado_em::timestamp - iniciado_em::timestamp)) / 60)',
            default => 'AVG(TIMESTAMPDIFF(MINUTE, iniciado_em, finalizado_em))',
        };

        $result = Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
            ->whereNotNull('iniciado_em')
            ->whereNotNull('finalizado_em')
            ->whereBetween('criado_em', [$inicio, $fim])
            ->selectRaw("$expr as media")
            ->value('media');

        return $result !== null ? round((float) $result, 2) : null;
    }

    private function mediaTempoTotal($inicio, $fim): ?float
    {
        $driver = DB::connection()->getDriverName();

        $expr = match ($driver) {
            'sqlite' => 'AVG(CAST((julianday(finalizado_em) - julianday(criado_em)) * 1440 AS REAL))',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (finalizado_em::timestamp - criado_em::timestamp)) / 60)',
            default => 'AVG(TIMESTAMPDIFF(MINUTE, criado_em, finalizado_em))',
        };

        $result = Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
            ->whereNotNull('finalizado_em')
            ->whereBetween('criado_em', [$inicio, $fim])
            ->selectRaw("$expr as media")
            ->value('media');

        return $result !== null ? round((float) $result, 2) : null;
    }

    private function tempoMedioAtendenteNoDia(Atendente $atendente): ?float
    {
        $driver = DB::connection()->getDriverName();

        $expr = match ($driver) {
            'sqlite' => 'AVG(CAST((julianday(atendimento_eventos.criado_em) - julianday(atendimentos.criado_em)) * 1440 AS REAL))',
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (atendimento_eventos.criado_em::timestamp - atendimentos.criado_em::timestamp)) / 60)',
            default => 'AVG(TIMESTAMPDIFF(MINUTE, atendimentos.criado_em, atendimento_eventos.criado_em))',
        };

        $result = Atendimento::query()
            ->join('atendimento_atribuicoes', 'atendimentos.id', '=', 'atendimento_atribuicoes.atendimento_id')
            ->join('atendimento_eventos', 'atendimentos.id', '=', 'atendimento_eventos.atendimento_id')
            ->where('atendimento_atribuicoes.atendente_id', $atendente->id)
            ->whereDate('atendimentos.criado_em', now())
            ->selectRaw("$expr as media")
            ->value('media');

        return $result !== null ? round((float) $result, 2) : null;
    }
}