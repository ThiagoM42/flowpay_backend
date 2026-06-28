# Filament Admin Panel — Sistema de Atendimento FlowPay

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the Filament v5 admin panel for the customer service module: 8 Resources, 8 Dashboard Widgets (stats/charts/tables), role-based Policies, and Redis widget caching.

**Architecture:** Role system added to `users` table (admin/coordenador/atendente) with `time_atendimento_id` and link to `atendentes.user_id`. Filament Resources auto-discovered from `app/Filament/Resources/`. Widgets auto-discovered from `app/Filament/Widgets/` and sorted by `$sort`. Actions in AtendimentoResource call existing Services directly. Policies registered via `Gate::policy()` in AppServiceProvider; Filament v5 checks them automatically.

**Tech Stack:** Laravel 12, PHP 8.2+, Filament v5.6, Redis (Docker container `laravel_redis_flowpay`), PHPUnit 11 (existing tests must remain green).

## Global Constraints

- Filament version: `^5.6` — use Filament v5 API (Schema-based forms, `->badge()` on TextColumn, `ChartWidget`, `StatsOverviewWidget`, `TableWidget`)
- All artisan commands run inside Docker: `docker exec laravel_app_flowpay php artisan ...`
- Existing 14 feature tests must keep passing after every task (`docker exec laravel_app_flowpay php artisan test --no-coverage`)
- No automated Filament/Livewire tests — verification is manual browser check at `http://localhost/admin` (login: `admin@hotmail.com` / `4815162342`)
- Redis container: `laravel_redis_flowpay`, port 6379 internal. `.env` must have `CACHE_STORE=redis` and `REDIS_HOST=laravel_redis_flowpay`
- Navigation groups: **Operação** (Atendimentos, Fila, Atribuições), **Cadastros** (Clientes, Times, Assuntos, Atendentes), **Auditoria** (Eventos)
- Badge color convention (used everywhere): `aguardando=warning`, `em_atendimento=success`, `finalizado=gray`, `cancelado=danger`, `online=success`, `offline=gray`, `pausado=warning`
- Actions that write to DB must use `DB::transaction()` — same pattern as existing controllers
- `lockForUpdate()` must be conditional: `if (DB::connection()->getDriverName() !== 'sqlite') { $query->lockForUpdate(); }`
- Platform: Windows, shell is PowerShell — use `docker exec` for all PHP/artisan commands

---

## File Map

**New migrations:**
```
database/migrations/2026_06_28_000001_add_role_to_users_table.php
database/migrations/2026_06_28_000002_add_user_id_to_atendentes_table.php
```

**Modified models:**
```
app/Models/User.php           — role constants, helpers, relationships
app/Models/Atendente.php      — belongsTo User
```

**Modified providers:**
```
app/Providers/AppServiceProvider.php          — Gate::policy() registrations
app/Providers/Filament/AdminPanelProvider.php — navigationGroups
```

**New Filament Resources (8):**
```
app/Filament/Resources/ClienteResource.php
app/Filament/Resources/ClienteResource/Pages/ListClientes.php
app/Filament/Resources/ClienteResource/Pages/CreateCliente.php
app/Filament/Resources/ClienteResource/Pages/EditCliente.php
app/Filament/Resources/TimeAtendimentoResource.php
app/Filament/Resources/TimeAtendimentoResource/Pages/...
app/Filament/Resources/AssuntoResource.php
app/Filament/Resources/AssuntoResource/Pages/...
app/Filament/Resources/AtendenteResource.php
app/Filament/Resources/AtendenteResource/Pages/...
app/Filament/Resources/AtendimentoResource.php
app/Filament/Resources/AtendimentoResource/Pages/ListAtendimentos.php
app/Filament/Resources/AtendimentoResource/Pages/CreateAtendimento.php
app/Filament/Resources/AtendimentoResource/Pages/ViewAtendimento.php
app/Filament/Resources/AtendimentoResource/Pages/EditAtendimento.php
app/Filament/Resources/AtendimentoResource/RelationManagers/AtribuicoesRelationManager.php
app/Filament/Resources/AtendimentoResource/RelationManagers/EventosRelationManager.php
app/Filament/Resources/FilaAtendimentoResource.php
app/Filament/Resources/FilaAtendimentoResource/Pages/ListFilaAtendimentos.php
app/Filament/Resources/AtendimentoAtribuicaoResource.php
app/Filament/Resources/AtendimentoAtribuicaoResource/Pages/ListAtendimentoAtribuicoes.php
app/Filament/Resources/AtendimentoEventoResource.php
app/Filament/Resources/AtendimentoEventoResource/Pages/ListAtendimentoEventos.php
```

**New Filament Widgets (8):**
```
app/Filament/Widgets/AtendimentosOverviewWidget.php
app/Filament/Widgets/AtendentesOverviewWidget.php
app/Filament/Widgets/TemposMediosWidget.php
app/Filament/Widgets/AtendimentosPorTimeChart.php
app/Filament/Widgets/AtendimentosPorAssuntoChart.php
app/Filament/Widgets/AtendimentosUltimos7DiasChart.php
app/Filament/Widgets/CargaAtendentesTableWidget.php
app/Filament/Widgets/FilaAtualTableWidget.php
```

**New Policies (8):**
```
app/Policies/ClientePolicy.php
app/Policies/TimeAtendimentoPolicy.php
app/Policies/AssuntoPolicy.php
app/Policies/AtendentePolicy.php
app/Policies/AtendimentoPolicy.php
app/Policies/FilaAtendimentoPolicy.php
app/Policies/AtendimentoAtribuicaoPolicy.php
app/Policies/AtendimentoEventoPolicy.php
```

---

### Task 1: Role System

**Files:**
- Create: `database/migrations/2026_06_28_000001_add_role_to_users_table.php`
- Create: `database/migrations/2026_06_28_000002_add_user_id_to_atendentes_table.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Atendente.php`

**Interfaces:**
- Produces: `User::ROLE_ADMIN`, `User::ROLE_COORDENADOR`, `User::ROLE_ATENDENTE`, `$user->isAdmin()`, `$user->isCoordenador()`, `$user->isAtendente()`, `$user->timeAtendimento()`, `$user->atendente()` — consumed by Tasks 2–8.
- Produces: `Atendente::$user` relationship (hasOne inverse via user_id) — consumed by Policies in Task 8.

- [ ] **Step 1: Create migration to add role and time_atendimento_id to users**

File: `database/migrations/2026_06_28_000001_add_role_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'coordenador', 'atendente'])
                  ->default('admin')
                  ->after('password');
            $table->foreignId('time_atendimento_id')
                  ->nullable()
                  ->constrained('times_atendimento')
                  ->nullOnDelete()
                  ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['time_atendimento_id']);
            $table->dropColumn(['time_atendimento_id', 'role']);
        });
    }
};
```

- [ ] **Step 2: Create migration to add user_id to atendentes**

File: `database/migrations/2026_06_28_000002_add_user_id_to_atendentes_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atendentes', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('atendentes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
```

- [ ] **Step 3: Update User model**

