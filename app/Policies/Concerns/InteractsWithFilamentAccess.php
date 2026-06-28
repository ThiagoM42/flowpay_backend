<?php

namespace App\Policies\Concerns;

use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithFilamentAccess
{
    protected function isAdmin(User $user): bool
    {
        return $user->isAdmin();
    }

    protected function isCoordenador(User $user): bool
    {
        return $user->isCoordenador();
    }

    protected function isAtendente(User $user): bool
    {
        return $user->isAtendente();
    }

    protected function userAtendente(User $user): ?Atendente
    {
        return $user->atendente;
    }

    protected function userTimeId(User $user): ?int
    {
        return $user->time_atendimento_id
            ?? $user->atendente?->time_atendimento_id;
    }

    protected function isOwnTeam(User $user, int $timeId): bool
    {
        return $this->userTimeId($user) === $timeId;
    }

    protected function isOwnAtendimento(User $user, Atendimento $atendimento): bool
    {
        $atendente = $this->userAtendente($user);

        if (! $atendente) {
            return false;
        }

        return $atendimento->atribuicoes()
            ->where('atendente_id', $atendente->id)
            ->exists();
    }

    protected function canManageCatalog(User $user, ?int $timeId = null): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isCoordenador($user) && ($timeId === null || $this->isOwnTeam($user, $timeId));
    }

    protected function canViewAttendance(User $user, Atendimento $atendimento): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isCoordenador($user)) {
            return $this->isOwnTeam($user, $atendimento->time_atendimento_id);
        }

        return $this->isAtendente($user) && $this->isOwnAtendimento($user, $atendimento);
    }

    protected function canViewTeamResource(User $user, Model $record): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $this->isCoordenador($user)) {
            return false;
        }

        if (property_exists($record, 'time_atendimento_id')) {
            return $this->isOwnTeam($user, (int) $record->time_atendimento_id);
        }

        return false;
    }
}