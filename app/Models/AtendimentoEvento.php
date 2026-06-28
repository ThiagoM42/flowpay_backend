<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtendimentoEvento extends Model
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = null;

    protected $fillable = ['atendimento_id', 'tipo', 'descricao', 'dados'];
    protected $casts = ['dados' => 'array', 'criado_em' => 'datetime'];

    public function atendimento(): BelongsTo { return $this->belongsTo(Atendimento::class); }
}