Full replacement for `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    const ROLE_ADMIN = 'admin';
    const ROLE_COORDENADOR = 'coordenador';
    const ROLE_ATENDENTE = 'atendente';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'time_atendimento_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCoordenador(): bool
    {
        return $this->role === self::ROLE_COORDENADOR;
    }

    public function isAtendente(): bool
    {
        return $this->role === self::ROLE_ATENDENTE;
    }

    public function timeAtendimento(): BelongsTo
    {
        return $this->belongsTo(TimeAtendimento::class);
    }

    public function atendente(): HasOne
    {
        return $this->hasOne(Atendente::class);
    }
}
```

- [ ] **Step 4: Add user() relationship to Atendente model**

In `app/Models/Atendente.php`, add import and relationship. Current fillable is `['time_atendimento_id', 'nome', 'email', 'status', 'max_atendimentos_simultaneos', 'ativo']`. Add `'user_id'` to fillable and add the relation:

```php
// Add to imports:
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Add 'user_id' to $fillable array

// Add relationship method:
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

- [ ] **Step 5: Run migrations and verify schema**

```bash
docker exec laravel_app_flowpay php artisan migrate
```

Expected output: 2 new migrations run successfully.

```bash
docker exec laravel_app_flowpay php artisan tinker --execute="schema_builder = DB::getSchemaBuilder(); dd(schema_builder->getColumnListing('users'), schema_builder->getColumnListing('atendentes'));"
```

Expected: `users` has `role` and `time_atendimento_id` columns; `atendentes` has `user_id` column.

- [ ] **Step 6: Run existing tests to confirm nothing broke**

```bash
docker exec laravel_app_flowpay php artisan test --no-coverage
```

Expected: `Tests: 14 passed (62 assertions)` — if any test fails, diagnose before continuing.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_28_000001_add_role_to_users_table.php database/migrations/2026_06_28_000002_add_user_id_to_atendentes_table.php app/Models/User.php app/Models/Atendente.php
git commit -m "feat: add role system to users and link atendentes to user accounts"
```

---

### Task 2: Cadastros Resources + AdminPanelProvider Navigation

**Files:**
- Create: `app/Filament/Resources/ClienteResource.php` + Pages (3 files)
- Create: `app/Filament/Resources/TimeAtendimentoResource.php` + Pages (3 files)
- Create: `app/Filament/Resources/AssuntoResource.php` + Pages (3 files)
- Create: `app/Filament/Resources/AtendenteResource.php` + Pages (3 files)
- Modify: `app/Providers/Filament/AdminPanelProvider.php`

**Interfaces:**
- Consumes: `User::ROLE_*` constants (Task 1), all existing Models
- Produces: working `/admin/clientes`, `/admin/time-atendimentos`, `/admin/assuntos`, `/admin/atendentes` routes

- [ ] **Step 1: Configure .env for Redis caching**

Check `.env` — if `CACHE_STORE=database`, change it:

```
CACHE_STORE=redis
REDIS_HOST=laravel_redis_flowpay
REDIS_PORT=6379
REDIS_PASSWORD=null
```

Then clear config cache:
```bash
docker exec laravel_app_flowpay php artisan config:clear
```

- [ ] **Step 2: Generate the 4 Cadastros resources**

```bash
docker exec laravel_app_flowpay php artisan make:filament-resource Cliente --generate
docker exec laravel_app_flowpay php artisan make:filament-resource TimeAtendimento --generate
docker exec laravel_app_flowpay php artisan make:filament-resource Assunto --generate
docker exec laravel_app_flowpay php artisan make:filament-resource Atendente --generate
```

This creates `app/Filament/Resources/{Name}Resource.php` and `Pages/` subdirectory with `List`, `Create`, `Edit` page classes.

- [ ] **Step 3: Implement ClienteResource**

Replace the generated `app/Filament/Resources/ClienteResource.php` with:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nome')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255),
            TextInput::make('telefone')->tel()->maxLength(20),
            TextInput::make('documento')->required()->maxLength(20)->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('telefone'),
                TextColumn::make('documento')->searchable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit'   => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Implement TimeAtendimentoResource**

