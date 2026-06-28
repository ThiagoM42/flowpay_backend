<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AtendentesOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class)->attendants();

        return [
            Stat::make('Online', $metrics['online'])->icon('heroicon-o-signal')->chart([$metrics['online']]),
            Stat::make('Pausados', $metrics['pausados'])->icon('heroicon-o-pause')->chart([$metrics['pausados']]),
            Stat::make('Slots livres', $metrics['slots_livres'])->icon('heroicon-o-square-3-stack-3d')->chart([$metrics['slots_livres']]),
            Stat::make('Ocupação', $metrics['taxa_ocupacao'] . '%')->icon('heroicon-o-chart-bar')->chart([$metrics['taxa_ocupacao']]),
        ];
    }
}
