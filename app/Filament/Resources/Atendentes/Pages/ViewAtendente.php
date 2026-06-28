<?php

namespace App\Filament\Resources\Atendentes\Pages;

use App\Filament\Resources\Atendentes\AtendenteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAtendente extends ViewRecord
{
    protected static string $resource = AtendenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
