<?php

namespace App\Filament\Resources\TimeAtendimentos\Pages;

use App\Filament\Resources\TimeAtendimentos\TimeAtendimentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTimeAtendimentos extends ListRecords
{
    protected static string $resource = TimeAtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
