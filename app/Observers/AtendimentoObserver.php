<?php

namespace App\Observers;

use App\Models\Assunto;
use App\Models\Atendimento;

class AtendimentoObserver
{
    public function creating(Atendimento $atendimento): void
    {
        if (!$atendimento->time_atendimento_id && $atendimento->assunto_id) {
            $atendimento->time_atendimento_id = Assunto::find($atendimento->assunto_id)?->time_atendimento_id;
        }
    }
}
