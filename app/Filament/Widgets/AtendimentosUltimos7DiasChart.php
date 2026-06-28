<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;

class AtendimentosUltimos7DiasChart extends ChartWidget
{
    protected ?string $heading = 'Últimos 7 dias';

    protected ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $series = app(DashboardMetricsService::class)->ultimos7Dias();

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => 'Criados',
                    'data' => $series['criados'],
                ],
                [
                    'label' => 'Finalizados',
                    'data' => $series['finalizados'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
