<?php

namespace App\Filament\Resources\IncomeResource\Pages;

use App\Filament\Resources\IncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Guava\FilamentNestedResources\Concerns\NestedPage;
use Illuminate\Contracts\Support\Htmlable;

class CreateIncome extends CreateRecord
{

    use NestedPage;

    protected static string $resource = IncomeResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('Crear proyecto');
    }
}
