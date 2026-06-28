<?php

namespace App\Policies;

use App\Models\AtendimentoEvento;
use App\Models\User;
use App\Policies\Concerns\InteractsWithFilamentAccess;

class AtendimentoEventoPolicy
{
    use InteractsWithFilamentAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user) || $this->isAtendente($user);
    }

    public function view(User $user, AtendimentoEvento $atendimentoEvento): bool
    {
        $atendimento = $atendimentoEvento->atendimento;

        return $atendimento ? $this->canViewAttendance($user, $atendimento) : $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AtendimentoEvento $atendimentoEvento): bool
    {
        return false;
    }

    public function delete(User $user, AtendimentoEvento $atendimentoEvento): bool
    {
        return $this->isAdmin($user);
    }
}