<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TemposMedios extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class)->temposMedios();

        return [
            Stat::make('Espera média', number_format((float) ($metrics['espera_atual'] ?? 0), 1) . ' min')
                ->chart([$metrics['espera_atual'] ?? 0, $metrics['espera_anterior'] ?? 0]),
            Stat::make('Atendimento médio', number_format((float) ($metrics['atendimento_atual'] ?? 0), 1) . ' min')
                ->chart([$metrics['atendimento_atual'] ?? 0, $metrics['atendimento_anterior'] ?? 0]),
            Stat::make('Total médio', number_format((float) ($metrics['total_atual'] ?? 0), 1) . ' min')
                ->chart([$metrics['total_atual'] ?? 0, $metrics['total_anterior'] ?? 0]),
        ];
    }
}
