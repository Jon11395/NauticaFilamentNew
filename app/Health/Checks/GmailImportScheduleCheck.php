<?php

namespace App\Health\Checks;

use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Result;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\GlobalConfig;

class GmailImportScheduleCheck extends ScheduleCheck
{
    public function run(): Result
    {
        $result = parent::run();

        // Get the last successful run timestamp from cache
        // Using the cache key 'health:schedule:gmail-import' as defined in AppServiceProvider
        $cacheKey = 'health:schedule:gmail-import';
        $lastRunTimestamp = Cache::store(config('cache.default'))->get($cacheKey);

        // Get the sync interval from GlobalConfig
        $gmailIntervalMinutes = (int) GlobalConfig::getValue('gmail_sync_interval_minutes', 60);

        if ($lastRunTimestamp) {
            $lastRunDate = Carbon::createFromTimestamp($lastRunTimestamp);
            $now = Carbon::now();
            $diffInMinutes = $now->diffInMinutes($lastRunDate);
            $diffInHours = $now->diffInHours($lastRunDate);
            $diffInDays = $now->diffInDays($lastRunDate);

            // Format the time difference
            if ($diffInDays > 0) {
                $timeAgo = $diffInDays . ' day' . ($diffInDays > 1 ? 's' : '') . ' ago';
            } elseif ($diffInHours > 0) {
                $timeAgo = $diffInHours . ' hour' . ($diffInHours > 1 ? 's' : '') . ' ago';
            } else {
                $timeAgo = $diffInMinutes . ' minute' . ($diffInMinutes > 1 ? 's' : '') . ' ago';
            }

            // Calculate next run time
            $nextRunDate = $lastRunDate->copy()->addMinutes($gmailIntervalMinutes);
            
            // If the next run time is in the past (shouldn't happen, but just in case), add another interval
            if ($nextRunDate->isPast()) {
                $nextRunDate->addMinutes($gmailIntervalMinutes);
            }

            // Calculate time until next run
            $minutesUntilNext = $now->diffInMinutes($nextRunDate, false);
            $hoursUntilNext = $now->diffInHours($nextRunDate, false);
            $daysUntilNext = $now->diffInDays($nextRunDate, false);

            // Format time until next run
            if ($daysUntilNext > 0) {
                $timeUntilNext = $daysUntilNext . ' day' . ($daysUntilNext > 1 ? 's' : '');
            } elseif ($hoursUntilNext > 0) {
                $timeUntilNext = $hoursUntilNext . ' hour' . ($hoursUntilNext > 1 ? 's' : '');
            } else {
                $timeUntilNext = $minutesUntilNext . ' minute' . ($minutesUntilNext > 1 ? 's' : '');
            }

            // Format interval
            $intervalText = $gmailIntervalMinutes >= 60 
                ? ($gmailIntervalMinutes / 60) . ' hour' . ($gmailIntervalMinutes >= 120 ? 's' : '')
                : $gmailIntervalMinutes . ' minute' . ($gmailIntervalMinutes > 1 ? 's' : '');

            // Build short summary with key details
            $shortSummary = "Last: {$lastRunDate->format('Y-m-d H:i:s')} ({$timeAgo}) | Next: {$nextRunDate->format('Y-m-d H:i:s')} (in {$timeUntilNext})";
            
            // Add metadata about the last successful run and next run
            $result->meta([
                'Last successful run' => $lastRunDate->format('Y-m-d H:i:s'),
                'Time ago' => $timeAgo,
                'Next scheduled run' => $nextRunDate->format('Y-m-d H:i:s'),
                'Time until next run' => $timeUntilNext,
                'Sync interval' => $intervalText,
            ])->shortSummary($shortSummary);
        } else {
            // Format interval
            $intervalText = $gmailIntervalMinutes >= 60 
                ? ($gmailIntervalMinutes / 60) . ' hour' . ($gmailIntervalMinutes >= 120 ? 's' : '')
                : $gmailIntervalMinutes . ' minute' . ($gmailIntervalMinutes > 1 ? 's' : '');

            $shortSummary = "No heartbeat recorded yet | Interval: {$intervalText}";
            
            $result->meta([
                'Last successful run' => 'Never',
                'Status' => 'No heartbeat recorded yet',
                'Sync interval' => $intervalText,
            ])->shortSummary($shortSummary);
        }

        return $result;
    }
}