Replace `app/Filament/Resources/TimeAtendimentoResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimeAtendimentoResource\Pages;
use App\Models\TimeAtendimento;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TimeAtendimentoResource extends Resource
{
    protected static ?string $model = TimeAtendimento::class;
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $modelLabel = 'Time de Atendimento';
    protected static ?string $pluralModelLabel = 'Times de Atendimento';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nome')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(100)->unique(ignoreRecord: true),
            Toggle::make('ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('slug'),
                IconColumn::make('ativo')->boolean(),
            ])
            ->defaultSort('nome');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTimeAtendimentos::route('/'),
            'create' => Pages\CreateTimeAtendimento::route('/create'),
            'edit'   => Pages\EditTimeAtendimento::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Implement AssuntoResource**

Replace `app/Filament/Resources/AssuntoResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssuntoResource\Pages;
use App\Models\Assunto;
use App\Models\TimeAtendimento;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssuntoResource extends Resource
{
    protected static ?string $model = Assunto::class;
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $modelLabel = 'Assunto';
    protected static ?string $pluralModelLabel = 'Assuntos';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('time_atendimento_id')
                ->label('Time')
                ->relationship('time', 'nome')
                ->required(),
            TextInput::make('nome')->required()->maxLength(255),
            Toggle::make('ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('time.nome')->label('Time')->sortable(),
                TextColumn::make('nome')->searchable()->sortable(),
                IconColumn::make('ativo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('time_atendimento_id')
                    ->relationship('time', 'nome')
                    ->label('Time'),
            ])
            ->defaultSort('nome');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAssuntos::route('/'),
            'create' => Pages\CreateAssunto::route('/create'),
            'edit'   => Pages\EditAssunto::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 6: Implement AtendenteResource**

Replace `app/Filament/Resources/AtendenteResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtendenteResource\Pages;
use App\Models\Atendente;
use App\Models\AtendimentoAtribuicao;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AtendenteResource extends Resource
{
    protected static ?string $model = Atendente::class;
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $modelLabel = 'Atendente';
    protected static ?string $pluralModelLabel = 'Atendentes';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('time_atendimento_id')
                ->label('Time')
                ->relationship('time', 'nome')
                ->required(),
            TextInput::make('nome')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255),
            Select::make('status')
                ->options([
                    Atendente::STATUS_ONLINE  => 'Online',
                    Atendente::STATUS_OFFLINE => 'Offline',
                    Atendente::STATUS_PAUSADO => 'Pausado',
                ])
                ->default(Atendente::STATUS_OFFLINE)
                ->required(),
            TextInput::make('max_atendimentos_simultaneos')
                ->numeric()
                ->default(3)
                ->required()
                ->minValue(1)
                ->maxValue(10),
            Toggle::make('ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('time.nome')->label('Time')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'online'  => 'success',
                        'pausado' => 'warning',
                        default   => 'gray',
                    }),
                TextColumn::make('carga')
                    ->label('Carga (ativas/máx)')
                    ->getStateUsing(fn(Atendente $record): string =>
                        $record->atribuicoesAtivas()->count() . '/' . $record->max_atendimentos_simultaneos
                    ),
            ])
            ->filters([
                SelectFilter::make('time_atendimento_id')
                    ->relationship('time', 'nome')
                    ->label('Time'),
                SelectFilter::make('status')
                    ->options([
                        'online'  => 'Online',
                        'offline' => 'Offline',
                        'pausado' => 'Pausado',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('toggle_status')
                    ->label(fn(Atendente $record): string =>
                        $record->status === Atendente::STATUS_ONLINE ? 'Pausar' : 'Ativar'
                    )
                    ->icon(fn(Atendente $record): string =>
                        $record->status === Atendente::STATUS_ONLINE ? 'heroicon-o-pause' : 'heroicon-o-play'
                    )
                    ->color(fn(Atendente $record): string =>
                        $record->status === Atendente::STATUS_ONLINE ? 'warning' : 'success'
                    )
                    ->requiresConfirmation()
                    ->action(fn(Atendente $record) => $record->update([
                        'status' => $record->status === Atendente::STATUS_ONLINE
                            ? Atendente::STATUS_PAUSADO
                            : Atendente::STATUS_ONLINE,
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAtendentes::route('/'),
            'create' => Pages\CreateAtendente::route('/create'),
            'edit'   => Pages\EditAtendente::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 7: Update AdminPanelProvider with navigation groups**

Modify `app/Providers/Filament/AdminPanelProvider.php`. Add import and call `->navigationGroups([...])` inside the `panel()` method, before `->discoverResources(...)`:

```php
// Add to imports at top:
use Filament\Navigation\NavigationGroup;

// Add inside panel() method, after ->colors([...]):
->navigationGroups([
    NavigationGroup::make('Operação')
        ->icon('heroicon-o-bolt'),
    NavigationGroup::make('Cadastros')
        ->icon('heroicon-o-cog-6-tooth'),
    NavigationGroup::make('Auditoria')
        ->icon('heroicon-o-document-magnifying-glass'),
])
```

- [ ] **Step 8: Run existing tests**

```bash
docker exec laravel_app_flowpay php artisan test --no-coverage
```

Expected: `Tests: 14 passed (62 assertions)`

- [ ] **Step 9: Manual verification**

Visit `http://localhost/admin` in the browser, log in with `admin@hotmail.com` / `4815162342`.

Verify:
- Left sidebar shows groups "Cadastros" with Clientes, Times, Assuntos, Atendentes
- Can create/edit a Cliente
- Can create/edit a TimeAtendimento
- Can create/edit an Assunto (with Time dropdown)
- Can create/edit an Atendente — badge shows colored status
- Toggle action on Atendente list switches online ↔ pausado

- [ ] **Step 10: Commit**

```bash
git add app/Filament/Resources/ClienteResource.php \
        app/Filament/Resources/ClienteResource/ \
        app/Filament/Resources/TimeAtendimentoResource.php \
        app/Filament/Resources/TimeAtendimentoResource/ \
        app/Filament/Resources/AssuntoResource.php \
        app/Filament/Resources/AssuntoResource/ \
        app/Filament/Resources/AtendenteResource.php \
        app/Filament/Resources/AtendenteResource/ \
        app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: add Cadastros resources and navigation groups to Filament panel"
```

---

### Task 3: AtendimentoResource

**Files:**
- Create: `app/Filament/Resources/AtendimentoResource.php` + Pages (4 files) + RelationManagers (2 files)

**Interfaces:**
- Consumes: `AtendimentoDistribuicaoService::atribuirAtendente(Atendimento, Atendente): void`, `ProcessarFilaAtendimentoJob::dispatch(int $timeAtendimentoId)`, all Status constants
- Produces: working `/admin/atendimentos` with 4 actions + 2 RelationManagers

- [ ] **Step 1: Generate AtendimentoResource scaffold**

```bash
docker exec laravel_app_flowpay php artisan make:filament-resource Atendimento --generate
docker exec laravel_app_flowpay php artisan make:filament-relation-manager AtendimentoResource atribuicoes atendente_id
docker exec laravel_app_flowpay php artisan make:filament-relation-manager AtendimentoResource eventos tipo
```

- [ ] **Step 2: Implement AtendimentoResource**

Replace `app/Filament/Resources/AtendimentoResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtendimentoResource\Pages;
use App\Filament\Resources\AtendimentoResource\RelationManagers;
use App\Jobs\ProcessarFilaAtendimentoJob;
use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\AtendimentoEvento;
use App\Services\AtendimentoDistribuicaoService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class AtendimentoResource extends Resource
{
    protected static ?string $model = Atendimento::class;
    protected static ?string $navigationGroup = 'Operação';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $modelLabel = 'Atendimento';
    protected static ?string $pluralModelLabel = 'Atendimentos';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('cliente_id')
                ->label('Cliente')
                ->relationship('cliente', 'nome')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('assunto_id')
                ->label('Assunto')
                ->relationship('assunto', 'nome', fn($query) => $query->where('ativo', true))
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function (?int $state, Set $set) {
                    $set('time_atendimento_id', $state ? Assunto::find($state)?->time_atendimento_id : null);
                }),
            Hidden::make('time_atendimento_id'),
            Select::make('prioridade')
                ->options(['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta'])
                ->default('media'),
            Textarea::make('descricao')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('cliente.nome')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('assunto.nome')->label('Assunto')->searchable(),
                TextColumn::make('time.nome')->label('Time'),
                TextColumn::make('atendente_atual')
                    ->label('Atendente')
                    ->getStateUsing(fn(Atendimento $record): string =>
                        $record->atribuicaoAtiva?->atendente?->nome ?? '—'
                    ),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'em_atendimento' => 'success',
                        'aguardando'     => 'warning',
                        'finalizado'     => 'gray',
                        'cancelado'      => 'danger',
                        default          => 'gray',
                    }),
                TextColumn::make('criado_em')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'aguardando'     => 'Aguardando',
                        'em_atendimento' => 'Em atendimento',
                        'finalizado'     => 'Finalizado',
                        'cancelado'      => 'Cancelado',
                    ]),
                SelectFilter::make('time_atendimento_id')
                    ->relationship('time', 'nome')
                    ->label('Time'),
                SelectFilter::make('assunto_id')
                    ->relationship('assunto', 'nome')
                    ->label('Assunto'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('atribuir')
                    ->label('Atribuir')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn(Atendimento $record): bool =>
                        $record->status === Atendimento::STATUS_AGUARDANDO
                    )
                    ->form([
                        Select::make('atendente_id')
                            ->label('Atendente')
                            ->options(fn(Atendimento $record) =>
                                Atendente::where('time_atendimento_id', $record->time_atendimento_id)
                                    ->where('status', Atendente::STATUS_ONLINE)
                                    ->where('ativo', true)
                                    ->pluck('nome', 'id')
                            )
                            ->required(),
                    ])
                    ->action(function (Atendimento $record, array $data): void {
                        $atendente = Atendente::findOrFail($data['atendente_id']);
                        app(AtendimentoDistribuicaoService::class)->atribuirAtendente($record, $atendente);
                        Notification::make()->title('Atendimento atribuído')->success()->send();
                    }),

                Action::make('transferir')
                    ->label('Transferir')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->visible(fn(Atendimento $record): bool =>
                        $record->status === Atendimento::STATUS_EM_ATENDIMENTO
                    )
                    ->form([
                        Select::make('atendente_id')
                            ->label('Novo Atendente')
                            ->options(fn(Atendimento $record) =>
                                Atendente::where('status', Atendente::STATUS_ONLINE)
                                    ->where('ativo', true)
                                    ->where('id', '!=', $record->atribuicaoAtiva?->atendente_id)
                                    ->pluck('nome', 'id')
                            )
                            ->required(),
                    ])
                    ->action(function (Atendimento $record, array $data): void {
                        $novoAtendente = Atendente::findOrFail($data['atendente_id']);
                        if (!$novoAtendente->estaDisponivel()) {
                            Notification::make()->title('Atendente não disponível ou no limite')->danger()->send();
                            return;
                        }
                        $atribuicaoAtual = $record->atribuicaoAtiva;
                        if (!$atribuicaoAtual) {
                            Notification::make()->title('Nenhuma atribuição ativa')->danger()->send();
                            return;
                        }
                        if ($novoAtendente->id === $atribuicaoAtual->atendente_id) {
                            Notification::make()->title('Atendente já é o atual responsável')->danger()->send();
                            return;
                        }
                        try {
                            DB::transaction(function () use ($record, $atribuicaoAtual, $novoAtendente) {
                                $query = $novoAtendente->atribuicoesAtivas();
                                if (DB::connection()->getDriverName() !== 'sqlite') {
                                    $query->lockForUpdate();
                                }
                                if ($query->count() >= $novoAtendente->max_atendimentos_simultaneos) {
                                    throw new \RuntimeException('limit_exceeded');
                                }
                                $atribuicaoAtual->update([
                                    'status'        => AtendimentoAtribuicao::STATUS_TRANSFERIDO,
                                    'finalizado_em' => now(),
                                ]);
                                AtendimentoAtribuicao::create([
                                    'atendimento_id' => $record->id,
                                    'atendente_id'   => $novoAtendente->id,
                                    'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
                                ]);
                                AtendimentoEvento::create([
                                    'atendimento_id' => $record->id,
                                    'tipo'           => 'transferido',
                                    'descricao'      => "Transferido para {$novoAtendente->nome}.",
                                    'dados'          => [
                                        'atendente_anterior_id' => $atribuicaoAtual->atendente_id,
                                        'atendente_novo_id'     => $novoAtendente->id,
                                    ],
                                ]);
                            });
                            Notification::make()->title('Atendimento transferido')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Atendente atingiu o limite')->danger()->send();
                        }
                    }),

                Action::make('finalizar')
                    ->label('Finalizar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Atendimento $record): bool =>
                        $record->status === Atendimento::STATUS_EM_ATENDIMENTO
                    )
                    ->requiresConfirmation()
                    ->action(function (Atendimento $record): void {
                        $atribuicao = $record->atribuicaoAtiva;
                        if (!$atribuicao) {
                            Notification::make()->title('Nenhuma atribuição ativa')->danger()->send();
                            return;
                        }
                        DB::transaction(function () use ($record, $atribuicao) {
                            $atribuicao->update([
                                'status'        => AtendimentoAtribuicao::STATUS_FINALIZADO,
                                'finalizado_em' => now(),
                            ]);
                            $record->update([
                                'status'        => Atendimento::STATUS_FINALIZADO,
                                'finalizado_em' => now(),
                            ]);
                            AtendimentoEvento::create([
                                'atendimento_id' => $record->id,
                                'tipo'           => 'finalizado',
                                'descricao'      => 'Atendimento finalizado pelo administrador.',
                                'dados'          => ['atendente_id' => $atribuicao->atendente_id],
                            ]);
                        });
                        ProcessarFilaAtendimentoJob::dispatch($record->time_atendimento_id);
                        Notification::make()->title('Atendimento finalizado')->success()->send();
                    }),

                Action::make('cancelar')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Atendimento $record): bool =>
                        in_array($record->status, [
                            Atendimento::STATUS_AGUARDANDO,
                            Atendimento::STATUS_EM_ATENDIMENTO,
                        ])
                    )
                    ->requiresConfirmation()
                    ->action(function (Atendimento $record): void {
                        DB::transaction(function () use ($record) {
                            if ($record->atribuicaoAtiva) {
                                $record->atribuicaoAtiva->update([
                                    'status'        => AtendimentoAtribuicao::STATUS_FINALIZADO,
                                    'finalizado_em' => now(),
                                ]);
                            }
                            $record->update(['status' => 'cancelado']);
                            AtendimentoEvento::create([
                                'atendimento_id' => $record->id,
                                'tipo'           => 'cancelado',
                                'descricao'      => 'Atendimento cancelado pelo administrador.',
                            ]);
                        });
                        Notification::make()->title('Atendimento cancelado')->warning()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AtribuicoesRelationManager::class,
            RelationManagers\EventosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAtendimentos::route('/'),
            'create' => Pages\CreateAtendimento::route('/create'),
            'view'   => Pages\ViewAtendimento::route('/{record}'),
            'edit'   => Pages\EditAtendimento::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 3: Create ViewAtendimento page**

Create `app/Filament/Resources/AtendimentoResource/Pages/ViewAtendimento.php`:

```php
<?php

namespace App\Filament\Resources\AtendimentoResource\Pages;

use App\Filament\Resources\AtendimentoResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAtendimento extends ViewRecord
{
    protected static string $resource = AtendimentoResource::class;
}
```

- [ ] **Step 4: Implement AtribuicoesRelationManager**

Replace `app/Filament/Resources/AtendimentoResource/RelationManagers/AtribuicoesRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\AtendimentoResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AtribuicoesRelationManager extends RelationManager
{
    protected static string $relationship = 'atribuicoes';
    protected static ?string $title = 'Histórico de Atendentes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('atendente.nome')->label('Atendente'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'ativo'       => 'success',
                        'finalizado'  => 'gray',
                        'transferido' => 'warning',
                        default       => 'gray',
                    }),
                TextColumn::make('criado_em')->label('Início')->dateTime('d/m/Y H:i'),
                TextColumn::make('finalizado_em')->label('Fim')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('criado_em', 'asc');
    }
}
```

- [ ] **Step 5: Implement EventosRelationManager**

Replace `app/Filament/Resources/AtendimentoResource/RelationManagers/EventosRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\AtendimentoResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventosRelationManager extends RelationManager
{
    protected static string $relationship = 'eventos';
    protected static ?string $title = 'Timeline de Eventos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipo')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'atribuido'  => 'success',
                        'finalizado' => 'gray',
                        'transferido'=> 'info',
                        'enfileirado'=> 'warning',
                        'cancelado'  => 'danger',
                        default      => 'gray',
                    }),
                TextColumn::make('descricao')->label('Descrição')->wrap(),
                TextColumn::make('criado_em')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('criado_em', 'asc');
    }
}
```

- [ ] **Step 6: Run tests**

```bash
docker exec laravel_app_flowpay php artisan test --no-coverage
```

Expected: `Tests: 14 passed (62 assertions)`

- [ ] **Step 7: Manual verification**

Visit `/admin/atendimentos`. Verify:
- List shows atendimentos with colored status badges
- Filters by status/time/assunto work
- "Atribuir" action visible for `aguardando` status atendimentos
- "Transferir" + "Finalizar" visible for `em_atendimento`
- "Cancelar" visible for `aguardando` and `em_atendimento`
- View page shows RelationManagers tabs for Atribuições and Eventos

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/AtendimentoResource.php \
        app/Filament/Resources/AtendimentoResource/
git commit -m "feat: add AtendimentoResource with custom actions and relation managers"
```

