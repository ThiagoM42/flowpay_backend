<?php

namespace App\Filament\Resources\AtendimentoAtribuicaos\Pages;

use App\Filament\Resources\AtendimentoAtribuicaos\AtendimentoAtribuicaoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAtendimentoAtribuicao extends EditRecord
{
    protected static string $resource = AtendimentoAtribuicaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
