<?php

namespace App\Policies;

use App\Models\AtendimentoAtribuicao;
use App\Models\User;
use App\Policies\Concerns\InteractsWithFilamentAccess;

class AtendimentoAtribuicaoPolicy
{
    use InteractsWithFilamentAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isCoordenador($user) || $this->isAtendente($user);
    }

    public function view(User $user, AtendimentoAtribuicao $atendimentoAtribuicao): bool
    {
        $atendimento = $atendimentoAtribuicao->atendimento;

        return $atendimento ? $this->canViewAttendance($user, $atendimento) : $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AtendimentoAtribuicao $atendimentoAtribuicao): bool
    {
        return false;
    }

    public function delete(User $user, AtendimentoAtribuicao $atendimentoAtribuicao): bool
    {
        return $this->isAdmin($user);
    }
}