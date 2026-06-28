<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeAtendimento extends Model
{
    protected $table = 'times_atendimento';

    protected $fillable = ['nome', 'slug'];

    public function assuntos(): HasMany { return $this->hasMany(Assunto::class); }
    public function atendentes(): HasMany { return $this->hasMany(Atendente::class); }
    public function filaAtendimentos(): HasMany { return $this->hasMany(FilaAtendimento::class); }
}
