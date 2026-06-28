<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Atendimento extends Model
{
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'updated_at';

    const STATUS_AGUARDANDO     = 'aguardando';
    const STATUS_EM_ATENDIMENTO = 'em_atendimento';
    const STATUS_FINALIZADO     = 'finalizado';
    const STATUS_CANCELADO      = 'cancelado';

    protected $fillable = [
        'cliente_id', 'assunto_id', 'time_atendimento_id',
        'descricao', 'prioridade', 'status', 'entrou_na_fila_em', 'iniciado_em', 'finalizado_em',
    ];
    protected $casts = [
        'criado_em'         => 'datetime',
        'entrou_na_fila_em' => 'datetime',
        'iniciado_em'       => 'datetime',
        'finalizado_em'     => 'datetime',
    ];

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function assunto(): BelongsTo { return $this->belongsTo(Assunto::class); }
    public function time(): BelongsTo { return $this->belongsTo(TimeAtendimento::class, 'time_atendimento_id'); }
    public function atribuicoes(): HasMany { return $this->hasMany(AtendimentoAtribuicao::class); }
    public function atribuicaoAtiva(): HasOne
    {
        return $this->hasOne(AtendimentoAtribuicao::class)->where('status', AtendimentoAtribuicao::STATUS_ATIVO);
    }
    public function eventos(): HasMany { return $this->hasMany(AtendimentoEvento::class); }
    public function filaAtendimento(): HasOne { return $this->hasOne(FilaAtendimento::class); }
}
