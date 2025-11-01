<?php

use App\Services\GmailReceiptImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gmail:import-receipts {--limit= : Número máximo de correos a procesar hoy}', function (GmailReceiptImportService $importer) {
    $limitOption = $this->option('limit');
    $limit = null;

    if ($limitOption !== null && $limitOption !== '') {
        if (!is_numeric($limitOption)) {
            $this->error('La opción --limit debe ser un número positivo o dejarse vacía para procesar todos los correos del día.');
            return;
        }

        $limit = (int) $limitOption;

        if ($limit <= 0) {
            $this->error('La opción --limit debe ser mayor que cero o dejarse vacía para procesar todos los correos del día.');
            return;
        }
    }

    if ($limit === null) {
        $this->info('Buscando todos los correos recibidos hoy en Gmail...');
    } else {
        $this->info("Buscando hasta {$limit} correos recibidos hoy en Gmail...");
    }

    $summary = $importer->import($limit);

    $this->newLine();
    $this->info('Resumen de importación:');
    $this->line("• Correos revisados: {$summary['messages_processed']}");
    $this->line("• Adjuntos considerados: {$summary['attachments_considered']}");
    $this->line("• Gastos creados: {$summary['expenses_created']}");

    if (!empty($summary['skipped'])) {
        $this->newLine();
        $this->warn('Saltos:');
        foreach ($summary['skipped'] as $note) {
            $this->line("  - {$note}");
        }
    }

    if (!empty($summary['errors'])) {
        $this->newLine();
        $this->error('Errores:');
        foreach ($summary['errors'] as $error) {
            $this->line("  - {$error}");
        }
    }
})->purpose('Importar comprobantes XML recibidos hoy desde Gmail como gastos temporales');
