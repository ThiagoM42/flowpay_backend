<?php

namespace App\Policies;

use App\Models\TimeAtendimento;
use App\Models\User;
use App\Policies\Concerns\InteractsWithFilamentAccess;

class TimeAtendimentoPolicy
{
    use InteractsWithFilamentAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user);
    }

    public function view(User $user, TimeAtendimento $timeAtendimento): bool
    {
        return $this->canManageCatalog($user, $timeAtendimento->id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, TimeAtendimento $timeAtendimento): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, TimeAtendimento $timeAtendimento): bool
    {
        return $this->isAdmin($user);
    }
}