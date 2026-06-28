<?php

namespace App\Filament\Resources\TimeAtendimentos\Pages;

use App\Filament\Resources\TimeAtendimentos\TimeAtendimentoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTimeAtendimento extends ViewRecord
{
    protected static string $resource = TimeAtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
