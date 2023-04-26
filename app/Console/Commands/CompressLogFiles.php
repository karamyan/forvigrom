<?php


namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class CompressLogFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compress_log_files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compressing all log files from storage.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Interval after that the file be compressed.
     */
    private const COMPRESS_INTERVAL = 14;

    /**
     * Interval after that the file is going to delete from compressed.
     */
    private const REMOVE_FROM_COMPRESS_INTERVAL = 180;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $root = getcwd();

        ini_set('memory_limit', '1G');

        $this->zip(source: $root . '/storage/nginx/access', folder: 'access', destination: $root . '/backup_logs.zip');
        $this->zip(source: $root . '/storage/logs/requests', folder: 'requests', destination: $root . '/backup_logs.zip');
        $this->zip(source: $root . '/storage/logs/errors', folder: 'errors', destination: $root . '/backup_logs.zip');

        $this->deleteOlderFilesFromZip(path: $root . '/backup_logs.zip');
    }

    private function zip(string $source, string $folder, string $destination): bool
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZipArchive::CREATE)) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
            $now = now();

            foreach ($files as $file) {
                if ($file->getExtension() != 'log')
                    continue;

                $file = str_replace('\\', '/', $file);

                // Ignore "." and ".." folders
                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..')))
                    continue;

                $file = realpath($file);

                $fileDate = substr($file, -14, 10);

                try {
                    $diff = $now->diffInDays(Carbon::createFromFormat('Y-m-d', $fileDate));
                } catch (\Throwable $exception) {
                    continue;
                }

                if ($diff < self::COMPRESS_INTERVAL)
                    continue;

                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', $folder . '/', $file . '/'));
                } else if (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . '/', $folder . '/', $file), file_get_contents($file));
                }
                unlink($file);
            }
        } else if (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }

        return $zip->close();
    }

    private function deleteOlderFilesFromZip(string $path): bool
    {
        $zip = new ZipArchive();
        $zip->open($path);

        $now = now();
        $entries = $zip->count();
        for ($i = 0; $i < $entries; $i++) {
            $stat = $zip->statIndex($i);
            $fileDate = substr($stat['name'], -14, 10);
            $diff = $now->diffInDays(Carbon::createFromFormat('Y-m-d', $fileDate));

            if ($diff > self::REMOVE_FROM_COMPRESS_INTERVAL) {
                $zip->deleteName($stat['name']);
            }
        }
        return $zip->close();
    }
}
