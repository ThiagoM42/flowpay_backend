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

    protected $fillable = ['nome', 'email', 'time_atendimento_id', 'status', 'max_atendimentos_simultaneos', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function time(): BelongsTo
    {
        return $this->belongsTo(TimeAtendimento::class, 'time_atendimento_id');
    }
    public function atribuicoes(): HasMany { return $this->hasMany(AtendimentoAtribuicao::class); }
    public function atribuicoesAtivas(): HasMany
    {
        return $this->hasMany(AtendimentoAtribuicao::class)->where('status', AtendimentoAtribuicao::STATUS_ATIVO);
    }
    public function atendimentosAtivosCount(): int { return $this->atribuicoesAtivas()->count(); }
    public function estaDisponivel(): bool
    {
        return $this->status === self::STATUS_ONLINE
            && $this->atendimentosAtivosCount() < $this->max_atendimentos_simultaneos;
    }
}
