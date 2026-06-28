<?php

namespace App\Filament\Resources\AtendimentoAtribuicaos;

use App\Filament\Resources\AtendimentoAtribuicaos\Pages\ListAtendimentoAtribuicaos;
use App\Models\AtendimentoAtribuicao;
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

class AtendimentoAtribuicaoResource extends Resource
{
    protected static ?string $model = AtendimentoAtribuicao::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Operação';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('atendimento.id')->label('Atendimento'),
            TextEntry::make('atendente.nome')->label('Atendente'),
            TextEntry::make('status')->badge(),
            TextEntry::make('criado_em')->dateTime(),
            TextEntry::make('finalizado_em')->dateTime()->placeholder('-'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('criado_em', 'desc')
            ->columns([
                TextColumn::make('atendimento.id')->label('Atendimento')->searchable()->sortable(),
                TextColumn::make('atendente.nome')->label('Atendente')->searchable()->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('criado_em')->dateTime()->sortable(),
                TextColumn::make('finalizado_em')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    AtendimentoAtribuicao::STATUS_ATIVO => 'Ativo',
                    AtendimentoAtribuicao::STATUS_FINALIZADO => 'Finalizado',
                    AtendimentoAtribuicao::STATUS_TRANSFERIDO => 'Transferido',
                ]),
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
        $query = parent::getEloquentQuery()->with(['atendimento', 'atendente'])->orderByDesc('criado_em');
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
            return $query->where('atendente_id', $user->atendente_id);
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
            'index' => ListAtendimentoAtribuicaos::route('/'),
        ];
    }
}
