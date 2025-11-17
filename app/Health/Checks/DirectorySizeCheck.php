<?php

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Symfony\Component\Process\Process;

class DirectorySizeCheck extends Check
{
    protected ?string $directory = null;

    public function directory(string $directory): self
    {
        $this->directory = $directory;
        return $this;
    }

    public function run(): Result
    {
        $result = Result::make();

        try {
            // Get the directory to check (default to project root)
            $directory = $this->directory ?? base_path();
            
            // Verify the directory exists and is readable
            if (!is_dir($directory) || !is_readable($directory)) {
                return $result
                    ->failed('Directory is not accessible')
                    ->meta([
                        'Path' => $directory,
                        'Exists' => is_dir($directory),
                        'Readable' => is_readable($directory),
                    ]);
            }
            
            // Execute du -sh command on the specific directory
            // Use absolute path to avoid issues with relative paths
            $process = new Process(['du', '-sh', $directory]);
            $process->setTimeout(60); // Set timeout to 60 seconds
            $process->run();

            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                $stdOutput = $process->getOutput();
                
                return $result
                    ->failed('Could not execute du -sh command')
                    ->meta([
                        'Error output' => $errorOutput ?: '(empty)',
                        'Standard output' => $stdOutput ?: '(empty)',
                        'Path' => $directory,
                        'Exit code' => $process->getExitCode(),
                        'Command' => "du -sh {$directory}",
                    ]);
            }

            $output = trim($process->getOutput());
            
            // Parse output: format is "SIZE    PATH" (e.g., "427M    /path/to/project")
            // Split by whitespace to separate size and path
            $parts = preg_split('/\s+/', $output, 2);
            
            if (count($parts) < 1 || empty($parts[0])) {
                return $result
                    ->failed('Could not parse du -sh output')
                    ->meta([
                        'Raw output' => $output,
                        'Path' => $directory,
                    ]);
            }

            $size = $parts[0];
            $path = $parts[1] ?? $directory;

            // Always return OK status since du -sh just shows size, not a threshold
            $result->ok("Directory size: {$size}")
                ->meta([
                    'Size' => $size,
                    'Path' => $path,
                ]);

        } catch (\Exception $e) {
            return $result
                ->failed('Error checking directory size: ' . $e->getMessage())
                ->meta([
                    'Error' => $e->getMessage(),
                    'Path' => $this->directory ?? base_path(),
                ]);
        }

        return $result;
    }
}

