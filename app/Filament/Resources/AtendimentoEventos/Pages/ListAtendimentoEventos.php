<?php

namespace App\Filament\Resources\AtendimentoEventos\Pages;

use App\Filament\Resources\AtendimentoEventos\AtendimentoEventoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAtendimentoEventos extends ListRecords
{
    protected static string $resource = AtendimentoEventoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
