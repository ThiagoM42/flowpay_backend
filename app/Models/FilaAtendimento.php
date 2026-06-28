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

    public function atendimento(): BelongsTo { return $this->belongsTo(Atendimento::class); }
    public function time(): BelongsTo { return $this->belongsTo(TimeAtendimento::class, 'time_atendimento_id'); }
}
