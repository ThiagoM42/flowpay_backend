<?php

namespace App\Policies;

use App\Models\FilaAtendimento;
use App\Models\User;
use App\Policies\Concerns\InteractsWithFilamentAccess;

class FilaAtendimentoPolicy
{
    use InteractsWithFilamentAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user) || $this->isAtendente($user);
    }

    public function view(User $user, FilaAtendimento $filaAtendimento): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isCoordenador($user)) {
            return $this->isOwnTeam($user, $filaAtendimento->time_atendimento_id);
        }

        return $this->isAtendente($user) && $this->isOwnTeam($user, $filaAtendimento->time_atendimento_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, FilaAtendimento $filaAtendimento): bool
    {
        return false;
    }

    public function delete(User $user, FilaAtendimento $filaAtendimento): bool
    {
        return $this->isAdmin($user);
    }
}