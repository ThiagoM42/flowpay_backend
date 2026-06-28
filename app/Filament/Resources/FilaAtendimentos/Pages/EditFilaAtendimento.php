<?php

namespace App\Filament\Resources\FilaAtendimentos\Pages;

use App\Filament\Resources\FilaAtendimentos\FilaAtendimentoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFilaAtendimento extends EditRecord
{
    protected static string $resource = FilaAtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
