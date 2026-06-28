<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;
use App\Policies\Concerns\InteractsWithFilamentAccess;

class ClientePolicy
{
    use InteractsWithFilamentAccess;

    public function viewAny(User $user): bool
    {
        return $this->canManageCatalog($user);
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $this->canManageCatalog($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageCatalog($user);
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $this->canManageCatalog($user);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $this->isAdmin($user);
    }
}