# Sistema de Atendimento FlowPay — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a customer service module with automatic distribution to specialized teams, queue management, and an operational dashboard.

**Architecture:** REST API backed by Laravel 12 services — `AtendimentoDistribuicaoService` resolves the team from the subject, finds an available attendant (online + below capacity), assigns or enqueues; `FilaAtendimentoService` pops the queue when an attendant frees up, dispatched via a Laravel Job so the finalize request is not blocked. An Eloquent observer on `Atendimento` populates `time_atendimento_id` from `assunto_id` and logs status-change events to `atendimento_eventos`.

**Tech Stack:** Laravel 12, PHP 8.2+, Filament v5, SQLite (tests), Redis/queue via `predis`, PHPUnit 11.

## Global Constraints

- PHP ≥ 8.2 — use named arguments, enums, readonly where it helps readability
- All tests use SQLite in-memory (`DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`)
- Queue connection in tests is `sync` — jobs run inline, no async needed in tests
- No authentication on API endpoints (out of scope for this task)
- `posicao` column is NOT stored; queue position derived from `entrou_em ASC` (per task notes)
- Backend validates the 3-simultaneous-attendances limit — do not rely on DB constraints alone
- Every state transition must produce a record in `atendimento_eventos`

---

## File Map

**New files:**
```
database/migrations/2026_06_27_000002_create_clientes_table.php
database/migrations/2026_06_27_000003_create_times_atendimento_table.php
database/migrations/2026_06_27_000004_create_assuntos_table.php
database/migrations/2026_06_27_000005_create_atendentes_table.php
database/migrations/2026_06_27_000006_create_atendimentos_table.php
database/migrations/2026_06_27_000007_create_fila_atendimentos_table.php
database/migrations/2026_06_27_000008_create_atendimento_atribuicoes_table.php
database/migrations/2026_06_27_000009_create_atendimento_eventos_table.php
app/Models/Cliente.php
app/Models/TimeAtendimento.php
app/Models/Assunto.php
app/Models/Atendente.php
app/Models/Atendimento.php
app/Models/FilaAtendimento.php
app/Models/AtendimentoAtribuicao.php
app/Models/AtendimentoEvento.php
app/Services/AtendimentoDistribuicaoService.php
app/Services/FilaAtendimentoService.php
app/Jobs/ProcessarFilaAtendimentoJob.php
app/Observers/AtendimentoObserver.php
app/Http/Controllers/Api/AtendimentoController.php
app/Http/Controllers/Api/DashboardController.php
routes/api.php
database/seeders/TimeAtendimentoSeeder.php
database/seeders/AssuntoSeeder.php
database/seeders/AtendenteSeeder.php
tests/Feature/AtendimentoDistribuicaoTest.php
tests/Feature/AtendimentoFinalizacaoTest.php
tests/Feature/AtendimentoTransferenciaTest.php
tests/Feature/DashboardTest.php
```

**Modified files:**
```
bootstrap/app.php                          — add api routes
app/Providers/AppServiceProvider.php       — register AtendimentoObserver
database/seeders/DatabaseSeeder.php        — call new seeders
```

---

## Task 1: Migrations and Models

**Files:**
- Create: `database/migrations/2026_06_27_000002_create_clientes_table.php`
- Create: `database/migrations/2026_06_27_000003_create_times_atendimento_table.php`
- Create: `database/migrations/2026_06_27_000004_create_assuntos_table.php`
- Create: `database/migrations/2026_06_27_000005_create_atendentes_table.php`
- Create: `database/migrations/2026_06_27_000006_create_atendimentos_table.php`
- Create: `database/migrations/2026_06_27_000007_create_fila_atendimentos_table.php`
- Create: `database/migrations/2026_06_27_000008_create_atendimento_atribuicoes_table.php`
- Create: `database/migrations/2026_06_27_000009_create_atendimento_eventos_table.php`
- Create: `app/Models/Cliente.php`
- Create: `app/Models/TimeAtendimento.php`
- Create: `app/Models/Assunto.php`
- Create: `app/Models/Atendente.php`
- Create: `app/Models/Atendimento.php`
- Create: `app/Models/FilaAtendimento.php`
- Create: `app/Models/AtendimentoAtribuicao.php`
- Create: `app/Models/AtendimentoEvento.php`

**Interfaces produced:**
- `Cliente::firstOrCreate(['documento' => string], [...])` — used in Task 4
- `Atendente::STATUS_ONLINE`, `::STATUS_OFFLINE`, `::STATUS_PAUSADO` — used in Tasks 3, 4
- `Atendimento::STATUS_AGUARDANDO`, `::STATUS_EM_ATENDIMENTO`, `::STATUS_FINALIZADO`, `::STATUS_CANCELADO` — used in Tasks 3, 4, 5
- `AtendimentoAtribuicao::STATUS_ATIVO`, `::STATUS_FINALIZADO`, `::STATUS_TRANSFERIDO` — used in Tasks 3, 4
- `Atendente::estaDisponivel(): bool` — used in Task 3
- `Atendimento->atribuicaoAtiva: HasOne` — used in Tasks 3, 4
- `Atendimento->eventos: HasMany` — used in Task 5

- [ ] **Step 1: Create migration for `clientes`**

```php
// database/migrations/2026_06_27_000002_create_clientes_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email');
            $table->string('telefone', 20)->nullable();
            $table->string('documento', 20)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
```

- [ ] **Step 2: Create migration for `times_atendimento`**

```php
// database/migrations/2026_06_27_000003_create_times_atendimento_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('times_atendimento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('times_atendimento');
    }
};
```

- [ ] **Step 3: Create migration for `assuntos`**

```php
// database/migrations/2026_06_27_000004_create_assuntos_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assuntos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->foreignId('time_atendimento_id')->constrained('times_atendimento');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assuntos');
    }
};
```

- [ ] **Step 4: Create migration for `atendentes`**

```php
// database/migrations/2026_06_27_000005_create_atendentes_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendentes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->foreignId('time_atendimento_id')->constrained('times_atendimento');
            $table->enum('status', ['online', 'offline', 'pausado'])->default('offline');
            $table->unsignedTinyInteger('max_atendimentos_simultaneos')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendentes');
    }
};
```

- [ ] **Step 5: Create migration for `atendimentos`**

