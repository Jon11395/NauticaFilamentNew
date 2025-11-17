<?php

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Symfony\Component\Process\Process;

class DiskSpaceCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        try {
            // Execute df -h command
            $process = Process::fromShellCommandline('df -h');
            $process->run();

            if (!$process->isSuccessful()) {
                return $result
                    ->failed('Could not execute df -h command')
                    ->meta([
                        'Error' => $process->getErrorOutput(),
                    ]);
            }

            $output = trim($process->getOutput());
            $lines = explode("\n", $output);
            
            // First line is the header
            $header = array_shift($lines);
            
            // Parse each filesystem line
            $filesystems = [];
            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                
                // Split by whitespace (handles multiple spaces)
                $parts = preg_split('/\s+/', trim($line));
                
                if (count($parts) >= 6) {
                    $filesystems[] = [
                        'Filesystem' => $parts[0],
                        'Size' => $parts[1],
                        'Used' => $parts[2],
                        'Avail' => $parts[3],
                        'Use%' => $parts[4],
                        'Mounted on' => $parts[5],
                    ];
                }
            }

            // Find the root filesystem (/) to determine overall status
            $rootFs = null;
            foreach ($filesystems as $fs) {
                if ($fs['Mounted on'] === '/') {
                    $rootFs = $fs;
                    break;
                }
            }

            // If no root found, use the first one
            if (!$rootFs && !empty($filesystems)) {
                $rootFs = $filesystems[0];
            }

            // Determine status based on root filesystem usage
            if ($rootFs) {
                $usagePercent = (int) rtrim($rootFs['Use%'], '%');
                
                if ($usagePercent >= 90) {
                    $result->failed("Disk usage is at {$rootFs['Use%']} ({$rootFs['Used']} used of {$rootFs['Size']})");
                } elseif ($usagePercent >= 75) {
                    $result->warning("Disk usage is at {$rootFs['Use%']} ({$rootFs['Used']} used of {$rootFs['Size']})");
                } else {
                    $result->ok("Disk usage is at {$rootFs['Use%']} ({$rootFs['Used']} used of {$rootFs['Size']})");
                }

                // Build metadata with all filesystems
                $meta = [];
                foreach ($filesystems as $fs) {
                    $key = $fs['Mounted on'] === '/' 
                        ? 'Root (/)' 
                        : $fs['Mounted on'];
                    
                    $meta[$key] = "{$fs['Used']} / {$fs['Size']} ({$fs['Use%']}) - Available: {$fs['Avail']}";
                }

                $result->meta($meta);
            } else {
                $result->ok('Disk space check completed');
                
                // Still show all filesystems in metadata
                $meta = [];
                foreach ($filesystems as $fs) {
                    $key = $fs['Mounted on'] === '/' 
                        ? 'Root (/)' 
                        : $fs['Mounted on'];
                    
                    $meta[$key] = "{$fs['Used']} / {$fs['Size']} ({$fs['Use%']}) - Available: {$fs['Avail']}";
                }
                
                $result->meta($meta);
            }

        } catch (\Exception $e) {
            return $result
                ->failed('Error checking disk space: ' . $e->getMessage())
                ->meta([
                    'Error' => $e->getMessage(),
                ]);
        }

        return $result;
    }
}

