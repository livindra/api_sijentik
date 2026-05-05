<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StorageLinkSafe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:link-safe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create symbolic links for storage without failing if they already exist';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $links = config('filesystems.links', [
            public_path('storage') => storage_path('app/public'),
        ]);

        foreach ($links as $link => $target) {
            if (is_link($link)) {
                $this->line("The [{$link}] link already exists. Skipping.");
                continue;
            }

            if (is_dir($link)) {
                $this->warn("A directory already exists at [{$link}]. Removing it before creating the symlink.");
                $this->removeDirectory($link);
            }

            if (file_exists($link)) {
                $this->warn("A file already exists at [{$link}]. Removing it before creating the symlink.");
                unlink($link);
            }

            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }

            symlink($target, $link);
            $this->info("The [{$link}] link has been connected to [{$target}].");
        }

        $this->info('Storage links created successfully.');

        return self::SUCCESS;
    }

    /**
     * Recursively remove a directory and its contents.
     */
    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);

        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