```php
// database/migrations/2026_06_27_000006_create_atendimentos_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('assunto_id')->constrained('assuntos');
            $table->foreignId('time_atendimento_id')->constrained('times_atendimento');
            $table->enum('status', ['aguardando', 'em_atendimento', 'finalizado', 'cancelado'])
                  ->default('aguardando');
            $table->timestamp('criado_em')->useCurrent();
            $table->timestamp('entrou_na_fila_em')->nullable();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimentos');
    }
};
```

- [ ] **Step 6: Create migration for `fila_atendimentos`**

```php
// database/migrations/2026_06_27_000007_create_fila_atendimentos_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fila_atendimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos');
            $table->foreignId('time_atendimento_id')->constrained('times_atendimento');
            $table->enum('status', ['aguardando', 'processado', 'cancelado'])->default('aguardando');
            $table->timestamp('entrou_em')->useCurrent();

            $table->index(['time_atendimento_id', 'status', 'entrou_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fila_atendimentos');
    }
};
```

- [ ] **Step 7: Create migration for `atendimento_atribuicoes`**

```php
// database/migrations/2026_06_27_000008_create_atendimento_atribuicoes_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_atribuicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos');
            $table->foreignId('atendente_id')->constrained('atendentes');
            $table->enum('status', ['ativo', 'finalizado', 'transferido'])->default('ativo');
            $table->timestamp('criado_em')->useCurrent();
            $table->timestamp('finalizado_em')->nullable();

            $table->index(['atendente_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_atribuicoes');
    }
};
```

- [ ] **Step 8: Create migration for `atendimento_eventos`**

```php
// database/migrations/2026_06_27_000009_create_atendimento_eventos_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos');
            $table->string('tipo', 50);
            $table->string('descricao');
            $table->json('dados')->nullable();
            $table->timestamp('criado_em')->useCurrent();

            $table->index(['atendimento_id', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_eventos');
    }
};
```

- [ ] **Step 9: Create Model `Cliente`**

```php
// app/Models/Cliente.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = ['nome', 'email', 'telefone', 'documento'];

    public function atendimentos(): HasMany
    {
        return $this->hasMany(Atendimento::class);
    }
}
```

- [ ] **Step 10: Create Model `TimeAtendimento`**

```php
// app/Models/TimeAtendimento.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeAtendimento extends Model
{
    protected $fillable = ['nome', 'slug'];

    public function assuntos(): HasMany
    {
        return $this->hasMany(Assunto::class);
    }

    public function atendentes(): HasMany
    {
        return $this->hasMany(Atendente::class);
    }

    public function filaAtendimentos(): HasMany
    {
        return $this->hasMany(FilaAtendimento::class);
    }
}
```

- [ ] **Step 11: Create Model `Assunto`**

```php
// app/Models/Assunto.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assunto extends Model
{
    protected $fillable = ['nome', 'time_atendimento_id', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function time(): BelongsTo
    {
        return $this->belongsTo(TimeAtendimento::class, 'time_atendimento_id');
    }

    public function atendimentos(): HasMany
    {
        return $this->hasMany(Atendimento::class);
    }
}
```

- [ ] **Step 12: Create Model `Atendente`**

```php
// app/Models/Atendente.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Atendente extends Model
{
    const STATUS_ONLINE  = 'online';
    const STATUS_OFFLINE = 'offline';
    const STATUS_PAUSADO = 'pausado';

    protected $fillable = [
        'nome', 'email', 'time_atendimento_id', 'status', 'max_atendimentos_simultaneos',
    ];

    public function time(): BelongsTo
    {
        return $this->belongsTo(TimeAtendimento::class, 'time_atendimento_id');
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(AtendimentoAtribuicao::class);
    }

    public function atribuicoesAtivas(): HasMany
    {
        return $this->hasMany(AtendimentoAtribuicao::class)
                    ->where('status', AtendimentoAtribuicao::STATUS_ATIVO);
    }

    public function atendimentosAtivosCount(): int
    {
        return $this->atribuicoesAtivas()->count();
    }

    public function estaDisponivel(): bool
    {
        return $this->status === self::STATUS_ONLINE
            && $this->atendimentosAtivosCount() < $this->max_atendimentos_simultaneos;
    }
}
```

- [ ] **Step 13: Create Model `Atendimento`**

```php
// app/Models/Atendimento.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Atendimento extends Model
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'updated_at';

    const STATUS_AGUARDANDO    = 'aguardando';
    const STATUS_EM_ATENDIMENTO = 'em_atendimento';
    const STATUS_FINALIZADO    = 'finalizado';
    const STATUS_CANCELADO     = 'cancelado';

    protected $fillable = [
        'cliente_id', 'assunto_id', 'time_atendimento_id',
        'status', 'entrou_na_fila_em', 'iniciado_em', 'finalizado_em',
    ];

    protected $casts = [
        'criado_em'         => 'datetime',
        'entrou_na_fila_em' => 'datetime',
        'iniciado_em'       => 'datetime',
        'finalizado_em'     => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function assunto(): BelongsTo
    {
        return $this->belongsTo(Assunto::class);
    }

    public function time(): BelongsTo
    {
        return $this->belongsTo(TimeAtendimento::class, 'time_atendimento_id');
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(AtendimentoAtribuicao::class);
    }

    public function atribuicaoAtiva(): HasOne
    {
        return $this->hasOne(AtendimentoAtribuicao::class)
                    ->where('status', AtendimentoAtribuicao::STATUS_ATIVO);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(AtendimentoEvento::class);
    }

    public function filaAtendimento(): HasOne
    {
        return $this->hasOne(FilaAtendimento::class);
    }
}
```

- [ ] **Step 14: Create Model `FilaAtendimento`**

```php
// app/Models/FilaAtendimento.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilaAtendimento extends Model
{
    const CREATED_AT = 'entrou_em';
    const UPDATED_AT = null;

    const STATUS_AGUARDANDO = 'aguardando';
    const STATUS_PROCESSADO = 'processado';
    const STATUS_CANCELADO  = 'cancelado';

    protected $fillable = ['atendimento_id', 'time_atendimento_id', 'status'];

    protected $casts = ['entrou_em' => 'datetime'];

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class);
    }

    public function time(): BelongsTo
    {
        return $this->belongsTo(TimeAtendimento::class, 'time_atendimento_id');
    }
}
```

- [ ] **Step 15: Create Model `AtendimentoAtribuicao`**

