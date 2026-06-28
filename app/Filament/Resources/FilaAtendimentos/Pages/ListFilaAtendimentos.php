<?php

namespace App\Filament\Resources\FilaAtendimentos\Pages;

use App\Filament\Resources\FilaAtendimentos\FilaAtendimentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFilaAtendimentos extends ListRecords
{
    protected static string $resource = FilaAtendimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
