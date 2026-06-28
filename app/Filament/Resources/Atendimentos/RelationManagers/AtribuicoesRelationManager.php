<?php

namespace App\Filament\Resources\Atendimentos\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;

class AtribuicoesRelationManager extends RelationManager
{
    protected static string $relationship = 'atribuicoes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('criado_em', 'desc')
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('atendente.nome')->label('Atendente')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('criado_em')->dateTime()->sortable(),
                TextColumn::make('finalizado_em')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
