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
    public function atendimentos(): HasMany { return $this->hasMany(Atendimento::class); }
}