```php
// app/Models/AtendimentoAtribuicao.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtendimentoAtribuicao extends Model
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = null;

    const STATUS_ATIVO      = 'ativo';
    const STATUS_FINALIZADO = 'finalizado';
    const STATUS_TRANSFERIDO = 'transferido';

    protected $fillable = ['atendimento_id', 'atendente_id', 'status', 'finalizado_em'];

    protected $casts = [
        'criado_em'    => 'datetime',
        'finalizado_em' => 'datetime',
    ];

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class);
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(Atendente::class);
    }
}
```

- [ ] **Step 16: Create Model `AtendimentoEvento`**

```php
// app/Models/AtendimentoEvento.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtendimentoEvento extends Model
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = null;

    protected $fillable = ['atendimento_id', 'tipo', 'descricao', 'dados'];

    protected $casts = [
        'dados'     => 'array',
        'criado_em' => 'datetime',
    ];

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class);
    }
}
```

- [ ] **Step 17: Run migrations to verify schema**

```bash
php artisan migrate:fresh
```

Expected output: `INFO  Running migrations.` followed by all 9 migration names. No errors.

- [ ] **Step 18: Commit**

```bash
git add database/migrations/ app/Models/
git commit -m "feat: add migrations and models for sistema de atendimento"
```

---

## Task 2: Seeders

**Files:**
- Create: `database/seeders/TimeAtendimentoSeeder.php`
- Create: `database/seeders/AssuntoSeeder.php`
- Create: `database/seeders/AtendenteSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces produced:**
- Seeds: times `Cartões` (slug `cartoes`), `Empréstimos` (slug `emprestimos`), `Outros` (slug `outros`)
- Seeds: 3 assuntos per time (9 total)
- Seeds: 5 atendentes distributed across teams, all offline by default

- [ ] **Step 1: Create `TimeAtendimentoSeeder`**

```php
// database/seeders/TimeAtendimentoSeeder.php
<?php

namespace Database\Seeders;

use App\Models\TimeAtendimento;
use Illuminate\Database\Seeder;

class TimeAtendimentoSeeder extends Seeder
{
    public function run(): void
    {
        $times = [
            ['nome' => 'Cartões',     'slug' => 'cartoes'],
            ['nome' => 'Empréstimos', 'slug' => 'emprestimos'],
            ['nome' => 'Outros',      'slug' => 'outros'],
        ];

        foreach ($times as $time) {
            TimeAtendimento::firstOrCreate(['slug' => $time['slug']], $time);
        }
    }
}
```

- [ ] **Step 2: Create `AssuntoSeeder`**

```php
// database/seeders/AssuntoSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Assunto;
use App\Models\TimeAtendimento;
use Illuminate\Database\Seeder;

class AssuntoSeeder extends Seeder
{
    public function run(): void
    {
        $assuntos = [
            'cartoes'     => ['Segunda via de cartão', 'Bloqueio de cartão', 'Limite de crédito'],
            'emprestimos' => ['Simulação de empréstimo', 'Renegociação de dívida', 'Quitação antecipada'],
            'outros'      => ['Dados cadastrais', 'Extrato de conta', 'Reclamação geral'],
        ];

        foreach ($assuntos as $slug => $nomes) {
            $time = TimeAtendimento::where('slug', $slug)->firstOrFail();
            foreach ($nomes as $nome) {
                Assunto::firstOrCreate(
                    ['nome' => $nome, 'time_atendimento_id' => $time->id],
                    ['ativo' => true]
                );
            }
        }
    }
}
```

- [ ] **Step 3: Create `AtendenteSeeder`**

```php
// database/seeders/AtendenteSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Atendente;
use App\Models\TimeAtendimento;
use Illuminate\Database\Seeder;

class AtendenteSeeder extends Seeder
{
    public function run(): void
    {
        $atendentes = [
            ['nome' => 'Ana Silva',    'email' => 'ana@flowpay.com',    'time' => 'cartoes'],
            ['nome' => 'Bruno Costa',  'email' => 'bruno@flowpay.com',  'time' => 'cartoes'],
            ['nome' => 'Carla Nunes',  'email' => 'carla@flowpay.com',  'time' => 'emprestimos'],
            ['nome' => 'Diego Rocha',  'email' => 'diego@flowpay.com',  'time' => 'emprestimos'],
            ['nome' => 'Eva Martins',  'email' => 'eva@flowpay.com',    'time' => 'outros'],
        ];

        foreach ($atendentes as $data) {
            $time = TimeAtendimento::where('slug', $data['time'])->firstOrFail();
            Atendente::firstOrCreate(
                ['email' => $data['email']],
                [
                    'nome'                       => $data['nome'],
                    'time_atendimento_id'        => $time->id,
                    'status'                     => Atendente::STATUS_OFFLINE,
                    'max_atendimentos_simultaneos' => 3,
                ]
            );
        }
    }
}
```

- [ ] **Step 4: Update `DatabaseSeeder`**

```php
// database/seeders/DatabaseSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TimeAtendimentoSeeder::class,
            AssuntoSeeder::class,
            AtendenteSeeder::class,
        ]);
    }
}
```

- [ ] **Step 5: Run seeders to verify**

```bash
php artisan migrate:fresh --seed
```

Expected: No errors. Run `php artisan tinker --execute="echo App\Models\Atendente::count();"` and verify output is `5`.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/
git commit -m "feat: add seeders for times, assuntos and atendentes"
```

---

## Task 3: Service Layer

**Files:**
- Create: `app/Services/AtendimentoDistribuicaoService.php`
- Create: `app/Services/FilaAtendimentoService.php`
- Create: `app/Jobs/ProcessarFilaAtendimentoJob.php`

**Interfaces consumed:**
- `Atendente::STATUS_ONLINE`, `Atendente::estaDisponivel(): bool`, `Atendente->atribuicoesAtivas()` — from Task 1
- `Atendimento::STATUS_*`, `Atendimento->atribuicaoAtiva` — from Task 1
- `AtendimentoAtribuicao::STATUS_*` — from Task 1
- `FilaAtendimento::STATUS_*` — from Task 1

**Interfaces produced:**
- `AtendimentoDistribuicaoService::distribuir(Atendimento $atendimento): void` — used in Task 4
- `AtendimentoDistribuicaoService::atribuirAtendente(Atendimento $atendimento, Atendente $atendente): void` — used in FilaAtendimentoService
- `FilaAtendimentoService::processarProximo(int $timeAtendimentoId): void` — used in Job
- `ProcessarFilaAtendimentoJob` dispatched in Task 4

