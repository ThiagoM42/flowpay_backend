# Filament Admin Panel — Sistema de Atendimento FlowPay

## Objetivo

Criar a camada de administração do módulo de atendimento usando Filament v5: 8 Resources para CRUD/visualização, 8 Widgets para dashboard operacional em tempo real, Policies por papel e caching Redis.

## Contexto

Migrations, models e services do módulo de atendimento já existem e estão na `main` (10 commits, task00 completa). O `AdminPanelProvider` já existe em `app/Providers/Filament/AdminPanelProvider.php` com auto-discovery configurado. Não há diretório `app/Filament/` ainda.

---

## 1. Role System (pré-requisito)

### Mudanças no banco

**Migration 1 — adicionar colunas em `users`:**
```
role                  ENUM('admin','coordenador','atendente')  DEFAULT 'admin' NOT NULL
time_atendimento_id   BIGINT UNSIGNED NULLABLE FK → times_atendimento.id
```

- `admin`: acesso total ao painel
- `coordenador`: vê tudo do seu `time_atendimento_id`
- `atendente`: vê só seus próprios atendimentos (vínculo via `atendentes.user_id`)

**Migration 2 — adicionar coluna em `atendentes`:**
```
user_id   BIGINT UNSIGNED NULLABLE FK → users.id
```

**Migration 3 — atualizar admin user existente:**
```sql
UPDATE users SET role = 'admin' WHERE email = 'admin@hotmail.com';
```

### User model

Constantes: `ROLE_ADMIN = 'admin'`, `ROLE_COORDENADOR = 'coordenador'`, `ROLE_ATENDENTE = 'atendente'`

Helpers: `isAdmin(): bool`, `isCoordenador(): bool`, `isAtendente(): bool`

Relacionamentos: `belongsTo(TimeAtendimento::class)`, `hasOne(Atendente::class)`

---

## 2. Filament Resources (8 resources)

Gerados com `php artisan make:filament-resource <Nome> --generate`, depois customizados.

### Navegação em 3 grupos

| Grupo | Resource | Modo |
|---|---|---|
| **Operação** | `AtendimentoResource` | CRUD + 4 actions custom |
| **Operação** | `FilaAtendimentoResource` | Read-only (sem Create/Edit/Delete) |
| **Operação** | `AtendimentoAtribuicaoResource` | Read-only |
| **Cadastros** | `ClienteResource` | CRUD |
| **Cadastros** | `TimeAtendimentoResource` | CRUD |
| **Cadastros** | `AssuntoResource` | CRUD |
| **Cadastros** | `AtendenteResource` | CRUD + ToggleAction status |
| **Auditoria** | `AtendimentoEventoResource` | Read-only |

Cada resource declara:
```php
protected static ?string $navigationGroup = 'Operação'; // ou 'Cadastros' / 'Auditoria'
protected static ?string $navigationIcon = 'heroicon-o-...';
```

### AtendimentoResource — detalhes

**Form:**
- `Select::make('cliente_id')` com `->searchable()->preload()`
- `Select::make('assunto_id')->live()->afterStateUpdated(fn($state, Set $set) => $set('time_atendimento_id', Assunto::find($state)?->time_atendimento_id))`
- `Hidden::make('time_atendimento_id')`
- `Textarea::make('descricao')`
- `Select::make('prioridade')` (baixa/media/alta)

**Table:**
- Colunas: id, cliente.nome, assunto.nome, time.nome, atendente atual (via `getStateUsing`), status (badge colorida), criado_em
- Badge status: `aguardando=warning`, `em_atendimento=success`, `finalizado=gray`, `cancelado=danger`
- Filtros: `SelectFilter` por status, time, assunto + `DateRangeFilter` por período

**4 Actions na linha (via `->actions()`):**
- `Atribuir`: modal com `Select` de atendente disponível → chama `AtendimentoDistribuicaoService::atribuirAtendente($atendimento, $atendente)`
- `Transferir`: modal com `Select` de atendente → chama `AtendimentoController::transferir()` ou replica a lógica: finaliza atribuição atual + cria nova + cria evento (dentro de `DB::transaction`)
- `Finalizar`: `Action::make()->requiresConfirmation()` → replica lógica do `AtendimentoController::finalizar()`: finaliza atribuição + atualiza status + dispara `ProcessarFilaAtendimentoJob`
- `Cancelar`: `Action::make()->requiresConfirmation()->color('danger')` → `$atendimento->update(['status' => 'cancelado'])` (sem processar fila)

**RelationManagers:**
- `AtribuicoesRelationManager`: tabela de `AtendimentoAtribuicao` (read-only)
- `EventosRelationManager`: tabela de `AtendimentoEvento` (read-only), ordenado por `criado_em ASC`

**Infolist:** `RepeatableEntry::make('eventos')` para timeline visual.

### AtendenteResource — detalhes

**Table:** coluna `ativas_count/max_atendimentos_simultaneos` via `withCount` + `ToggleAction` para alternar status `online` ↔ `pausado`.

**Badge status:** `online=success`, `offline=gray`, `pausado=warning`

### FilaAtendimentoResource — detalhes

Table com `->poll('5s')`, ordenada por `entrou_em ASC`, coluna de tempo de espera calculada em PHP: `now()->diffInMinutes($record->entrou_em) . ' min'`.

---

## 3. Widgets do Dashboard (8 widgets)

Gerados com `php artisan make:filament-widget <Nome> --stats-overview` ou `--chart` ou `--table`.

### Stats Overview (linha 1 — 3 colunas)