---

### Task 4: Read-only Resources (Fila, Atribuições, Eventos)

**Files:**
- Create: `app/Filament/Resources/FilaAtendimentoResource.php` + Pages
- Create: `app/Filament/Resources/AtendimentoAtribuicaoResource.php` + Pages
- Create: `app/Filament/Resources/AtendimentoEventoResource.php` + Pages

**Interfaces:**
- Produces: `/admin/fila-atendimentos` (with 5s polling), `/admin/atendimento-atribuicoes`, `/admin/atendimento-eventos`

- [ ] **Step 1: Generate read-only resource scaffolds**

```bash
docker exec laravel_app_flowpay php artisan make:filament-resource FilaAtendimento --generate
docker exec laravel_app_flowpay php artisan make:filament-resource AtendimentoAtribuicao --generate
docker exec laravel_app_flowpay php artisan make:filament-resource AtendimentoEvento --generate
```

- [ ] **Step 2: Implement FilaAtendimentoResource**

Replace `app/Filament/Resources/FilaAtendimentoResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FilaAtendimentoResource\Pages;
use App\Models\FilaAtendimento;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FilaAtendimentoResource extends Resource
{
    protected static ?string $model = FilaAtendimento::class;
    protected static ?string $navigationGroup = 'Operação';
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $modelLabel = 'Fila de Atendimento';
    protected static ?string $pluralModelLabel = 'Fila de Atendimentos';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]); // read-only: no form needed
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(FilaAtendimento::query()->with(['atendimento.cliente', 'time']))
            ->poll('5s')
            ->columns([
                TextColumn::make('time.nome')->label('Time')->sortable(),
                TextColumn::make('atendimento.cliente.nome')->label('Cliente'),
                TextColumn::make('atendimento.assunto.nome')->label('Assunto'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'aguardando' => 'warning',
                        'processado' => 'success',
                        'cancelado'  => 'danger',
                        default      => 'gray',
                    }),
                TextColumn::make('tempo_espera')
                    ->label('Tempo de espera')
                    ->getStateUsing(fn(FilaAtendimento $record): string =>
                        now()->diffInMinutes($record->entrou_em) . ' min'
                    ),
                TextColumn::make('entrou_em')
                    ->label('Entrou na fila')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('entrou_em', 'asc');
    }

    public static function canCreate(): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFilaAtendimentos::route('/'),
        ];
    }
}
```