- [ ] **Step 1: Create `AtendimentoDistribuicaoService`**

```php
// app/Services/AtendimentoDistribuicaoService.php
<?php

namespace App\Services;

use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\AtendimentoEvento;
use App\Models\FilaAtendimento;
use Illuminate\Support\Facades\DB;

class AtendimentoDistribuicaoService
{
    public function distribuir(Atendimento $atendimento): void
    {
        $atendente = $this->encontrarAtendenteDisponivel($atendimento->time_atendimento_id);

        if ($atendente) {
            $this->atribuirAtendente($atendimento, $atendente);
        } else {
            $this->enfileirar($atendimento);
        }
    }

    public function encontrarAtendenteDisponivel(int $timeId): ?Atendente
    {
        $atendentes = Atendente::query()
            ->where('time_atendimento_id', $timeId)
            ->where('status', Atendente::STATUS_ONLINE)
            ->withCount([
                'atribuicoes as ativas_count' => fn($q) => $q->where('status', AtendimentoAtribuicao::STATUS_ATIVO),
            ])
            ->orderBy('ativas_count')
            ->get();

        return $atendentes->first(fn($a) => $a->ativas_count < $a->max_atendimentos_simultaneos);
    }

    public function atribuirAtendente(Atendimento $atendimento, Atendente $atendente): void
    {
        DB::transaction(function () use ($atendimento, $atendente) {
            // Re-check limit inside transaction to prevent race conditions
            $ativasCount = $atendente->atribuicoesAtivas()->lockForUpdate()->count();
            if ($ativasCount >= $atendente->max_atendimentos_simultaneos) {
                throw new \RuntimeException(
                    "Atendente {$atendente->id} atingiu o limite de {$atendente->max_atendimentos_simultaneos} atendimentos simultâneos."
                );
            }

            AtendimentoAtribuicao::create([
                'atendimento_id' => $atendimento->id,
                'atendente_id'   => $atendente->id,
                'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
            ]);

            $atendimento->update([
                'status'      => Atendimento::STATUS_EM_ATENDIMENTO,
                'iniciado_em' => now(),
            ]);

            AtendimentoEvento::create([
                'atendimento_id' => $atendimento->id,
                'tipo'           => 'atribuido',
                'descricao'      => "Atendimento atribuído ao atendente {$atendente->nome}.",
                'dados'          => ['atendente_id' => $atendente->id],
            ]);
        });
    }

    private function enfileirar(Atendimento $atendimento): void
    {
        $atendimento->update([
            'status'            => Atendimento::STATUS_AGUARDANDO,
            'entrou_na_fila_em' => now(),
        ]);

        FilaAtendimento::create([
            'atendimento_id'      => $atendimento->id,
            'time_atendimento_id' => $atendimento->time_atendimento_id,
            'status'              => FilaAtendimento::STATUS_AGUARDANDO,
        ]);

        AtendimentoEvento::create([
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'enfileirado',
            'descricao'      => 'Nenhum atendente disponível. Atendimento adicionado à fila.',
        ]);
    }
}
```

- [ ] **Step 2: Create `FilaAtendimentoService`**

```php
// app/Services/FilaAtendimentoService.php
<?php

namespace App\Services;

use App\Models\FilaAtendimento;

class FilaAtendimentoService
{
    public function __construct(
        private readonly AtendimentoDistribuicaoService $distribuicao
    ) {}

    public function processarProximo(int $timeAtendimentoId): void
    {
        $entrada = FilaAtendimento::query()
            ->where('time_atendimento_id', $timeAtendimentoId)
            ->where('status', FilaAtendimento::STATUS_AGUARDANDO)
            ->orderBy('entrou_em')
            ->with('atendimento')
            ->first();

        if (!$entrada) {
            return;
        }

        $atendente = $this->distribuicao->encontrarAtendenteDisponivel($timeAtendimentoId);

        if (!$atendente) {
            return;
        }

        $entrada->update(['status' => FilaAtendimento::STATUS_PROCESSADO]);
        $this->distribuicao->atribuirAtendente($entrada->atendimento, $atendente);
    }
}
```

- [ ] **Step 3: Create `ProcessarFilaAtendimentoJob`**

```php
// app/Jobs/ProcessarFilaAtendimentoJob.php
<?php

namespace App\Jobs;

use App\Services\FilaAtendimentoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessarFilaAtendimentoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $timeAtendimentoId
    ) {}

    public function handle(FilaAtendimentoService $service): void
    {
        $service->processarProximo($this->timeAtendimentoId);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/ app/Jobs/
git commit -m "feat: add AtendimentoDistribuicaoService, FilaAtendimentoService and ProcessarFilaAtendimentoJob"
```

---

## Task 4: API Routes and Controllers

**Files:**
- Modify: `bootstrap/app.php`
- Create: `routes/api.php`
- Create: `app/Http/Controllers/Api/AtendimentoController.php`
- Create: `app/Http/Controllers/Api/DashboardController.php`

**Interfaces consumed:**
- `AtendimentoDistribuicaoService::distribuir(Atendimento): void` — from Task 3
- `ProcessarFilaAtendimentoJob` — from Task 3
- `AtendimentoAtribuicao::STATUS_*` — from Task 1
- `Cliente::firstOrCreate(...)` — from Task 1
- `Atendimento::STATUS_*` — from Task 1

**Interfaces produced:**
- `POST /api/v1/atendimentos` — body: `{nome, email, telefone?, documento, assunto_id}`
- `GET /api/v1/atendimentos/{id}`
- `POST /api/v1/atendimentos/{id}/finalizar`
- `POST /api/v1/atendimentos/{id}/transferir` — body: `{atendente_id}`
- `GET /api/v1/dashboard`

- [ ] **Step 1: Register API routes in `bootstrap/app.php`**

```php
// bootstrap/app.php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

- [ ] **Step 2: Create `routes/api.php`**

```php
// routes/api.php
<?php

