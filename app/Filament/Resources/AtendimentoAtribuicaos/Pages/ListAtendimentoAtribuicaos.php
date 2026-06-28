<?php

namespace App\Filament\Resources\AtendimentoAtribuicaos\Pages;

use App\Filament\Resources\AtendimentoAtribuicaos\AtendimentoAtribuicaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAtendimentoAtribuicaos extends ListRecords
{
    protected static string $resource = AtendimentoAtribuicaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
