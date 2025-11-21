<?php

namespace App\Console\Commands;

use App\Models\Expense;
use Illuminate\Console\Command;

class DeleteOldExpenseAttachments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expenses:delete-old-attachments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete attachments for expenses older than the configured retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get retention period from database
        $retentionMonths = \App\Models\GlobalConfig::getValue('expense_attachment_retention_months', 12);
        
        $this->info('Starting deletion of old expense attachments...');
        
        if ($retentionMonths == 0) {
            $this->line('Retention period: Disabled (attachments will not be deleted)');
        } else {
            $this->line("Retention period: {$retentionMonths} months");
        }
        $this->newLine();

        $stats = Expense::deleteOldAttachments();

        // Check if deletion is disabled
        if (isset($stats['message'])) {
            $this->info($stats['message']);
            return Command::SUCCESS;
        }

        $this->info('Deletion completed!');
        $this->newLine();
        $this->line("• Expenses processed: {$stats['expenses_processed']}");
        $this->line("• Attachments deleted: {$stats['attachments_deleted']}");
        $this->line("• Files deleted: {$stats['files_deleted']}");
        $this->line("• Files skipped (used by other expenses): {$stats['files_skipped']}");

        return Command::SUCCESS;
    }
}

