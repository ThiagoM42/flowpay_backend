<?php

namespace App\Filament\Resources\Atendimentos\Pages;

use App\Filament\Resources\Atendimentos\AtendimentoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAtendimento extends ViewRecord
{
    protected static string $resource = AtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
