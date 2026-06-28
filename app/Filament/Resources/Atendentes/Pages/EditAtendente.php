<?php

namespace App\Filament\Resources\Atendentes\Pages;

use App\Filament\Resources\Atendentes\AtendenteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAtendente extends EditRecord
{
    protected static string $resource = AtendenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
