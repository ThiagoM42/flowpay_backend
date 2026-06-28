<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = ['nome', 'email', 'telefone'];

    public function atendimentos(): HasMany
    {
        return $this->hasMany(Atendimento::class);
    }
}
