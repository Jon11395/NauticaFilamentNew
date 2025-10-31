<?php

use App\Services\GmailReceiptImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gmail:import-receipts {--limit=20}', function (GmailReceiptImportService $importer) {
    $limit = (int) $this->option('limit');

    $this->info("Buscando hasta {$limit} correos no leídos en Gmail...");

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

    if (!empty($summary['notes'])) {
        $this->newLine();
        $this->info('Notas:');
        foreach ($summary['notes'] as $note) {
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
})->purpose('Importar comprobantes XML desde Gmail como gastos temporales');
