<?php

namespace App\Policies;

use App\Models\Atendimento;
use App\Models\User;
use App\Policies\Concerns\InteractsWithFilamentAccess;

class AtendimentoPolicy
{
    use InteractsWithFilamentAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user) || $this->isAtendente($user);
    }

    public function view(User $user, Atendimento $atendimento): bool
    {
        return $this->canViewAttendance($user, $atendimento);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user);
    }

    public function update(User $user, Atendimento $atendimento): bool
    {
        return $this->canViewAttendance($user, $atendimento) && $atendimento->status !== Atendimento::STATUS_FINALIZADO;
    }

    public function delete(User $user, Atendimento $atendimento): bool
    {
        return $this->isAdmin($user);
    }
}