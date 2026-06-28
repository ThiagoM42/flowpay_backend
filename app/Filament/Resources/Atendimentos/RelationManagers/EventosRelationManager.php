<?php

namespace App\Filament\Resources\Atendimentos\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventosRelationManager extends RelationManager
{
    protected static string $relationship = 'eventos';

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
                TextColumn::make('tipo')->badge(),
                TextColumn::make('descricao')->wrap()->searchable(),
                TextColumn::make('criado_em')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
