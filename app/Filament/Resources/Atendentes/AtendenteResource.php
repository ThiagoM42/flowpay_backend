<?php

namespace App\Filament\Resources\Atendentes;

use App\Filament\Resources\Atendentes\Pages\CreateAtendente;
use App\Filament\Resources\Atendentes\Pages\EditAtendente;
use App\Filament\Resources\Atendentes\Pages\ListAtendentes;
use App\Filament\Resources\Atendentes\Pages\ViewAtendente;
use App\Models\Atendente;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AtendenteResource extends Resource
{
    protected static ?string $model = Atendente::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('time_atendimento_id')
                ->relationship('time', 'nome')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('nome')->required(),
            TextInput::make('email')->email()->required(),
            Radio::make('status')
                ->options([
                    Atendente::STATUS_ONLINE => 'Online',
                    Atendente::STATUS_OFFLINE => 'Offline',
                    Atendente::STATUS_PAUSADO => 'Pausado',
                ])
                ->inline()
                ->required(),
            TextInput::make('max_atendimentos_simultaneos')->numeric()->default(3)->required(),
            Toggle::make('ativo')->default(true),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('nome'),
            TextEntry::make('email'),
            TextEntry::make('time.nome')->label('Time'),
            TextEntry::make('status')->badge(),
            TextEntry::make('max_atendimentos_simultaneos'),
            TextEntry::make('ativo')
                ->badge()
                ->formatStateUsing(fn (bool $state): string => $state ? 'Ativo' : 'Inativo'),
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
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('time.nome')->label('Time')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Atendente::STATUS_ONLINE => 'Online',
                        Atendente::STATUS_PAUSADO => 'Pausado',
                        default => 'Offline',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Atendente::STATUS_ONLINE => 'success',
                        Atendente::STATUS_PAUSADO => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('atendimentos_ativos_count')
                    ->label('Ativos')
                    ->badge()
                    ->formatStateUsing(fn ($state, Atendente $record): string => $state . '/' . $record->max_atendimentos_simultaneos)
                    ->color(fn ($state, Atendente $record): string => ((int) $state >= $record->max_atendimentos_simultaneos) ? 'danger' : 'success'),
                TextColumn::make('ativo')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sim' : 'Não')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('max_atendimentos_simultaneos')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('time_atendimento_id')->label('Time')->relationship('time', 'nome'),
                SelectFilter::make('status')->options([
                    Atendente::STATUS_ONLINE => 'Online',
                    Atendente::STATUS_OFFLINE => 'Offline',
                    Atendente::STATUS_PAUSADO => 'Pausado',
                ]),
                SelectFilter::make('ativo')->options([
                    1 => 'Ativo',
                    0 => 'Inativo',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('toggleStatus')
                    ->label(fn (Atendente $record): string => $record->status === Atendente::STATUS_PAUSADO ? 'Retomar' : 'Pausar')
                    ->icon(fn (Atendente $record): string => $record->status === Atendente::STATUS_PAUSADO ? 'heroicon-o-play' : 'heroicon-o-pause')
                    ->color(fn (Atendente $record): string => $record->status === Atendente::STATUS_PAUSADO ? 'success' : 'warning')
                    ->action(function (Atendente $record): void {
                        $record->update([
                            'status' => $record->status === Atendente::STATUS_PAUSADO ? Atendente::STATUS_ONLINE : Atendente::STATUS_PAUSADO,
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('time')
            ->withCount([
                'atribuicoes as atendimentos_ativos_count' => fn (Builder $query): Builder => $query->where('status', 'ativo'),
            ])
            ->orderBy('nome');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtendentes::route('/'),
            'create' => CreateAtendente::route('/create'),
            'view' => ViewAtendente::route('/{record}'),
            'edit' => EditAtendente::route('/{record}/edit'),
        ];
    }
}
