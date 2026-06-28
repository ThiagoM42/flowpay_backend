<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use App\Models\Atendimento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class FilaAtualTable extends TableWidget
{
    protected ?string $pollingInterval = '5s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Fila atual')
            ->query(fn (): Builder => Atendimento::query()
                ->where('status', Atendimento::STATUS_AGUARDANDO)
                ->with(['cliente', 'assunto', 'time'])
                ->orderBy('entrou_na_fila_em'))
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('time.nome')->label('Time'),
                TextColumn::make('cliente.nome')->label('Cliente'),
                TextColumn::make('assunto.nome')->label('Assunto'),
                TextColumn::make('prioridade')->badge(),
                TextColumn::make('entrou_na_fila_em')
                    ->label('Em espera')
                    ->formatStateUsing(fn ($state): string => $state ? Carbon::parse($state)->diffForHumans(now(), true) : '-'),
            ])
            ->defaultSort('entrou_na_fila_em');
    }
}
