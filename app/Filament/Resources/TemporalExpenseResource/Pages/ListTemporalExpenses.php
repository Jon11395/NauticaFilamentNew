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
                        $summary = app(GmailReceiptImportService::class)->import();

                        $this->dispatch('temporal-expenses-updated');

                        // Check if there are connection errors or if no messages were processed due to connection issues
                        $hasConnectionError = !empty($summary['errors']) && 
                            (str_contains(implode(' ', $summary['errors']), 'Failed to fetch') || 
                             str_contains(implode(' ', $summary['errors']), 'Gmail') ||
                             str_contains(implode(' ', $summary['errors']), 'connection') ||
                             str_contains(implode(' ', $summary['errors']), 'token') ||
                             str_contains(implode(' ', $summary['errors']), 'credentials'));

                        if ($hasConnectionError) {
                            // Show error notification for connection issues
                            $errorDetails = new HtmlString(
                                '<ul>' .
                                    '<li><strong>Error:</strong> ' . htmlspecialchars($summary['errors'][0] ?? 'No se pudo conectar a Gmail') . '</li>' .
                                '</ul>'
                            );

                            Notification::make()
                                ->title('Error al sincronizar Gmail')
                                ->body($errorDetails)
                                ->danger()
                                ->send();
                        } elseif (!empty($summary['errors'])) {
                            // Show warning notification if there are other errors but connection was successful
                            $errorList = '<ul>';
                            foreach ($summary['errors'] as $error) {
                                $errorList .= '<li>' . htmlspecialchars($error) . '</li>';
                            }
                            $errorList .= '</ul>';

                            $details = new HtmlString(
                                '<ul>' .
                                    '<li>Correos revisados: ' . ($summary['messages_processed'] ?? 0) . '</li>' .
                                    '<li>Adjuntos considerados: ' . ($summary['attachments_considered'] ?? 0) . '</li>' .
                                    '<li>Gastos creados: ' . ($summary['expenses_created'] ?? 0) . '</li>' .
                                '</ul>' .
                                '<div style="margin-top: 10px;"><strong>Errores:</strong>' . $errorList . '</div>'
                            );

                            Notification::make()
                                ->title('Sincronización completada con errores')
                                ->body($details)
                                ->warning()
                                ->send();
                        } else {
                            // Show success notification
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
                        }

                        // Refresh the page to update navigation badge
                        $this->redirect(static::getResource()::getUrl('index'));

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