- [ ] **Step 3: Implement AtendimentoAtribuicaoResource**

Replace `app/Filament/Resources/AtendimentoAtribuicaoResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtendimentoAtribuicaoResource\Pages;
use App\Models\AtendimentoAtribuicao;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AtendimentoAtribuicaoResource extends Resource
{
    protected static ?string $model = AtendimentoAtribuicao::class;
    protected static ?string $navigationGroup = 'Operação';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $modelLabel = 'Atribuição';
    protected static ?string $pluralModelLabel = 'Atribuições';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('atendimento.id')->label('Atendimento #'),
                TextColumn::make('atendente.nome')->label('Atendente')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'ativo'       => 'success',
                        'finalizado'  => 'gray',
                        'transferido' => 'warning',
                        default       => 'gray',
                    }),
                TextColumn::make('criado_em')->label('Início')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('finalizado_em')->label('Fim')->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'ativo'       => 'Ativo',
                        'finalizado'  => 'Finalizado',
                        'transferido' => 'Transferido',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function canCreate(): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAtendimentoAtribuicoes::route('/'),
        ];
    }
}
```

- [ ] **Step 4: Implement AtendimentoEventoResource**

Replace `app/Filament/Resources/AtendimentoEventoResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AtendimentoEventoResource\Pages;
use App\Models\AtendimentoEvento;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AtendimentoEventoResource extends Resource
{
    protected static ?string $model = AtendimentoEvento::class;
    protected static ?string $navigationGroup = 'Auditoria';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $modelLabel = 'Evento';
    protected static ?string $pluralModelLabel = 'Eventos';
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('atendimento_id')->label('Atendimento #')->sortable(),
                TextColumn::make('tipo')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'atribuido'   => 'success',
                        'finalizado'  => 'gray',
                        'transferido' => 'info',
                        'enfileirado' => 'warning',
                        'cancelado'   => 'danger',
                        default       => 'gray',
                    }),
                TextColumn::make('descricao')->label('Descrição')->limit(80),
                TextColumn::make('criado_em')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->options([
                        'atribuido'   => 'Atribuído',
                        'finalizado'  => 'Finalizado',
                        'transferido' => 'Transferido',
                        'enfileirado' => 'Enfileirado',
                        'cancelado'   => 'Cancelado',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function canCreate(): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAtendimentoEventos::route('/'),
        ];
    }
}
```

- [ ] **Step 5: Run tests**

```bash
docker exec laravel_app_flowpay php artisan test --no-coverage
```

Expected: `Tests: 14 passed (62 assertions)`

- [ ] **Step 6: Manual verification**

Visit `/admin/fila-atendimentos` — table auto-refreshes every 5 s, shows queue entries. Visit `/admin/atendimento-atribuicoes` and `/admin/atendimento-eventos` — read-only tables with filters.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/FilaAtendimentoResource.php \
        app/Filament/Resources/FilaAtendimentoResource/ \
        app/Filament/Resources/AtendimentoAtribuicaoResource.php \
        app/Filament/Resources/AtendimentoAtribuicaoResource/ \
        app/Filament/Resources/AtendimentoEventoResource.php \
        app/Filament/Resources/AtendimentoEventoResource/
