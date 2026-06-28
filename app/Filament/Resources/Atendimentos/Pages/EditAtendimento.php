<?php

namespace App\Filament\Resources\Atendimentos\Pages;

use App\Filament\Resources\Atendimentos\AtendimentoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAtendimento extends EditRecord
{
    protected static string $resource = AtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
