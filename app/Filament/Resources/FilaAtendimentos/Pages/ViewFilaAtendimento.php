<?php

namespace App\Filament\Resources\FilaAtendimentos\Pages;

use App\Filament\Resources\FilaAtendimentos\FilaAtendimentoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFilaAtendimento extends ViewRecord
{
    protected static string $resource = FilaAtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