git commit -m "feat: add read-only Resources for fila, atribuicoes and eventos"
```

---

### Task 5: Stats Overview Widgets

**Files:**
- Create: `app/Filament/Widgets/AtendimentosOverviewWidget.php`
- Create: `app/Filament/Widgets/AtendentesOverviewWidget.php`
- Create: `app/Filament/Widgets/TemposMediosWidget.php`

**Interfaces:**
- Consumes: all Status constants, `AtendimentoAtribuicao::STATUS_ATIVO`, cross-db time SQL pattern from existing `DashboardController`
- Produces: 3 Stats widgets auto-discovered at `/admin` dashboard

- [ ] **Step 1: Generate stats widget scaffolds**

```bash
docker exec laravel_app_flowpay php artisan make:filament-widget AtendimentosOverviewWidget --stats-overview
docker exec laravel_app_flowpay php artisan make:filament-widget AtendentesOverviewWidget --stats-overview
docker exec laravel_app_flowpay php artisan make:filament-widget TemposMediosWidget --stats-overview
```

- [ ] **Step 2: Implement AtendimentosOverviewWidget**

Replace `app/Filament/Widgets/AtendimentosOverviewWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Atendimento;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AtendimentosOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return Cache::remember('widget.atendimentos_overview', 30, function () {
            $sparkline = Atendimento::query()
                ->where('criado_em', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw("DATE(criado_em) as dia, COUNT(*) as total")
                ->groupBy('dia')
                ->orderBy('dia')
                ->pluck('total')
                ->toArray();

            return [
                Stat::make('Criados hoje', Atendimento::whereDate('criado_em', today())->count())
                    ->chart($sparkline)
                    ->color('primary'),
                Stat::make('Em andamento', Atendimento::where('status', Atendimento::STATUS_EM_ATENDIMENTO)->count())
                    ->color('success'),
                Stat::make('Aguardando na fila', Atendimento::where('status', Atendimento::STATUS_AGUARDANDO)->count())
                    ->color('warning'),
                Stat::make('Finalizados hoje',
                    Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
                        ->whereDate('finalizado_em', today())
                        ->count()
                )->color('gray'),
            ];
        });
    }
}
```

- [ ] **Step 3: Implement AtendentesOverviewWidget**

Replace `app/Filament/Widgets/AtendentesOverviewWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Atendente;
use App\Models\AtendimentoAtribuicao;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AtendentesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return Cache::remember('widget.atendentes_overview', 15, function () {
            $online = Atendente::where('status', Atendente::STATUS_ONLINE)->get();
            $pausados = Atendente::where('status', Atendente::STATUS_PAUSADO)->count();

            $totalSlots = $online->sum('max_atendimentos_simultaneos');
            $ativas = AtendimentoAtribuicao::where('status', AtendimentoAtribuicao::STATUS_ATIVO)->count();
            $taxaOcupacao = $totalSlots > 0 ? round(($ativas / $totalSlots) * 100, 1) : 0;

            return [
                Stat::make('Atendentes online', $online->count())->color('success'),
                Stat::make('Atendentes pausados', $pausados)->color('warning'),
                Stat::make('Taxa de ocupação', "{$taxaOcupacao}%")->color($taxaOcupacao > 80 ? 'danger' : 'primary'),
            ];
        });
    }
}
```

- [ ] **Step 4: Implement TemposMediosWidget**

Replace `app/Filament/Widgets/TemposMediosWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Atendimento;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TemposMediosWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        return Cache::remember('widget.tempos_medios', 60, function () {
            $driver = DB::connection()->getDriverName();

            $diffMinutes = match ($driver) {
                'sqlite' => fn(string $a, string $b) => "(julianday({$a}) - julianday({$b})) * 1440",
                'pgsql'  => fn(string $a, string $b) => "EXTRACT(EPOCH FROM ({$a}::timestamp - {$b}::timestamp)) / 60",
                default  => fn(string $a, string $b) => "TIMESTAMPDIFF(MINUTE, {$b}, {$a})",
            };

            $esperaResult = Atendimento::whereNotNull('entrou_na_fila_em')
                ->whereNotNull('iniciado_em')
                ->selectRaw("AVG({$diffMinutes('iniciado_em', 'entrou_na_fila_em')}) as media")
                ->value('media');

            $atendimentoResult = Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
                ->whereNotNull('iniciado_em')
                ->whereNotNull('finalizado_em')
                ->selectRaw("AVG({$diffMinutes('finalizado_em', 'iniciado_em')}) as media")
                ->value('media');

            $totalResult = Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
                ->whereNotNull('finalizado_em')
                ->selectRaw("AVG({$diffMinutes('finalizado_em', 'criado_em')}) as media")
                ->value('media');

            return [
                Stat::make('Tempo médio de espera',
                    $esperaResult !== null ? round((float) $esperaResult, 1) . ' min' : 'N/A'
                )->color('warning'),
                Stat::make('Tempo médio de atendimento',
                    $atendimentoResult !== null ? round((float) $atendimentoResult, 1) . ' min' : 'N/A'
                )->color('primary'),
                Stat::make('Tempo médio total',
                    $totalResult !== null ? round((float) $totalResult, 1) . ' min' : 'N/A'
                )->color('gray'),
            ];
        });
    }
}
```

- [ ] **Step 5: Run tests**

```bash
docker exec laravel_app_flowpay php artisan test --no-coverage
```

Expected: `Tests: 14 passed (62 assertions)`

- [ ] **Step 6: Manual verification**

Visit `/admin`. Verify 3 Stats rows appear at top with correct counts. Run `php artisan cache:clear` — numbers should recompute.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Widgets/AtendimentosOverviewWidget.php \
        app/Filament/Widgets/AtendentesOverviewWidget.php \
        app/Filament/Widgets/TemposMediosWidget.php
git commit -m "feat: add Stats Overview widgets with Redis cache"
```

---

### Task 6: Chart Widgets

**Files:**
- Create: `app/Filament/Widgets/AtendimentosPorTimeChart.php`
- Create: `app/Filament/Widgets/AtendimentosPorAssuntoChart.php`
- Create: `app/Filament/Widgets/AtendimentosUltimos7DiasChart.php`

**Interfaces:**
- Produces: 3 chart widgets at `/admin` with period filters

- [ ] **Step 1: Generate chart widget scaffolds**

```bash
docker exec laravel_app_flowpay php artisan make:filament-widget AtendimentosPorTimeChart --chart
docker exec laravel_app_flowpay php artisan make:filament-widget AtendimentosPorAssuntoChart --chart
docker exec laravel_app_flowpay php artisan make:filament-widget AtendimentosUltimos7DiasChart --chart
```

- [ ] **Step 2: Implement AtendimentosPorTimeChart**

Replace `app/Filament/Widgets/AtendimentosPorTimeChart.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Atendimento;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class AtendimentosPorTimeChart extends ChartWidget
{
    protected static ?string $heading = 'Atendimentos por Time';
    protected static ?int $sort = 4;
    public ?string $filter = 'hoje';

    protected function getFilters(): ?array
    {
        return [
            'hoje'   => 'Hoje',
            'semana' => 'Esta semana',
            'mes'    => 'Este mês',
        ];
    }

    protected function getData(): array
    {
        return Cache::remember("widget.por_time.{$this->filter}", 60, function () {
            $query = Atendimento::query()
                ->join('times_atendimento', 'atendimentos.time_atendimento_id', '=', 'times_atendimento.id')
                ->selectRaw('times_atendimento.nome as time_nome, COUNT(atendimentos.id) as total')
                ->groupBy('times_atendimento.nome');

            match ($this->filter) {
                'hoje'   => $query->whereDate('atendimentos.criado_em', today()),
                'semana' => $query->whereBetween('atendimentos.criado_em', [now()->startOfWeek(), now()->endOfWeek()]),
                'mes'    => $query->whereMonth('atendimentos.criado_em', now()->month)
                                  ->whereYear('atendimentos.criado_em', now()->year),
            };

            $data = $query->get();

            return [
                'datasets' => [[
                    'label'           => 'Atendimentos',
                    'data'            => $data->pluck('total')->toArray(),
                    'backgroundColor' => ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6'],
                ]],
                'labels' => $data->pluck('time_nome')->toArray(),
            ];
        });
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
```

- [ ] **Step 3: Implement AtendimentosPorAssuntoChart**

