<?php

namespace App\Filament\Resources\Assuntos;

use App\Filament\Resources\Assuntos\Pages\CreateAssunto;
use App\Filament\Resources\Assuntos\Pages\EditAssunto;
use App\Filament\Resources\Assuntos\Pages\ListAssuntos;
use App\Filament\Resources\Assuntos\Pages\ViewAssunto;
use App\Models\Assunto;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AssuntoResource extends Resource
{
    protected static ?string $model = Assunto::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('time_atendimento_id')
                ->relationship('time', 'nome')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('nome')->required(),
            Toggle::make('ativo')->default(true),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('nome'),
            TextEntry::make('time.nome')->label('Time'),
            IconEntry::make('ativo')->boolean(),
            TextEntry::make('created_at')->dateTime()->placeholder('-'),
            TextEntry::make('updated_at')->dateTime()->placeholder('-'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('time.nome')->label('Time')->searchable()->sortable(),
                TextColumn::make('ativo')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ativo' : 'Inativo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('time_atendimento_id')
                    ->label('Time')
                    ->relationship('time', 'nome'),
                SelectFilter::make('ativo')
                    ->options([
                        1 => 'Ativo',
                        0 => 'Inativo',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('time')->orderBy('nome');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssuntos::route('/'),
            'create' => CreateAssunto::route('/create'),
            'view' => ViewAssunto::route('/{record}'),
            'edit' => EditAssunto::route('/{record}/edit'),
        ];
    }
}