use App\Http\Controllers\Api\AtendimentoController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/atendimentos', [AtendimentoController::class, 'store']);
    Route::get('/atendimentos/{atendimento}', [AtendimentoController::class, 'show']);
    Route::post('/atendimentos/{atendimento}/finalizar', [AtendimentoController::class, 'finalizar']);
    Route::post('/atendimentos/{atendimento}/transferir', [AtendimentoController::class, 'transferir']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

- [ ] **Step 3: Create `AtendimentoController`**

```php
// app/Http/Controllers/Api/AtendimentoController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessarFilaAtendimentoJob;
use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\AtendimentoEvento;
use App\Models\Cliente;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtendimentoController extends Controller
{
    public function __construct(
        private readonly AtendimentoDistribuicaoService $distribuicao
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome'       => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'telefone'   => 'nullable|string|max:20',
            'documento'  => 'required|string|max:20',
            'assunto_id' => 'required|integer|exists:assuntos,id',
        ]);

        $assunto = Assunto::findOrFail($validated['assunto_id']);

        $cliente = Cliente::firstOrCreate(
            ['documento' => $validated['documento']],
            [
                'nome'     => $validated['nome'],
                'email'    => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
            ]
        );

        $atendimento = Atendimento::create([
            'cliente_id'  => $cliente->id,
            'assunto_id'  => $assunto->id,
            'time_atendimento_id' => $assunto->time_atendimento_id,
            'status'      => Atendimento::STATUS_AGUARDANDO,
        ]);

        $this->distribuicao->distribuir($atendimento);

        return response()->json(
            $atendimento->fresh()->load(['cliente', 'assunto', 'atribuicaoAtiva.atendente']),
            201
        );
    }

    public function show(Atendimento $atendimento): JsonResponse
    {
        return response()->json(
            $atendimento->load(['cliente', 'assunto', 'time', 'atribuicaoAtiva.atendente', 'eventos'])
        );
    }

    public function finalizar(Atendimento $atendimento): JsonResponse
    {
        if ($atendimento->status !== Atendimento::STATUS_EM_ATENDIMENTO) {
            return response()->json(
                ['error' => 'Atendimento não está em andamento.'],
                422
            );
        }

        $atribuicao = $atendimento->atribuicaoAtiva;
        if (!$atribuicao) {
            return response()->json(['error' => 'Nenhuma atribuição ativa encontrada.'], 422);
        }

        $atribuicao->update([
            'status'        => AtendimentoAtribuicao::STATUS_FINALIZADO,
            'finalizado_em' => now(),
        ]);

        $atendimento->update([
            'status'        => Atendimento::STATUS_FINALIZADO,
            'finalizado_em' => now(),
        ]);

        AtendimentoEvento::create([
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'finalizado',
            'descricao'      => 'Atendimento finalizado pelo atendente.',
            'dados'          => ['atendente_id' => $atribuicao->atendente_id],
        ]);

        ProcessarFilaAtendimentoJob::dispatch($atendimento->time_atendimento_id);

        return response()->json($atendimento->fresh());
    }

    public function transferir(Request $request, Atendimento $atendimento): JsonResponse
    {
        if ($atendimento->status !== Atendimento::STATUS_EM_ATENDIMENTO) {
            return response()->json(['error' => 'Atendimento não está em andamento.'], 422);
        }

        $validated = $request->validate([
            'atendente_id' => 'required|integer|exists:atendentes,id',
        ]);

        $novoAtendente = Atendente::findOrFail($validated['atendente_id']);

        if (!$novoAtendente->estaDisponivel()) {
            return response()->json(
                ['error' => "Atendente {$novoAtendente->nome} não está disponível ou atingiu o limite de atendimentos."],
                422
            );
        }

        $atribuicaoAtual = $atendimento->atribuicaoAtiva;
        if (!$atribuicaoAtual) {
            return response()->json(['error' => 'Nenhuma atribuição ativa encontrada.'], 422);
        }

        $atribuicaoAtual->update([
            'status'        => AtendimentoAtribuicao::STATUS_TRANSFERIDO,
            'finalizado_em' => now(),
        ]);

        AtendimentoAtribuicao::create([
            'atendimento_id' => $atendimento->id,
            'atendente_id'   => $novoAtendente->id,
            'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
        ]);

        AtendimentoEvento::create([
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'transferido',
            'descricao'      => "Atendimento transferido para {$novoAtendente->nome}.",
            'dados'          => [
                'atendente_anterior_id' => $atribuicaoAtual->atendente_id,
                'atendente_novo_id'     => $novoAtendente->id,
            ],
        ]);

        return response()->json(
            $atendimento->fresh()->load('atribuicaoAtiva.atendente')
        );
    }
}
```

- [ ] **Step 4: Create `DashboardController`**

```php
// app/Http/Controllers/Api/DashboardController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $hoje = Carbon::today();

        return response()->json([
            'total_criados_hoje'              => Atendimento::whereDate('criado_em', $hoje)->count(),
            'em_andamento'                    => Atendimento::where('status', Atendimento::STATUS_EM_ATENDIMENTO)->count(),
            'aguardando'                      => Atendimento::where('status', Atendimento::STATUS_AGUARDANDO)->count(),
            'finalizados_hoje'                => Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
                                                             ->whereDate('finalizado_em', $hoje)
                                                             ->count(),
            'tempo_medio_espera_minutos'      => $this->tempoMedioEspera(),
            'tempo_medio_atendimento_minutos' => $this->tempoMedioAtendimento(),
            'atendentes_online'               => $this->atendentesOnline(),
            'volume_por_time'                 => $this->volumePorTime(),
            'volume_por_assunto'              => $this->volumePorAssunto(),
        ]);
    }

    private function tempoMedioEspera(): float|null
    {
        $result = Atendimento::whereNotNull('entrou_na_fila_em')
            ->whereNotNull('iniciado_em')
            ->selectRaw('AVG(CAST((julianday(iniciado_em) - julianday(entrou_na_fila_em)) * 1440 AS REAL)) as media')
            ->value('media');

        return $result ? round((float) $result, 2) : null;
    }

    private function tempoMedioAtendimento(): float|null
    {
        $result = Atendimento::where('status', Atendimento::STATUS_FINALIZADO)
            ->whereNotNull('iniciado_em')
            ->whereNotNull('finalizado_em')
            ->selectRaw('AVG(CAST((julianday(finalizado_em) - julianday(iniciado_em)) * 1440 AS REAL)) as media')
            ->value('media');

        return $result ? round((float) $result, 2) : null;
    }

    private function atendentesOnline(): array
    {
        return Atendente::where('status', Atendente::STATUS_ONLINE)
            ->withCount([
                'atribuicoes as ativas_count' => fn($q) => $q->where('status', AtendimentoAtribuicao::STATUS_ATIVO),
            ])
            ->get()
            ->map(fn($a) => [
                'id'                          => $a->id,
                'nome'                        => $a->nome,
                'time_atendimento_id'         => $a->time_atendimento_id,
                'ativas_count'                => $a->ativas_count,
                'max_atendimentos_simultaneos' => $a->max_atendimentos_simultaneos,
            ])
            ->toArray();
    }

    private function volumePorTime(): array
    {
        return Atendimento::join('times_atendimento', 'atendimentos.time_atendimento_id', '=', 'times_atendimento.id')
            ->selectRaw('times_atendimento.nome as time, COUNT(atendimentos.id) as total')
            ->groupBy('times_atendimento.nome')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    private function volumePorAssunto(): array
    {
        return Atendimento::join('assuntos', 'atendimentos.assunto_id', '=', 'assuntos.id')
            ->selectRaw('assuntos.nome as assunto, COUNT(atendimentos.id) as total')
            ->groupBy('assuntos.nome')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }
}
```

- [ ] **Step 5: Verify routes are registered**

```bash
php artisan route:list --path=api
```

Expected output shows 5 routes under `/api/v1/`.

- [ ] **Step 6: Commit**

```bash
git add bootstrap/app.php routes/api.php app/Http/Controllers/Api/
git commit -m "feat: add REST API routes and controllers for atendimento and dashboard"
```

---

## Task 5: Observer (event logging + time_atendimento_id)

**Files:**
- Create: `app/Observers/AtendimentoObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces consumed:**
- `Atendimento` model — from Task 1
- `AtendimentoEvento` model — from Task 1
- `Assunto` model — from Task 1

**Interfaces produced:**
- Observer auto-populates `time_atendimento_id` on `Atendimento::creating` if not already set
- Observer logs `status_alterado` events to `atendimento_eventos` on `Atendimento::updated`

Note: The service layer already logs domain-level events (`atribuido`, `enfileirado`, `finalizado`, `transferido`). The observer adds an additional generic `status_alterado` log whenever the status column changes — useful for dashboard and audit — without duplicating the service-level context.

- [ ] **Step 1: Create `AtendimentoObserver`**

```php
// app/Observers/AtendimentoObserver.php
<?php

namespace App\Observers;

use App\Models\Assunto;
use App\Models\Atendimento;
use App\Models\AtendimentoEvento;

class AtendimentoObserver
{
    public function creating(Atendimento $atendimento): void
    {
        if (!$atendimento->time_atendimento_id && $atendimento->assunto_id) {
            $atendimento->time_atendimento_id = Assunto::find($atendimento->assunto_id)?->time_atendimento_id;
        }
    }

    public function updated(Atendimento $atendimento): void
    {
        if ($atendimento->wasChanged('status')) {
            AtendimentoEvento::create([
                'atendimento_id' => $atendimento->id,
                'tipo'           => 'status_alterado',
                'descricao'      => "Status alterado: {$atendimento->getOriginal('status')} → {$atendimento->status}.",
                'dados'          => [
                    'status_anterior' => $atendimento->getOriginal('status'),
                    'status_novo'     => $atendimento->status,
                ],
            ]);
        }
    }
}
```

- [ ] **Step 2: Register observer in `AppServiceProvider`**

```php
// app/Providers/AppServiceProvider.php
<?php

namespace App\Providers;

use App\Models\Atendimento;
use App\Observers\AtendimentoObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Atendimento::observe(AtendimentoObserver::class);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Observers/ app/Providers/AppServiceProvider.php
git commit -m "feat: add AtendimentoObserver for event logging and time resolution"
```

---

## Task 6: Feature Tests

**Files:**
- Create: `tests/Feature/AtendimentoDistribuicaoTest.php`
- Create: `tests/Feature/AtendimentoFinalizacaoTest.php`
- Create: `tests/Feature/AtendimentoTransferenciaTest.php`
- Create: `tests/Feature/DashboardTest.php`

**Interfaces consumed:** all models, services, and routes from Tasks 1–5.

- [ ] **Step 1: Create `AtendimentoDistribuicaoTest`** (covers: direct distribution, enqueue, limit enforcement)

```php
// tests/Feature/AtendimentoDistribuicaoTest.php
<?php

namespace Tests\Feature;

use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\FilaAtendimento;
use App\Models\TimeAtendimento;
use App\Models\Cliente;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtendimentoDistribuicaoTest extends TestCase
{
    use RefreshDatabase;

    private TimeAtendimento $time;
    private Assunto $assunto;
    private Atendente $atendente;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->time     = TimeAtendimento::create(['nome' => 'Cartões', 'slug' => 'cartoes']);
        $this->assunto  = Assunto::create(['nome' => 'Bloqueio', 'time_atendimento_id' => $this->time->id]);
        $this->atendente = Atendente::create([
            'nome'                       => 'Ana',
            'email'                      => 'ana@test.com',
            'time_atendimento_id'        => $this->time->id,
            'status'                     => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 3,
        ]);
        $this->cliente = Cliente::create([
            'nome'      => 'João Silva',
            'email'     => 'joao@test.com',
            'documento' => '12345678901',
        ]);
    }

    private function criarAtendimento(): Atendimento
    {
        return Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
    }

    public function test_distribui_diretamente_quando_atendente_esta_disponivel(): void
    {
        $atendimento = $this->criarAtendimento();

        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);

        $atendimento->refresh();
        $this->assertEquals(Atendimento::STATUS_EM_ATENDIMENTO, $atendimento->status);
        $this->assertNotNull($atendimento->iniciado_em);
        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $atendimento->id,
            'atendente_id'   => $this->atendente->id,
            'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
        ]);
        $this->assertDatabaseHas('atendimento_eventos', [
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'atribuido',
        ]);
    }

    public function test_enfileira_quando_nao_ha_atendente_disponivel(): void
    {
        // Max out the attendant
        for ($i = 0; $i < 3; $i++) {
            $a = $this->criarAtendimento();
            app(AtendimentoDistribuicaoService::class)->distribuir($a);
        }

        $atendimentoNaFila = $this->criarAtendimento();
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimentoNaFila);

        $atendimentoNaFila->refresh();
        $this->assertEquals(Atendimento::STATUS_AGUARDANDO, $atendimentoNaFila->status);
        $this->assertNotNull($atendimentoNaFila->entrou_na_fila_em);
        $this->assertDatabaseHas('fila_atendimentos', [
            'atendimento_id'      => $atendimentoNaFila->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => FilaAtendimento::STATUS_AGUARDANDO,
        ]);
        $this->assertDatabaseHas('atendimento_eventos', [
            'atendimento_id' => $atendimentoNaFila->id,
            'tipo'           => 'enfileirado',
        ]);
    }

    public function test_bloqueia_atribuicao_quando_limite_ja_atingido(): void
    {
        // Max out the attendant
        for ($i = 0; $i < 3; $i++) {
            $a = $this->criarAtendimento();
            app(AtendimentoDistribuicaoService::class)->distribuir($a);
        }

        $atendimento = $this->criarAtendimento();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/limite/i');

        app(AtendimentoDistribuicaoService::class)->atribuirAtendente($atendimento, $this->atendente);
    }

    public function test_atendente_offline_nao_recebe_atendimento(): void
    {
        $this->atendente->update(['status' => Atendente::STATUS_OFFLINE]);

        $atendimento = $this->criarAtendimento();
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);

        $atendimento->refresh();
        $this->assertEquals(Atendimento::STATUS_AGUARDANDO, $atendimento->status);
        $this->assertDatabaseHas('fila_atendimentos', ['atendimento_id' => $atendimento->id]);
    }

    public function test_cria_via_api_e_distribui_diretamente(): void
    {
        $response = $this->postJson('/api/v1/atendimentos', [
            'nome'       => 'Maria Souza',
            'email'      => 'maria@test.com',
            'documento'  => '98765432100',
            'assunto_id' => $this->assunto->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', Atendimento::STATUS_EM_ATENDIMENTO);
    }
}
```

- [ ] **Step 2: Run test and verify it fails (before all tasks complete it may not, but confirm tests parse correctly)**

```bash
php artisan test tests/Feature/AtendimentoDistribuicaoTest.php --no-coverage
```

Expected: tests pass (all infrastructure is in place from Tasks 1–5).

- [ ] **Step 3: Create `AtendimentoFinalizacaoTest`** (covers: finalize + queue consumption)

```php
// tests/Feature/AtendimentoFinalizacaoTest.php
<?php

namespace Tests\Feature;

use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\FilaAtendimento;
use App\Models\TimeAtendimento;
use App\Models\Cliente;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtendimentoFinalizacaoTest extends TestCase
{
    use RefreshDatabase;

    private TimeAtendimento $time;
    private Assunto $assunto;
    private Atendente $atendente;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->time     = TimeAtendimento::create(['nome' => 'Cartões', 'slug' => 'cartoes']);
        $this->assunto  = Assunto::create(['nome' => 'Bloqueio', 'time_atendimento_id' => $this->time->id]);
        $this->atendente = Atendente::create([
            'nome'                       => 'Ana',
            'email'                      => 'ana@test.com',
            'time_atendimento_id'        => $this->time->id,
            'status'                     => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 1,
        ]);
        $this->cliente = Cliente::create([
            'nome'      => 'João Silva',
            'email'     => 'joao@test.com',
            'documento' => '12345678901',
        ]);
    }

    private function criarAtendimento(): Atendimento
    {
        $a = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
        app(AtendimentoDistribuicaoService::class)->distribuir($a);
        return $a->fresh();
    }

    public function test_finalizar_atendimento_via_api(): void
    {
        $atendimento = $this->criarAtendimento();
        $this->assertEquals(Atendimento::STATUS_EM_ATENDIMENTO, $atendimento->status);

        $response = $this->postJson("/api/v1/atendimentos/{$atendimento->id}/finalizar");

        $response->assertStatus(200)
                 ->assertJsonPath('status', Atendimento::STATUS_FINALIZADO);

        $atendimento->refresh();
        $this->assertNotNull($atendimento->finalizado_em);
        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $atendimento->id,
            'status'         => AtendimentoAtribuicao::STATUS_FINALIZADO,
        ]);
        $this->assertDatabaseHas('atendimento_eventos', [
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'finalizado',
        ]);
    }

    public function test_finalizar_consome_fila_e_atribui_proximo(): void
    {
        // Attendant has max=1: first atendimento is assigned, second goes to queue
        $primeiro  = $this->criarAtendimento();
        $this->assertEquals(Atendimento::STATUS_EM_ATENDIMENTO, $primeiro->status);

        $segundoRaw = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
        app(AtendimentoDistribuicaoService::class)->distribuir($segundoRaw);
        $segundo = $segundoRaw->fresh();
        $this->assertEquals(Atendimento::STATUS_AGUARDANDO, $segundo->status);

        // Finalize first → job runs synchronously (QUEUE_CONNECTION=sync) → second is assigned
        $this->postJson("/api/v1/atendimentos/{$primeiro->id}/finalizar")->assertStatus(200);

        $segundo->refresh();
        $this->assertEquals(Atendimento::STATUS_EM_ATENDIMENTO, $segundo->status);
        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $segundo->id,
            'atendente_id'   => $this->atendente->id,
            'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
        ]);
        $this->assertDatabaseHas('fila_atendimentos', [
            'atendimento_id' => $segundo->id,
            'status'         => FilaAtendimento::STATUS_PROCESSADO,
        ]);
    }

    public function test_retorna_422_ao_finalizar_atendimento_nao_ativo(): void
    {
        $atendimento = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);

        $this->postJson("/api/v1/atendimentos/{$atendimento->id}/finalizar")
             ->assertStatus(422)
             ->assertJsonPath('error', 'Atendimento não está em andamento.');
    }
}
```

- [ ] **Step 4: Create `AtendimentoTransferenciaTest`**

```php
// tests/Feature/AtendimentoTransferenciaTest.php
<?php