Replace `app/Filament/Widgets/AtendimentosPorAssuntoChart.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Atendimento;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class AtendimentosPorAssuntoChart extends ChartWidget
{
    protected static ?string $heading = 'Top 10 Assuntos';
    protected static ?int $sort = 5;
    public ?string $filter = 'hoje';

    protected function getFilters(): ?array
    {
        return [
            'hoje'   => 'Hoje',
            'semana' => 'Esta semana',
            'mes'    => 'Este mês',
        ];
    }

    protected function getData(): array
    {
        return Cache::remember("widget.por_assunto.{$this->filter}", 60, function () {
            $query = Atendimento::query()
                ->join('assuntos', 'atendimentos.assunto_id', '=', 'assuntos.id')
                ->selectRaw('assuntos.nome as assunto_nome, COUNT(atendimentos.id) as total')
                ->groupBy('assuntos.nome')
                ->orderByDesc('total')
                ->limit(10);

            match ($this->filter) {
                'hoje'   => $query->whereDate('atendimentos.criado_em', today()),
                'semana' => $query->whereBetween('atendimentos.criado_em', [now()->startOfWeek(), now()->endOfWeek()]),
                'mes'    => $query->whereMonth('atendimentos.criado_em', now()->month)
                                  ->whereYear('atendimentos.criado_em', now()->year),
            };

            $data = $query->get();

            return [
                'datasets' => [[
                    'label'           => 'Total',
                    'data'            => $data->pluck('total')->toArray(),
                    'backgroundColor' => '#f59e0b',
                ]],
                'labels' => $data->pluck('assunto_nome')->toArray(),
            ];
        });
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
```

- [ ] **Step 4: Implement AtendimentosUltimos7DiasChart**

Replace `app/Filament/Widgets/AtendimentosUltimos7DiasChart.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Atendimento;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class AtendimentosUltimos7DiasChart extends ChartWidget
{
    protected static ?string $heading = 'Volume — Últimos 7 Dias';
    protected static ?int $sort = 6;

    protected function getData(): array
    {
        return Cache::remember('widget.ultimos7dias', 60, function () {
            $labels = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('d/m'));
            $dates  = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->toDateString());

            $criados = Atendimento::query()
                ->whereIn(\Illuminate\Support\Facades\DB::raw('DATE(criado_em)'), $dates)
                ->selectRaw('DATE(criado_em) as dia, COUNT(*) as total')
                ->groupBy('dia')
                ->pluck('total', 'dia');

            $finalizados = Atendimento::query()
                ->where('status', Atendimento::STATUS_FINALIZADO)
                ->whereIn(\Illuminate\Support\Facades\DB::raw('DATE(finalizado_em)'), $dates)
                ->selectRaw('DATE(finalizado_em) as dia, COUNT(*) as total')
                ->groupBy('dia')
                ->pluck('total', 'dia');

            return [
                'datasets' => [
                    [
                        'label'       => 'Criados',
                        'data'        => $dates->map(fn($d) => $criados[$d] ?? 0)->toArray(),
                        'borderColor' => '#f59e0b',
                        'fill'        => false,
                    ],
                    [
                        'label'       => 'Finalizados',
                        'data'        => $dates->map(fn($d) => $finalizados[$d] ?? 0)->toArray(),
                        'borderColor' => '#10b981',
                        'fill'        => false,
                    ],
                ],
                'labels' => $labels->toArray(),
            ];
        });
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

- [ ] **Step 5: Run tests**

```bash
docker exec laravel_app_flowpay php artisan test --no-coverage
```

Expected: `Tests: 14 passed (62 assertions)`

- [ ] **Step 6: Manual verification**

Visit `/admin`. Verify 3 chart widgets appear below stats. Test period filter (hoje/semana/mês) on the first two charts.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Widgets/AtendimentosPorTimeChart.php \
        app/Filament/Widgets/AtendimentosPorAssuntoChart.php \
        app/Filament/Widgets/AtendimentosUltimos7DiasChart.php
git commit -m "feat: add Chart widgets for dashboard (por time, por assunto, 7 dias)"
```

---

### Task 7: Table Widgets

**Files:**
- Create: `app/Filament/Widgets/CargaAtendentesTableWidget.php`
- Create: `app/Filament/Widgets/FilaAtualTableWidget.php`

**Interfaces:**
- Produces: 2 table widgets with polling at `/admin`

- [ ] **Step 1: Generate table widget scaffolds**

```bash
docker exec laravel_app_flowpay php artisan make:filament-widget CargaAtendentesTableWidget --table
docker exec laravel_app_flowpay php artisan make:filament-widget FilaAtualTableWidget --table
```

- [ ] **Step 2: Implement CargaAtendentesTableWidget**

Replace `app/Filament/Widgets/CargaAtendentesTableWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Atendente;
use App\Models\AtendimentoAtribuicao;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CargaAtendentesTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Carga dos Atendentes Online';
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Atendente::query()
                    ->where('status', Atendente::STATUS_ONLINE)
                    ->withCount([
                        'atribuicoes as ativas_count' => fn($q) =>
                            $q->where('status', AtendimentoAtribuicao::STATUS_ATIVO),
                    ])
                    ->orderByDesc('ativas_count')
            )
            ->poll('10s')
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('time.nome')->label('Time'),
                TextColumn::make('carga')
                    ->label('Carga (ativas/máx)')
                    ->getStateUsing(fn(Atendente $record): string =>
                        $record->ativas_count . '/' . $record->max_atendimentos_simultaneos
                    ),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'online'  => 'success',
                        'pausado' => 'warning',
                        default   => 'gray',
                    }),
            ]);
    }
}
```

- [ ] **Step 3: Implement FilaAtualTableWidget**

Replace `app/Filament/Widgets/FilaAtualTableWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\FilaAtendimento;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FilaAtualTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Fila Atual';
    protected static ?int $sort = 8;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FilaAtendimento::query()
                    ->where('status', FilaAtendimento::STATUS_AGUARDANDO)
                    ->with(['atendimento.cliente', 'atendimento.assunto', 'time'])
                    ->orderBy('entrou_em', 'asc')
            )
            ->poll('5s')
            ->columns([
                TextColumn::make('time.nome')->label('Time'),
                TextColumn::make('atendimento.cliente.nome')->label('Cliente'),
                TextColumn::make('atendimento.assunto.nome')->label('Assunto'),
                TextColumn::make('tempo_espera')
                    ->label('Esperando')
                    ->getStateUsing(fn(FilaAtendimento $record): string =>
                        now()->diffInMinutes($record->entrou_em) . ' min'
                    )
                    ->color(fn(FilaAtendimento $record): string =>
                        now()->diffInMinutes($record->entrou_em) > 10 ? 'danger' : 'success'
                    ),
                TextColumn::make('entrou_em')
                    ->label('Entrou')
                    ->dateTime('H:i:s'),
            ]);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
docker exec laravel_app_flowpay php artisan test --no-coverage
```

Expected: `Tests: 14 passed (62 assertions)`

- [ ] **Step 5: Manual verification**

Visit `/admin`. Verify 2 table widgets appear at bottom of dashboard. `FilaAtualTableWidget` shows danger color for entries > 10 min. Both auto-refresh.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Widgets/CargaAtendentesTableWidget.php \
        app/Filament/Widgets/FilaAtualTableWidget.php
git commit -m "feat: add CargaAtendentes and FilaAtual table widgets with polling"
```

---

### Task 8: Policies

**Files:**
- Create: `app/Policies/ClientePolicy.php`
- Create: `app/Policies/TimeAtendimentoPolicy.php`
- Create: `app/Policies/AssuntoPolicy.php`
- Create: `app/Policies/AtendentePolicy.php`
- Create: `app/Policies/AtendimentoPolicy.php`
- Create: `app/Policies/FilaAtendimentoPolicy.php`
- Create: `app/Policies/AtendimentoAtribuicaoPolicy.php`
- Create: `app/Policies/AtendimentoEventoPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Consumes: `User::isAdmin()`, `User::isCoordenador()`, `User::isAtendente()`, `User::$time_atendimento_id`, `User::atendente` relation (Task 1)
- Produces: role-based access control enforced by Filament automatically

