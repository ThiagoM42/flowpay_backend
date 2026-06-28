<?php

namespace App\Providers;

use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\AtendimentoEvento;
use App\Models\Cliente;
use App\Models\FilaAtendimento;
use App\Models\TimeAtendimento;
use App\Policies\AssuntoPolicy;
use App\Policies\AtendentePolicy;
use App\Policies\AtendimentoAtribuicaoPolicy;
use App\Policies\AtendimentoEventoPolicy;
use App\Policies\AtendimentoPolicy;
use App\Policies\ClientePolicy;
use App\Policies\FilaAtendimentoPolicy;
use App\Policies\TimeAtendimentoPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Cliente::class => ClientePolicy::class,
        TimeAtendimento::class => TimeAtendimentoPolicy::class,
        Assunto::class => AssuntoPolicy::class,
        Atendente::class => AtendentePolicy::class,
        Atendimento::class => AtendimentoPolicy::class,
        FilaAtendimento::class => FilaAtendimentoPolicy::class,
        AtendimentoAtribuicao::class => AtendimentoAtribuicaoPolicy::class,
        AtendimentoEvento::class => AtendimentoEventoPolicy::class,
    ];

    public function boot(): void
    {
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