**`AtendimentosOverviewWidget`** — 4 cards com sparkline 7 dias:
- Criados hoje / Em andamento / Aguardando na fila / Finalizados hoje
- Cache Redis TTL: 30 s

**`AtendentesOverviewWidget`** — 3 cards:
- Online agora / Pausados / Taxa de ocupação (%)
- Cache Redis TTL: 15 s

**`TemposMediosWidget`** — 3 cards:
- Tempo médio espera (min) / Tempo médio atendimento (min) / Tempo médio total (min)
- Usa SQL cross-db condicional (mesmo padrão do `DashboardController`)
- Cache Redis TTL: 60 s

### Charts (linha 2 — 3 colunas)

**`AtendimentosPorTimeChart`** — tipo `doughnut`, filtro hoje/semana/mês, TTL 60 s

**`AtendimentosPorAssuntoChart`** — tipo `bar` horizontal, top 10, filtro período, TTL 60 s

**`AtendimentosUltimos7DiasChart`** — tipo `line`, 2 datasets (criados vs finalizados por dia), TTL 60 s

### Tables (linha 3 — largura total)

**`CargaAtendentesTableWidget`** — polling 10 s, ordenado por carga desc, TTL 15 s

**`FilaAtualTableWidget`** — polling 5 s, highlight `danger` para espera > 10 min, TTL 5 s

**Padrão de cache (todos os widgets pesados):**
```php
return Cache::remember(
    "widget.{$this->getId()}",
    now()->addSeconds($this->cacheTtl),
    fn() => $this->compute()
);
```

---

## 4. Policies

Uma Policy por Resource, registradas em `AppServiceProvider::boot()` via `Gate::policy(Model::class, Policy::class)` explícito. O Filament v5 chama automaticamente o Gate nos métodos `can*` de cada Resource (ex: `canViewAny()`, `canCreate()`, `canEdit()`).

**Matriz:**

| Policy | admin | coordenador | atendente |
|---|---|---|---|
| ClientePolicy | CRUD | view | — |
| TimeAtendimentoPolicy | CRUD | view | — |
| AssuntoPolicy | CRUD | view | — |
| AtendentePolicy | CRUD | view próprio time | view próprio |
| AtendimentoPolicy | CRUD + actions | CRUD próprio time | view próprios |
| FilaAtendimentoPolicy | view | view próprio time | — |
| AtribuicaoPolicy | view | view próprio time | view próprios |
| EventoPolicy | view | view próprio time | view próprios |

**Lógica de filtro por papel no `viewAny` + `view`:**
```php
if ($user->isAdmin()) return true;
if ($user->isCoordenador()) return $record->time_atendimento_id === $user->time_atendimento_id;
if ($user->isAtendente()) return $record->atribuicaoAtiva?->atendente_id === $user->atendente?->id;
return false;
```

Nenhuma configuração adicional no panel provider é necessária — basta ter as Policies registradas no `AppServiceProvider` via `Gate::policy()`.

---

## 5. Panel Configuration

**`AdminPanelProvider` — ajustes:**
```php
->navigationGroups([
    NavigationGroup::make('Operação')->icon('heroicon-o-bolt'),
    NavigationGroup::make('Cadastros')->icon('heroicon-o-cog-6-tooth'),
    NavigationGroup::make('Auditoria')->icon('heroicon-o-document-magnifying-glass'),
])
->authorizationMiddleware('auth')  // já existe via ->login()
->widgets([
    AtendimentosOverviewWidget::class,
    AtendentesOverviewWidget::class,
    TemposMediosWidget::class,
    AtendimentosPorTimeChart::class,
    AtendimentosPorAssuntoChart::class,
    AtendimentosUltimos7DiasChart::class,
    CargaAtendentesTableWidget::class,
    FilaAtualTableWidget::class,
])
```

**Ordem visual:** Stats (3 col, linha 1) → Charts (3 col, linha 2) → Tables (full width, linha 3). Controlada pela ordem do array + `->columnSpan()` em cada widget.

---

## 6. Caching Redis

**Pré-requisito:** `.env` deve ter `CACHE_STORE=redis` e `REDIS_HOST=laravel_redis_flowpay` (ou host do container). O plano verificará isso.

**TTL por widget:**

| Widget | TTL |
|---|---|
| FilaAtualTableWidget | 5 s |
| AtendentesOverviewWidget | 15 s |
| CargaAtendentesTableWidget | 15 s |
| AtendimentosOverviewWidget | 30 s |
| Charts (3) | 60 s |
| TemposMediosWidget | 60 s |

**Invalidação:** TTL curto sem invalidação manual nesta iteração.

---

## Critérios de Aceitação

- [ ] Role system: 3 migrations novas, User model atualizado
- [ ] 8 Resources navegáveis no painel `/admin`
- [ ] Filtros funcionais em AtendimentoResource, AtendenteResource, FilaAtendimentoResource
- [ ] 4 actions do AtendimentoResource chamando os Services existentes sem erro
- [ ] Badges de status com cores consistentes em toda a aplicação
- [ ] 3 Stats Overview no topo do dashboard
- [ ] 3 Charts com filtro de período
- [ ] 2 Table widgets com polling configurado
- [ ] Policies aplicadas: admin/coordenador/atendente com acesso correto
- [ ] Cache Redis funcionando nos widgets (verificável via `php artisan cache:clear` + observar queries)
- [ ] Navegação agrupada: Operação / Cadastros / Auditoria

## Fora de Escopo

- Invalidação de cache baseada em eventos (Redis pub/sub)
- Notificações em tempo real (WebSockets/Reverb)
- Testes automatizados de Filament (Livewire testing) — validação manual no browser
- Multi-tenant ou múltiplos panels