namespace Tests\Feature;

use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\TimeAtendimento;
use App\Models\Cliente;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtendimentoTransferenciaTest extends TestCase
{
    use RefreshDatabase;

    private TimeAtendimento $time;
    private Assunto $assunto;
    private Atendente $atendente1;
    private Atendente $atendente2;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->time = TimeAtendimento::create(['nome' => 'Cartões', 'slug' => 'cartoes']);
        $this->assunto = Assunto::create(['nome' => 'Bloqueio', 'time_atendimento_id' => $this->time->id]);

        $this->atendente1 = Atendente::create([
            'nome' => 'Ana', 'email' => 'ana@test.com',
            'time_atendimento_id' => $this->time->id,
            'status' => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 3,
        ]);
        $this->atendente2 = Atendente::create([
            'nome' => 'Bruno', 'email' => 'bruno@test.com',
            'time_atendimento_id' => $this->time->id,
            'status' => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 3,
        ]);
        $this->cliente = Cliente::create([
            'nome' => 'João', 'email' => 'joao@test.com', 'documento' => '12345678901',
        ]);
    }

    public function test_transfere_atendimento_para_outro_atendente(): void
    {
        $atendimento = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);
        $atendimento->refresh();
        $this->assertEquals($this->atendente1->id, $atendimento->atribuicaoAtiva->atendente_id);

        $response = $this->postJson("/api/v1/atendimentos/{$atendimento->id}/transferir", [
            'atendente_id' => $this->atendente2->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $atendimento->id,
            'atendente_id'   => $this->atendente1->id,
            'status'         => AtendimentoAtribuicao::STATUS_TRANSFERIDO,
        ]);
        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $atendimento->id,
            'atendente_id'   => $this->atendente2->id,
            'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
        ]);
        $this->assertDatabaseHas('atendimento_eventos', [
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'transferido',
        ]);
    }

    public function test_rejeita_transferencia_para_atendente_no_limite(): void
    {
        // Max out atendente2
        for ($i = 0; $i < 3; $i++) {
            $extra = Atendimento::create([
                'cliente_id'          => $this->cliente->id,
                'assunto_id'          => $this->assunto->id,
                'time_atendimento_id' => $this->time->id,
                'status'              => Atendimento::STATUS_AGUARDANDO,
            ]);
            AtendimentoAtribuicao::create([
                'atendimento_id' => $extra->id,
                'atendente_id'   => $this->atendente2->id,
                'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
            ]);
            $extra->update(['status' => Atendimento::STATUS_EM_ATENDIMENTO, 'iniciado_em' => now()]);
        }

        $atendimento = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);

        $this->postJson("/api/v1/atendimentos/{$atendimento->id}/transferir", [
            'atendente_id' => $this->atendente2->id,
        ])->assertStatus(422);
    }

    public function test_rejeita_transferencia_de_atendimento_nao_ativo(): void
    {
        $atendimento = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);

        $this->postJson("/api/v1/atendimentos/{$atendimento->id}/transferir", [
            'atendente_id' => $this->atendente2->id,
        ])->assertStatus(422);
    }
}
```

- [ ] **Step 5: Create `DashboardTest`**

```php
// tests/Feature/DashboardTest.php
<?php

