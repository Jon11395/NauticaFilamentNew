<?php

namespace App\Filament\Resources\TemporalExpenseResource\Pages;

use App\Filament\Resources\TemporalExpenseResource;
use App\Filament\Resources\TemporalExpenseResource\Widgets\TemporalExpensesStats;
use App\Services\GmailReceiptImportService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListTemporalExpenses extends ListRecords
{
    protected static string $resource = TemporalExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\Action::make('sync_gmail_receipts')
                ->label('Sincronizar Gmail')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (): void {
                    try {
                        $summary = app(GmailReceiptImportService::class)->import(25);

                        $this->dispatch('temporal-expenses-updated');

                        $details = new HtmlString(
                            '<ul>' .
                                '<li>Correos revisados: ' . ($summary['messages_processed'] ?? 0) . '</li>' .
                                '<li>Adjuntos considerados: ' . ($summary['attachments_considered'] ?? 0) . '</li>' .
                                '<li>Gastos creados: ' . ($summary['expenses_created'] ?? 0) . '</li>' .
                            '</ul>'
                        );

                        Notification::make()
                            ->title('Sincronización completada')
                            ->body($details)
                            ->success()
                            ->send();


                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Error al sincronizar Gmail')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TemporalExpensesStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'md' => 1,
        ];
    }
}
