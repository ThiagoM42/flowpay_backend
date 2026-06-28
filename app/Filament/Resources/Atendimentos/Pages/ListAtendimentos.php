<?php

namespace App\Filament\Resources\Atendimentos\Pages;

use App\Filament\Resources\Atendimentos\AtendimentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAtendimentos extends ListRecords
{
    protected static string $resource = AtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