namespace Tests\Feature;

use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\TimeAtendimento;
use App\Models\Cliente;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_retorna_indicadores_corretos(): void
    {
        $time     = TimeAtendimento::create(['nome' => 'Cartões', 'slug' => 'cartoes']);
        $assunto  = Assunto::create(['nome' => 'Bloqueio', 'time_atendimento_id' => $time->id]);
        $atendente = Atendente::create([
            'nome' => 'Ana', 'email' => 'ana@test.com',
            'time_atendimento_id' => $time->id,
            'status' => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 3,
        ]);
        $cliente = Cliente::create([
            'nome' => 'João', 'email' => 'joao@test.com', 'documento' => '11111111111',
        ]);

        $atendimento = Atendimento::create([
            'cliente_id'          => $cliente->id,
            'assunto_id'          => $assunto->id,
            'time_atendimento_id' => $time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'total_criados_hoje',
                     'em_andamento',
                     'aguardando',
                     'finalizados_hoje',
                     'tempo_medio_espera_minutos',
                     'tempo_medio_atendimento_minutos',
                     'atendentes_online',
                     'volume_por_time',
                     'volume_por_assunto',
                 ])
                 ->assertJsonPath('total_criados_hoje', 1)
                 ->assertJsonPath('em_andamento', 1)
                 ->assertJsonPath('aguardando', 0);

        $onlineList = $response->json('atendentes_online');
        $this->assertCount(1, $onlineList);
        $this->assertEquals($atendente->id, $onlineList[0]['id']);
        $this->assertEquals(1, $onlineList[0]['ativas_count']);
    }
}
```

- [ ] **Step 6: Run the full test suite**

```bash
php artisan test --no-coverage
```

Expected: All tests pass. If any fail, debug and fix before committing.

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/
git commit -m "test: add feature tests for distribuição, finalização, transferência and dashboard"
```

