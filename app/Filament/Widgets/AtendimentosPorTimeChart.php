<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;

class AtendimentosPorTimeChart extends ChartWidget
{
    protected ?string $heading = 'Atendimentos por time';

    protected ?string $pollingInterval = '15s';

    public ?string $filter = 'today';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hoje',
            'week' => 'Semana',
            'month' => 'Mês',
        ];
    }

    protected function getData(): array
    {
        $rows = app(DashboardMetricsService::class)->atendimentosPorTime($this->filter ?? 'today');

        return [
            'labels' => array_column($rows, 'label'),
            'datasets' => [[
                'label' => 'Atendimentos',
                'data' => array_column($rows, 'value'),
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
