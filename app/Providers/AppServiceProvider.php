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
