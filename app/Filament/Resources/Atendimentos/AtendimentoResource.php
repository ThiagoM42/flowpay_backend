<?php

namespace App\Filament\Resources\Atendimentos;

use App\Filament\Resources\Atendimentos\Pages\CreateAtendimento;
use App\Filament\Resources\Atendimentos\Pages\EditAtendimento;
use App\Filament\Resources\Atendimentos\Pages\ListAtendimentos;
use App\Filament\Resources\Atendimentos\RelationManagers\AtribuicoesRelationManager;
use App\Filament\Resources\Atendimentos\RelationManagers\EventosRelationManager;
use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Services\AtendimentoOperacaoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use UnitEnum;

class AtendimentoResource extends Resource
{
    protected static ?string $model = Atendimento::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|UnitEnum|null $navigationGroup = 'Operação';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return Cache::remember('filament:navigation:atendimentos:badge', now()->addSeconds(30), function (): string {
            return (string) Atendimento::query()->where('status', Atendimento::STATUS_AGUARDANDO)->count();
        });
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('cliente_id')
                ->relationship('cliente', 'nome')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('assunto_id')
                ->relationship('assunto', 'nome')
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $set('time_atendimento_id', Assunto::find($state)?->time_atendimento_id);
                })
                ->required(),
            Hidden::make('time_atendimento_id')->required(),
            Textarea::make('descricao')->rows(5)->columnSpanFull(),
            Select::make('prioridade')
                ->options([
                    'normal' => 'Normal',
                    'alta' => 'Alta',
                    'urgente' => 'Urgente',
                ])
                ->default('normal')
                ->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Atendimento')->schema([
                TextEntry::make('cliente.nome')->label('Cliente'),
                TextEntry::make('assunto.nome')->label('Assunto'),
                TextEntry::make('time.nome')->label('Time'),
                TextEntry::make('atribuicaoAtiva.atendente.nome')->label('Atendente atual')->placeholder('-'),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Atendimento::STATUS_EM_ATENDIMENTO => 'Em atendimento',
                        Atendimento::STATUS_FINALIZADO => 'Finalizado',
                        Atendimento::STATUS_CANCELADO => 'Cancelado',
                        default => 'Aguardando',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Atendimento::STATUS_EM_ATENDIMENTO => 'success',
                        Atendimento::STATUS_FINALIZADO => 'gray',
                        Atendimento::STATUS_CANCELADO => 'danger',
                        default => 'warning',
                    }),
                TextEntry::make('prioridade')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextEntry::make('descricao')->columnSpanFull()->prose()->placeholder('-'),
                TextEntry::make('tempo_espera')
                    ->label('Tempo de espera')
                    ->formatStateUsing(fn ($state, Atendimento $record): string => self::formatarTempoEspera($record)),
                TextEntry::make('criado_em')->dateTime(),
                TextEntry::make('entrou_na_fila_em')->dateTime()->placeholder('-'),
                TextEntry::make('iniciado_em')->dateTime()->placeholder('-'),
                TextEntry::make('finalizado_em')->dateTime()->placeholder('-'),
            ])->columns(2),
            Section::make('Eventos')->schema([
                RepeatableEntry::make('eventos')->schema([
                    TextEntry::make('tipo')->badge(),
                    TextEntry::make('descricao')->columnSpanFull(),
                    TextEntry::make('criado_em')->dateTime(),
                ])->columns(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup(Group::make('time.nome')->label('Time'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('cliente.nome')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('assunto.nome')->label('Assunto')->searchable()->sortable(),
                TextColumn::make('time.nome')->label('Time')->searchable()->sortable(),
                TextColumn::make('atribuicaoAtiva.atendente.nome')->label('Atendente atual')->placeholder('-')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Atendimento::STATUS_EM_ATENDIMENTO => 'Em atendimento',
                        Atendimento::STATUS_FINALIZADO => 'Finalizado',
                        Atendimento::STATUS_CANCELADO => 'Cancelado',
                        default => 'Aguardando',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Atendimento::STATUS_EM_ATENDIMENTO => 'success',
                        Atendimento::STATUS_FINALIZADO => 'gray',
                        Atendimento::STATUS_CANCELADO => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('tempo_espera')
                    ->label('Tempo de espera')
                    ->formatStateUsing(fn ($state, Atendimento $record): string => self::formatarTempoEspera($record))
                    ->badge()
                    ->color(fn (string $state, Atendimento $record): string => self::tempoEmMinutos($record) > 10 ? 'danger' : 'gray'),
                TextColumn::make('criado_em')->label('Criado em')->dateTime()->sortable(),
                TextColumn::make('entrou_na_fila_em')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Atendimento::STATUS_AGUARDANDO => 'Aguardando',
                    Atendimento::STATUS_EM_ATENDIMENTO => 'Em atendimento',
                    Atendimento::STATUS_FINALIZADO => 'Finalizado',
                    Atendimento::STATUS_CANCELADO => 'Cancelado',
                ]),
                SelectFilter::make('time_atendimento_id')->label('Time')->relationship('time', 'nome'),
                SelectFilter::make('assunto_id')->label('Assunto')->relationship('assunto', 'nome'),
                Filter::make('periodo')->form([
                    DateTimePicker::make('from')->label('De'),
                    DateTimePicker::make('until')->label('Até'),
                ])->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'] ?? null, fn (Builder $query, $from): Builder => $query->where('criado_em', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, $until): Builder => $query->where('criado_em', '<=', $until));
                }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('atribuir')
                    ->label('Atribuir')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (Atendimento $record): bool => in_array($record->status, [Atendimento::STATUS_AGUARDANDO, Atendimento::STATUS_EM_ATENDIMENTO], true))
                    ->form([
                        Select::make('atendente_id')
                            ->label('Atendente')
                            ->searchable()
                            ->required()
                            ->options(fn (): array => Atendente::query()->with('time')->orderBy('nome')->get()->mapWithKeys(fn (Atendente $atendente): array => [$atendente->id => $atendente->nome . ' (' . ($atendente->time?->nome ?? 'sem time') . ')'])->all()),
                    ])
                    ->action(function (Atendimento $record, array $data): void {
                        app(AtendimentoOperacaoService::class)->atribuir($record, Atendente::findOrFail($data['atendente_id']));
                    }),
                Action::make('transferir')
                    ->label('Transferir')
                    ->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (Atendimento $record): bool => $record->status === Atendimento::STATUS_EM_ATENDIMENTO)
                    ->form([
                        Select::make('atendente_id')
                            ->label('Novo atendente')
                            ->searchable()
                            ->required()
                            ->options(fn (): array => Atendente::query()->with('time')->orderBy('nome')->get()->mapWithKeys(fn (Atendente $atendente): array => [$atendente->id => $atendente->nome . ' (' . ($atendente->time?->nome ?? 'sem time') . ')'])->all()),
                    ])
                    ->action(function (Atendimento $record, array $data): void {
                        app(AtendimentoOperacaoService::class)->transferir($record, Atendente::findOrFail($data['atendente_id']));
                    }),
                Action::make('finalizar')
                    ->label('Finalizar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Atendimento $record): bool => $record->status === Atendimento::STATUS_EM_ATENDIMENTO)
                    ->requiresConfirmation()
                    ->action(fn (Atendimento $record) => app(AtendimentoOperacaoService::class)->finalizar($record)),
                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Atendimento $record): bool => ! in_array($record->status, [Atendimento::STATUS_FINALIZADO, Atendimento::STATUS_CANCELADO], true))
                    ->requiresConfirmation()
                    ->action(fn (Atendimento $record) => app(AtendimentoOperacaoService::class)->cancelar($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['cliente', 'assunto', 'time', 'atribuicaoAtiva.atendente', 'eventos']);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isCoordenador()) {
            return $query->where('time_atendimento_id', $user->time_atendimento_id ?? $user->atendente?->time_atendimento_id);
        }

        if ($user->isAtendente() && $user->atendente_id) {
            return $query->whereHas('atribuicoes', fn (Builder $query): Builder => $query->where('atendente_id', $user->atendente_id));
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getRelations(): array
    {
        return [
            AtribuicoesRelationManager::class,
            EventosRelationManager::class,
        ];
    }

    public static function formatarTempoEspera(Atendimento $record): string
    {
        $inicio = $record->entrou_na_fila_em ?? $record->criado_em;

        return $inicio ? Carbon::parse($inicio)->diffForHumans(now(), true) . ' aguardando' : '-';
    }

    public static function tempoEmMinutos(Atendimento $record): int
    {
        $inicio = $record->entrou_na_fila_em ?? $record->criado_em;

        return $inicio ? Carbon::parse($inicio)->diffInMinutes(now()) : 0;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtendimentos::route('/'),
            'create' => CreateAtendimento::route('/create'),
            'edit' => EditAtendimento::route('/{record}/edit'),
        ];
    }
}
