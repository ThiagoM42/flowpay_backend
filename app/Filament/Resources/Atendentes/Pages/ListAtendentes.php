<?php

namespace App\Filament\Resources\Atendentes\Pages;

use App\Filament\Resources\Atendentes\AtendenteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAtendentes extends ListRecords
{
    protected static string $resource = AtendenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
