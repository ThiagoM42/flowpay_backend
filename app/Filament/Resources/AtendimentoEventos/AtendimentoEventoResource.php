<?php

namespace App\Filament\Resources\AtendimentoEventos;

use App\Filament\Resources\AtendimentoEventos\Pages\ListAtendimentoEventos;
use App\Models\AtendimentoEvento;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AtendimentoEventoResource extends Resource
{
    protected static ?string $model = AtendimentoEvento::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Auditoria';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('atendimento.id')->label('Atendimento'),
            TextEntry::make('tipo')->badge(),
            TextEntry::make('descricao'),
            TextEntry::make('dados')->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-'),
            TextEntry::make('criado_em')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('criado_em', 'desc')
            ->columns([
                TextColumn::make('atendimento.id')->label('Atendimento')->searchable()->sortable(),
                TextColumn::make('tipo')->badge()->searchable(),
                TextColumn::make('descricao')->searchable(),
                TextColumn::make('criado_em')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['atendimento'])->orderByDesc('criado_em');
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $timeId = $user->time_atendimento_id ?? $user->atendente?->time_atendimento_id;

        if ($user->isCoordenador() && $timeId) {
            return $query->whereHas('atendimento', fn (Builder $query): Builder => $query->where('time_atendimento_id', $timeId));
        }

        if ($user->isAtendente() && $user->atendente_id) {
            return $query->whereHas('atendimento', fn (Builder $query): Builder => $query->whereHas('atribuicoes', fn (Builder $query): Builder => $query->where('atendente_id', $user->atendente_id)));
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtendimentoEventos::route('/'),
        ];
    }
}
