<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtendimentoAtribuicao extends Model
{
    protected $table = 'atendimento_atribuicoes';

    const CREATED_AT = 'criado_em';
    const UPDATED_AT = null;

    const STATUS_ATIVO       = 'ativo';
    const STATUS_FINALIZADO  = 'finalizado';
    const STATUS_TRANSFERIDO = 'transferido';

    protected $fillable = ['atendimento_id', 'atendente_id', 'status', 'finalizado_em'];
    protected $casts = ['criado_em' => 'datetime', 'finalizado_em' => 'datetime'];

    public function atendimento(): BelongsTo { return $this->belongsTo(Atendimento::class); }
    public function atendente(): BelongsTo { return $this->belongsTo(Atendente::class); }
}
