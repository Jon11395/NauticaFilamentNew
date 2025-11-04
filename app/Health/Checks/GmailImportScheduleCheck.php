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
            // Get app timezone
            $timezone = config('app.timezone', 'UTC');
            
            // Timestamp is stored as Unix timestamp (timezone-agnostic)
            // Create Carbon instance in UTC first, then convert to app timezone
            $lastRunDate = Carbon::createFromTimestamp($lastRunTimestamp, 'UTC')->setTimezone($timezone);
            $now = Carbon::now($timezone);
            
            // Calculate time ago using total elapsed time (not calendar days)
            // Calculate total seconds difference, then convert to minutes/hours/days
            $totalSeconds = abs($now->diffInSeconds($lastRunDate));
            $totalMinutes = floor($totalSeconds / 60);
            $totalHours = floor($totalMinutes / 60);
            $totalDays = floor($totalHours / 24);

            // Format the time difference - prioritize showing smaller units when possible
            // Only show days if it's 24+ hours
            if ($totalHours >= 24) {
                $timeAgo = $totalDays . ' day' . ($totalDays > 1 ? 's' : '') . ' ago';
            } elseif ($totalHours >= 1) {
                $timeAgo = $totalHours . ' hour' . ($totalHours > 1 ? 's' : '') . ' ago';
            } elseif ($totalMinutes >= 1) {
                $timeAgo = $totalMinutes . ' minute' . ($totalMinutes > 1 ? 's' : '') . ' ago';
            } else {
                $timeAgo = 'just now';
            }

            // Calculate next run time
            $nextRunDate = $lastRunDate->copy()->addMinutes($gmailIntervalMinutes);
            
            // If the next run time is in the past, add another interval
            while ($nextRunDate->isPast()) {
                $nextRunDate->addMinutes($gmailIntervalMinutes);
            }

            // Calculate time until next run using total elapsed time
            // Calculate total seconds difference, then convert to minutes/hours/days
            $totalSecondsUntilNext = abs($now->diffInSeconds($nextRunDate, false));
            $totalMinutesUntilNext = floor($totalSecondsUntilNext / 60);
            $totalHoursUntilNext = floor($totalMinutesUntilNext / 60);
            $totalDaysUntilNext = floor($totalHoursUntilNext / 24);

            // Format time until next run - only show days if it's 24+ hours
            if ($totalHoursUntilNext >= 24) {
                $timeUntilNext = $totalDaysUntilNext . ' day' . ($totalDaysUntilNext > 1 ? 's' : '');
            } elseif ($totalHoursUntilNext >= 1) {
                $timeUntilNext = $totalHoursUntilNext . ' hour' . ($totalHoursUntilNext > 1 ? 's' : '');
            } elseif ($totalMinutesUntilNext >= 1) {
                $timeUntilNext = $totalMinutesUntilNext . ' minute' . ($totalMinutesUntilNext > 1 ? 's' : '');
            } else {
                $timeUntilNext = 'soon';
            }

            // Format interval
            if ($gmailIntervalMinutes >= 60) {
                $hours = floor($gmailIntervalMinutes / 60);
                $intervalText = $hours . ' hour' . ($hours > 1 ? 's' : '');
            } else {
                $intervalText = $gmailIntervalMinutes . ' minute' . ($gmailIntervalMinutes > 1 ? 's' : '');
            }

            // Format dates in a more readable way
            $lastRunFormatted = $lastRunDate->format('M j, Y g:i A');
            $nextRunFormatted = $nextRunDate->format('M j, Y g:i A');

            // Build short summary with cleaner format
            $shortSummary = "Last run: {$lastRunFormatted} ({$timeAgo}) • Next run: {$nextRunFormatted} (in {$timeUntilNext})";
            
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
            if ($gmailIntervalMinutes >= 60) {
                $hours = floor($gmailIntervalMinutes / 60);
                $intervalText = $hours . ' hour' . ($hours > 1 ? 's' : '');
            } else {
                $intervalText = $gmailIntervalMinutes . ' minute' . ($gmailIntervalMinutes > 1 ? 's' : '');
            }

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