---

## Self-Review

### Spec Coverage Check

| Requirement | Task |
|---|---|
| Migration: `clientes` | Task 1 Step 1 |
| Migration: `times_atendimento` | Task 1 Step 2 |
| Migration: `assuntos` | Task 1 Step 3 |
| Migration: `atendentes` | Task 1 Step 4 |
| Migration: `atendimentos` | Task 1 Step 5 |
| Migration: `fila_atendimentos` | Task 1 Step 6 |
| Migration: `atendimento_atribuicoes` | Task 1 Step 7 |
| Migration: `atendimento_eventos` | Task 1 Step 8 |
| Seeders: times, assuntos, atendentes | Task 2 |
| Endpoint: criar atendimento + distribuição | Task 4 Step 3 (`store`) |
| Endpoint: finalizar + consumo da fila | Task 4 Step 3 (`finalizar`) |
| Endpoint: transferência | Task 4 Step 3 (`transferir`) |
| Validação: limite 3 simultâneos no backend | Task 3 Step 1 (`atribuirAtendente` with re-check in transaction) |
| Registro em `atendimento_eventos` | Tasks 3, 4 (service layer) + Task 5 (observer `status_alterado`) |
| Dashboard: total criados hoje | Task 4 Step 4 |
| Dashboard: em andamento, aguardando, finalizados | Task 4 Step 4 |
| Dashboard: atendentes online + carga | Task 4 Step 4 |
| Dashboard: tempo médio espera e atendimento | Task 4 Step 4 |
| Dashboard: volume por time e assunto | Task 4 Step 4 |
| Teste: distribuição direta | Task 6 Step 1 |
| Teste: enfileiramento | Task 6 Step 1 |
| Teste: consumo da fila ao finalizar | Task 6 Step 3 |
| Teste: bloqueio por limite atingido | Task 6 Step 1 |
| Teste: transferência | Task 6 Step 4 |

All acceptance criteria covered. ✓

### Design Deviations from Spec

- `fila_atendimentos` has no `posicao` column — queue position is calculated from `entrou_em ASC` per the task's own notes.
- `atendimento_eventos.dados` is `json` nullable, supporting optional context per event type.
- `tempoMedioEspera` and `tempoMedioAtendimento` use SQLite `julianday()` — if moving to MySQL/PostgreSQL, replace with `TIMESTAMPDIFF(MINUTE, ...)`.
