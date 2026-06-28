<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'time_atendimento_id',
        'atendente_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function timeAtendimento(): BelongsTo
    {
        return $this->belongsTo(TimeAtendimento::class, 'time_atendimento_id');
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(Atendente::class, 'atendente_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCoordenador(): bool
    {
        return $this->role === 'coordenador';
    }

    public function isAtendente(): bool
    {
        return $this->role === 'atendente';
    }
}
