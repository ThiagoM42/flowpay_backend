<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AtendimentosOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class)->overview();
        $series = $metrics['series_7_dias'];

        return [
            Stat::make('Criados hoje', $metrics['criados_hoje'])
                ->description('Últimos 7 dias')
                ->icon('heroicon-o-plus-circle')
                ->chart($series['criados']),
            Stat::make('Em andamento', $metrics['em_andamento'])
                ->icon('heroicon-o-arrow-path')
                ->chart($series['criados']),
            Stat::make('Na fila', $metrics['aguardando'])
                ->icon('heroicon-o-clock')
                ->chart($series['criados']),
            Stat::make('Finalizados hoje', $metrics['finalizados_hoje'])
                ->icon('heroicon-o-check-circle')
                ->chart($series['finalizados']),
        ];
    }
}
