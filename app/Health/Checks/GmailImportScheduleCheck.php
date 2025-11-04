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
        // Use our own cache key (set via ->cacheKey() in AppServiceProvider)
        $cacheKey = $this->getCacheKey();
        $lastRunTimestamp = Cache::store($this->getCacheStoreName())->get($cacheKey);
        
        // Create a new result instead of using parent::run() to ensure we're checking the right schedule
        $result = Result::make();
        
        // Check if the schedule ran within the max age
        if ($lastRunTimestamp) {
            $timezone = config('app.timezone', 'UTC');
            $lastRunDate = Carbon::createFromTimestamp($lastRunTimestamp, 'UTC')->setTimezone($timezone);
            $now = Carbon::now($timezone);
            
            // Calculate minutes ago using the same logic as parent but with timezone awareness
            $minutesAgo = $lastRunDate->diffInMinutes($now);
            
            // Check if it's within the max age
            if ($minutesAgo <= $this->heartbeatMaxAgeInMinutes) {
                $result->ok();
            } else {
                $result->failed("The last run of the schedule was more than {$minutesAgo} minutes ago.");
            }
        } else {
            $result->failed('The schedule did not run yet.');
        }

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

            // Calculate next run time based on the actual cron schedule
            // Use the same logic as Kernel.php to determine the schedule pattern
            // First, validate the interval (same checks as Kernel.php)
            if ($gmailIntervalMinutes < 60 || $gmailIntervalMinutes % 60 !== 0) {
                // Invalid interval - schedule won't run
                $nextRunDate = null;
            } else {
                $hours = (int) ($gmailIntervalMinutes / 60);
                
                if ($hours === 1) {
                    // Cron: '0 * * * *' - Runs every hour at minute 0 (e.g., 8:00, 9:00, 10:00)
                    // Next run is the start of the next hour
                    $nextRunDate = $now->copy()->startOfHour()->addHour();
                } elseif ($hours < 24) {
                    // Cron: "0 */{$hours} * * *" - Runs every N hours at minute 0
                    // Example: every 2 hours = 0:00, 2:00, 4:00, 6:00, 8:00, 10:00, etc.
                    $currentHour = $now->hour;
                    
                    // Find the next hour that's a multiple of the interval
                    $nextHour = (int) (ceil(($currentHour + 1) / $hours) * $hours);
                    
                    if ($nextHour >= 24) {
                        // Next run is tomorrow at midnight (hour 0)
                        $nextRunDate = $now->copy()->startOfDay()->addDay()->hour(0)->minute(0)->second(0);
                    } else {
                        // Next run is today at the calculated hour
                        $nextRunDate = $now->copy()->hour($nextHour)->minute(0)->second(0);
                        // If we've already passed this hour today, move to the next interval
                        if ($nextRunDate->isPast()) {
                            $nextHour = $nextHour + $hours;
                            if ($nextHour >= 24) {
                                $nextRunDate = $now->copy()->startOfDay()->addDay()->hour(0)->minute(0)->second(0);
                            } else {
                                $nextRunDate->hour($nextHour);
                            }
                        }
                    }
                } elseif ($hours === 24) {
                    // Cron: '0 0 * * *' - Runs daily at midnight
                    $nextRunDate = $now->copy()->startOfDay()->addDay();
                } else {
                    // Cron: "0 0 */{$days} * *" - Runs every N days at midnight
                    $days = (int) ($hours / 24);
                    $nextRunDate = $now->copy()->startOfDay();
                    // Find the next run day
                    while ($nextRunDate->isPast() || $nextRunDate->isSameDay($now)) {
                        $nextRunDate->addDays($days);
                    }
                }
            }
            
            // If we couldn't calculate next run (invalid interval), use a fallback
            if (!$nextRunDate) {
                // Fallback: just add the interval to now
                $nextRunDate = $now->copy()->addMinutes($gmailIntervalMinutes);
            }
            
            // Ensure we're in the app timezone
            $nextRunDate->setTimezone($timezone);

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

