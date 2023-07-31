<?php

namespace NiclasTimm\LaravelDbImporter;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class Importer
{

    /**
     * The name for the unzipped backup file.
     */
    const TMP_UNZIPPED_FILENAME = 'backup.sql';

    /**
     * The filetype of the remote backup. .sql, .zip, .gz are allowed.
     * @var string
     */
    private string $remoteBackupFileType;

    /**
     * The name of the temporary zip file we will create.
     *
     * @var string
     */
    private string $zipFileName;

    /**
     * Handle the command.
     *
     * @return void
     *
     * @throws Exception
     */
    public function handle(): void
    {
        $fileContent = $this->_getLatestBackupFileContent();

        $this->_createTmpZipFile($fileContent);

        $this->_importBackup();

        $this->_cleanUp();
    }

    /**
     * Download the latest backup file from the S3 bucket.
     *
     * @throws Exception
     */
    private function _getLatestBackupFileContent(): ?string
    {
        $directory = config('dbimporter.s3.prefix');

        $fileNames = Storage::disk('s3')->files($directory);

        if (empty($fileNames)) {
            throw new Exception('No backups detected. Did you set the correct prefix in the config?');
        }

        $withTimestamps = [];
        foreach ($fileNames as $filename) {
            $lastModified = Storage::disk('s3')->lastModified($filename);
            $withTimestamps[$lastModified] = $filename;
        }

        $latest = $this->_getLatest($withTimestamps);

        $this->remoteBackupFileType = Storage::disk('s3')->mimeType($latest);

        return Storage::disk('s3')->get($latest);
    }

    /**
     * Helper function to get the name of the latest remote backup file.
     *
     * @param  array  $withTimestamps
     *   The filenames with their timestamps as array keys.
     *
     * @return string
     *   The name of the latest backup file.
     */
    private function _getLatest(array $withTimestamps): string
    {
        $sortedByTimestamp = $this->_sortByTimestamps($withTimestamps);

        return $sortedByTimestamp[0];
    }

    /**
     * Sort by timestamps.
     *
     * @param  array  $withTimestamps
     *   The timestamps.
     */
    private function _sortByTimestamps(array $withTimestamps): array
    {
        krsort($withTimestamps);

        return array_values($withTimestamps);
    }

    /**
     * Create and store a temporary file of the downloaded backup file.
     *
     * @param  mixed  $fileContent
     *   The file content.
     *
     * @throws Exception
     */
    private function _createTmpZipFile(mixed $fileContent): void
    {
        $this->zipFileName = 'backup_'.Str::random(10);

        switch ($this->remoteBackupFileType) {
            case 'application/x-gzip':
                $this->zipFileName .= '.gz';
                $this->_extractGzip($fileContent);
                break;
            case 'application/zip':
                $this->zipFileName .= '.zip';
                $this->_extractZip($fileContent);
                break;
            default:
                throw new Exception('Forbidden file type detected. Only .sql, .zip and .gz are allowed');
                break;
        }
    }

    /**
     * Unzip gzip file and store it as new file.
     *
     * @param  mixed  $fileContent
     *   The content of the gzip file.
     *
     * @return void
     */
    private function _extractGzip(mixed $fileContent): void
    {
        $tmpDir = storage_path('app/tmp/');
        $filePathFull = $tmpDir.$this->zipFileName;
        Storage::disk('local')->put('tmp/'.$this->zipFileName, $fileContent);
        $gz = gzopen($filePathFull, 'rb');
        if (!$gz) {
            throw new \UnexpectedValueException('Could not open gzip file');
        }
        $dest = fopen($tmpDir.self::TMP_UNZIPPED_FILENAME, 'wb');
        while (!gzeof($gz)) {
            fwrite($dest, gzread($gz, 4096));
        }
        gzclose($gz);
        fclose($dest);
    }

    /**
     * Unzip zip archive and store it as new file.
     *
     * @param  mixed  $fileContent
     *   The content of the gzip file.
     *
     * @return void
     */
    private function _extractZip(mixed $fileContent): void
    {
        $zip = new ZipArchive();
        $tmpDir = storage_path('app/tmp/');
        $filePathFull = $tmpDir.$this->zipFileName;
        Storage::disk('local')->put('tmp/'.$this->zipFileName, $fileContent);
        if ($zip->open($filePathFull)) {
            $zip->extractTo($tmpDir.self::TMP_UNZIPPED_FILENAME);
        }
        $zip->close();
    }

    /**
     * Import the sql file to the database.
     *
     * @return void
     */
    private function _importBackup(): void
    {
        $basePath = storage_path('app/tmp/').self::TMP_UNZIPPED_FILENAME;

        if (config('dbimporter.zip_full_path')) {
            DB::unprepared(file_get_contents($basePath.'/'.config('dbimporter.zip_full_path')));
        } else {
            DB::unprepared(file_get_contents($basePath));
        }
    }

    /**
     * Cleanup. Remove temporary files etc.
     *
     * @return void
     */
    private function _cleanUp(): void
    {
        if (is_dir(storage_path('app/tmp/').self::TMP_UNZIPPED_FILENAME)) {
            File::deleteDirectory(storage_path('app/tmp/').self::TMP_UNZIPPED_FILENAME);
        } else {
            File::delete(storage_path('app/tmp/').self::TMP_UNZIPPED_FILENAME);
        }
        unlink(storage_path('app/tmp/').$this->zipFileName);
    }

}