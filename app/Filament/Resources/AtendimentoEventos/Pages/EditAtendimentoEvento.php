<?php

namespace App\Filament\Resources\AtendimentoEventos\Pages;

use App\Filament\Resources\AtendimentoEventos\AtendimentoEventoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAtendimentoEvento extends EditRecord
{
    protected static string $resource = AtendimentoEventoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
