<?php

namespace App\Policies;

use App\Models\Assunto;
use App\Models\User;
use App\Policies\Concerns\InteractsWithFilamentAccess;

class AssuntoPolicy
{
    use InteractsWithFilamentAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user);
    }

    public function view(User $user, Assunto $assunto): bool
    {
        return $this->canManageCatalog($user, $assunto->time_atendimento_id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user);
    }

    public function update(User $user, Assunto $assunto): bool
    {
        return $this->canManageCatalog($user, $assunto->time_atendimento_id);
    }

    public function delete(User $user, Assunto $assunto): bool
    {
        return $this->isAdmin($user);
    }
}