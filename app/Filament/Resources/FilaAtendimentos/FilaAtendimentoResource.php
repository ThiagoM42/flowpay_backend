<?php

namespace App\Filament\Resources\FilaAtendimentos;

use App\Filament\Resources\FilaAtendimentos\Pages\ListFilaAtendimentos;
use App\Models\FilaAtendimento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class FilaAtendimentoResource extends Resource
{
    protected static ?string $model = FilaAtendimento::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Operação';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return Cache::remember('filament:navigation:fila-atendimentos:badge', now()->addSeconds(30), function (): string {
            return (string) FilaAtendimento::query()->where('status', FilaAtendimento::STATUS_AGUARDANDO)->count();
        });
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('atendimento.cliente.nome')->label('Cliente'),
            TextEntry::make('atendimento.assunto.nome')->label('Assunto'),
            TextEntry::make('time.nome')->label('Time'),
            TextEntry::make('status')->badge(),
            TextEntry::make('entrou_em')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup(Group::make('time.nome')->label('Time'))
            ->columns([
                TextColumn::make('posicao')
                    ->label('Posição')
                    ->formatStateUsing(fn ($state, FilaAtendimento $record): string => (string) $record->time->filaAtendimentos()->where('status', FilaAtendimento::STATUS_AGUARDANDO)->where('entrou_em', '<=', $record->entrou_em)->count()),
                TextColumn::make('atendimento.cliente.nome')->label('Cliente')->searchable(),
                TextColumn::make('atendimento.assunto.nome')->label('Assunto')->searchable(),
                TextColumn::make('time.nome')->label('Time')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        FilaAtendimento::STATUS_PROCESSADO => 'success',
                        FilaAtendimento::STATUS_CANCELADO => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('entrou_em')
                    ->label('Tempo de espera')
                    ->formatStateUsing(fn ($state, FilaAtendimento $record): string => $record->entrou_em ? Carbon::parse($record->entrou_em)->diffForHumans(now(), true) : '-')
                    ->badge(),
                TextColumn::make('prioridade')
                    ->formatStateUsing(fn ($state, FilaAtendimento $record): string => $record->atendimento?->prioridade ?? 'normal')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('time_atendimento_id')->label('Time')->relationship('time', 'nome'),
                SelectFilter::make('status')->options([
                    FilaAtendimento::STATUS_AGUARDANDO => 'Aguardando',
                    FilaAtendimento::STATUS_PROCESSADO => 'Processado',
                    FilaAtendimento::STATUS_CANCELADO => 'Cancelado',
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
        $query = parent::getEloquentQuery()->with(['atendimento.cliente', 'atendimento.assunto', 'time'])->orderBy('entrou_em');
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $timeId = $user->time_atendimento_id ?? $user->atendente?->time_atendimento_id;

        if ($user->isCoordenador() && $timeId) {
            return $query->where('time_atendimento_id', $timeId);
        }

        if ($user->isAtendente() && $timeId) {
            return $query->where('time_atendimento_id', $timeId);
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
            'index' => ListFilaAtendimentos::route('/'),
        ];
    }
}
