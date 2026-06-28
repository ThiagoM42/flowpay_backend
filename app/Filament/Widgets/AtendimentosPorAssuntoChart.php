<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;

class AtendimentosPorAssuntoChart extends ChartWidget
{
    protected ?string $heading = 'Top assuntos';

    protected ?string $pollingInterval = '30s';

    public ?string $filter = 'week';

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
        $rows = app(DashboardMetricsService::class)->atendimentosPorAssunto($this->filter ?? 'week');

        return [
            'labels' => array_reverse(array_column($rows, 'label')),
            'datasets' => [[
                'label' => 'Atendimentos',
                'data' => array_reverse(array_column($rows, 'value')),
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
