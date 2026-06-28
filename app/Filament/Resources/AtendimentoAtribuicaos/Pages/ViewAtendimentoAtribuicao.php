<?php

namespace App\Filament\Resources\AtendimentoAtribuicaos\Pages;

use App\Filament\Resources\AtendimentoAtribuicaos\AtendimentoAtribuicaoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAtendimentoAtribuicao extends ViewRecord
{
    protected static string $resource = AtendimentoAtribuicaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
