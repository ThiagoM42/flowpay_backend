<?php

namespace App\Policies;

use App\Models\Atendente;
use App\Models\User;
use App\Policies\Concerns\InteractsWithFilamentAccess;

class AtendentePolicy
{
    use InteractsWithFilamentAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user);
    }

    public function view(User $user, Atendente $atendente): bool
    {
        return $this->canManageCatalog($user, $atendente->time_atendimento_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user);
    }

    public function update(User $user, Atendente $atendente): bool
    {
        return $this->canManageCatalog($user, $atendente->time_atendimento_id);
    }

    public function delete(User $user, Atendente $atendente): bool
    {
        return $this->isAdmin($user);
    }
}