- [ ] **Step 1: Create ClientePolicy**

Create `app/Policies/ClientePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || $user->isCoordenador(); }
    public function view(User $user, Cliente $cliente): bool { return $user->isAdmin() || $user->isCoordenador(); }
    public function create(User $user): bool { return $user->isAdmin() || $user->isCoordenador(); }
    public function update(User $user, Cliente $cliente): bool { return $user->isAdmin() || $user->isCoordenador(); }
    public function delete(User $user, Cliente $cliente): bool { return $user->isAdmin(); }
}
```

- [ ] **Step 2: Create TimeAtendimentoPolicy**

Create `app/Policies/TimeAtendimentoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\TimeAtendimento;
use App\Models\User;

class TimeAtendimentoPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || $user->isCoordenador(); }
    public function view(User $user, TimeAtendimento $time): bool { return $user->isAdmin() || $user->isCoordenador(); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, TimeAtendimento $time): bool { return $user->isAdmin(); }
    public function delete(User $user, TimeAtendimento $time): bool { return $user->isAdmin(); }
}
```

- [ ] **Step 3: Create AssuntoPolicy**

Create `app/Policies/AssuntoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Assunto;
use App\Models\User;

class AssuntoPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || $user->isCoordenador(); }
    public function view(User $user, Assunto $assunto): bool { return $user->isAdmin() || $user->isCoordenador(); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Assunto $assunto): bool { return $user->isAdmin(); }
    public function delete(User $user, Assunto $assunto): bool { return $user->isAdmin(); }
}
```

- [ ] **Step 4: Create AtendentePolicy**

Create `app/Policies/AtendentePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Atendente;
use App\Models\User;

class AtendentePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCoordenador() || $user->isAtendente();
    }

    public function view(User $user, Atendente $atendente): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isCoordenador()) return $atendente->time_atendimento_id === $user->time_atendimento_id;
        return $user->atendente?->id === $atendente->id;
    }

    public function create(User $user): bool { return $user->isAdmin(); }

    public function update(User $user, Atendente $atendente): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isCoordenador()) return $atendente->time_atendimento_id === $user->time_atendimento_id;
        return false;
    }

    public function delete(User $user, Atendente $atendente): bool { return $user->isAdmin(); }
}
```

- [ ] **Step 5: Create AtendimentoPolicy**

Create `app/Policies/AtendimentoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Atendimento;
use App\Models\User;

class AtendimentoPolicy
{
    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Atendimento $atendimento): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isCoordenador()) return $atendimento->time_atendimento_id === $user->time_atendimento_id;
        return $atendimento->atribuicaoAtiva?->atendente_id === $user->atendente?->id;
    }

    public function create(User $user): bool { return $user->isAdmin() || $user->isCoordenador(); }

    public function update(User $user, Atendimento $atendimento): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isCoordenador()) return $atendimento->time_atendimento_id === $user->time_atendimento_id;
        return false;
    }

    public function delete(User $user, Atendimento $atendimento): bool { return $user->isAdmin(); }
}
```

- [ ] **Step 6: Create remaining Policies**

Create `app/Policies/FilaAtendimentoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\FilaAtendimento;
use App\Models\User;

class FilaAtendimentoPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || $user->isCoordenador(); }

    public function view(User $user, FilaAtendimento $fila): bool
    {
        if ($user->isAdmin()) return true;
        return $fila->time_atendimento_id === $user->time_atendimento_id;
    }

    public function create(User $user): bool { return false; }
    public function update(User $user, FilaAtendimento $fila): bool { return false; }
    public function delete(User $user, FilaAtendimento $fila): bool { return false; }
}
```

Create `app/Policies/AtendimentoAtribuicaoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\AtendimentoAtribuicao;
use App\Models\User;

class AtendimentoAtribuicaoPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || $user->isCoordenador() || $user->isAtendente(); }

    public function view(User $user, AtendimentoAtribuicao $atribuicao): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isCoordenador()) return $atribuicao->atendimento?->time_atendimento_id === $user->time_atendimento_id;
        return $atribuicao->atendente_id === $user->atendente?->id;
    }

    public function create(User $user): bool { return false; }
    public function update(User $user, AtendimentoAtribuicao $atribuicao): bool { return false; }
    public function delete(User $user, AtendimentoAtribuicao $atribuicao): bool { return false; }
}
```

Create `app/Policies/AtendimentoEventoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\AtendimentoEvento;
use App\Models\User;

class AtendimentoEventoPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || $user->isCoordenador() || $user->isAtendente(); }

    public function view(User $user, AtendimentoEvento $evento): bool
    {
        if ($user->isAdmin()) return true;
        if ($user->isCoordenador()) return $evento->atendimento?->time_atendimento_id === $user->time_atendimento_id;
        return $evento->atendimento?->atribuicaoAtiva?->atendente_id === $user->atendente?->id;
    }

    public function create(User $user): bool { return false; }
    public function update(User $user, AtendimentoEvento $evento): bool { return false; }
    public function delete(User $user, AtendimentoEvento $evento): bool { return false; }
}
```

- [ ] **Step 7: Register all policies in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, update `boot()` to add Gate registrations. The file currently only has `Atendimento::observe(AtendimentoObserver::class)`:

```php
<?php

namespace App\Providers;

use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\AtendimentoEvento;
use App\Models\Atendente;
use App\Models\Assunto;
use App\Models\Cliente;
use App\Models\FilaAtendimento;
use App\Models\TimeAtendimento;
use App\Observers\AtendimentoObserver;
use App\Policies\AssuntoPolicy;
use App\Policies\AtendimentoAtribuicaoPolicy;
use App\Policies\AtendimentoEventoPolicy;
use App\Policies\AtendimentoPolicy;
use App\Policies\AtendentePolicy;
use App\Policies\ClientePolicy;
use App\Policies\FilaAtendimentoPolicy;
use App\Policies\TimeAtendimentoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Atendimento::observe(AtendimentoObserver::class);

        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(TimeAtendimento::class, TimeAtendimentoPolicy::class);
        Gate::policy(Assunto::class, AssuntoPolicy::class);
        Gate::policy(Atendente::class, AtendentePolicy::class);
        Gate::policy(Atendimento::class, AtendimentoPolicy::class);
        Gate::policy(FilaAtendimento::class, FilaAtendimentoPolicy::class);
        Gate::policy(AtendimentoAtribuicao::class, AtendimentoAtribuicaoPolicy::class);
        Gate::policy(AtendimentoEvento::class, AtendimentoEventoPolicy::class);
    }
}
```

- [ ] **Step 8: Run tests**

```bash
docker exec laravel_app_flowpay php artisan test --no-coverage
```

Expected: `Tests: 14 passed (62 assertions)` — policies only affect authenticated Filament requests, not the API tests.

- [ ] **Step 9: Manual verification**

Log in to `/admin` as `admin@hotmail.com`. Verify all 8 Resources are still accessible (admin sees everything). Test that creating a user with `role='atendente'` and logging in shows only the Atendimentos resource with restricted access.

- [ ] **Step 10: Commit**

```bash
git add app/Policies/ app/Providers/AppServiceProvider.php
git commit -m "feat: add role-based Policies for all Filament resources"
```
