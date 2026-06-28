<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use App\Models\Atendente;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class CargaAtendentesTable extends TableWidget
{
    protected ?string $pollingInterval = '5s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Carga dos atendentes')
            ->query(fn (): Builder => Atendente::query()
                ->where('status', Atendente::STATUS_ONLINE)
                ->with('time')
                ->withCount([
                    'atribuicoes as atendimentos_ativos_count' => fn (Builder $query): Builder => $query->where('status', 'ativo'),
                ])
                ->orderByDesc('atendimentos_ativos_count'))
            ->columns([
                TextColumn::make('nome')->label('Nome'),
                TextColumn::make('time.nome')->label('Time'),
                TextColumn::make('atendimentos_ativos_count')
                    ->label('Ativos')
                    ->formatStateUsing(fn ($state, Atendente $record): string => $record->atendimentos_ativos_count . '/' . $record->max_atendimentos_simultaneos),
                TextColumn::make('tempo_medio_dia')
                    ->label('Tempo médio do dia')
                    ->state(fn (Atendente $record): string => number_format((float) data_get(collect(app(DashboardMetricsService::class)->cargaAtendentes())->firstWhere('id', $record->id), 'tempo_medio_dia', 0), 1) . ' min'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('atendimentos_ativos_count', 'desc');
    }
}
