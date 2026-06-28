<?php

namespace App\Filament\Resources\AtendimentoEventos\Pages;

use App\Filament\Resources\AtendimentoEventos\AtendimentoEventoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAtendimentoEvento extends ViewRecord
{
    protected static string $resource = AtendimentoEventoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
