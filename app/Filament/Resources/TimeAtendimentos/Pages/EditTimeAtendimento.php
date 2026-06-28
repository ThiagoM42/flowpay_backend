<?php

namespace App\Filament\Resources\TimeAtendimentos\Pages;

use App\Filament\Resources\TimeAtendimentos\TimeAtendimentoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTimeAtendimento extends EditRecord
{
    protected static string $resource = TimeAtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
