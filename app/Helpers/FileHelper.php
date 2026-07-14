<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileHelper {
    /**
     * Upload a single file
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param string $disk
     * @return string
     */
    public static function uploadSingle(UploadedFile $file, string $directory = 'uploads', string $disk = 'public'): string {
        $originalName = time() . '_' . $file->getClientOriginalName();
        return $file->storeAs($directory, $originalName, $disk);
    }

    /**
     * Upload a single file and return file details
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param string $disk
     * @param bool $fullPath
     * @return array
     */
    public static function uploadSingleWithDetails(UploadedFile $file, string $directory = 'uploads', string $disk = 'public', bool $fullPath = false): array {
        $originalName = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs($directory, $originalName, $disk);
        return [
            'file_path' => $fullPath ? Storage::disk($disk)->path($path) : $path,
            'file_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Upload multiple files
     *
     * @param array $files
     * @param string $directory
     * @param string $disk
     * @return array
     */
    public static function uploadMultiple(array $files, string $directory = 'uploads', string $disk = 'public'): array {
        $paths = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $originalName = time() . '_' . $file->getClientOriginalName();
                $paths[] = $file->storeAs($directory, $originalName, $disk);
            }
        }
        return $paths;
    }

    /**
     * Download a file
     *
     * @param string $path
     * @param string|null $name
     * @param string $disk
     * @return BinaryFileResponse
     */
    public static function download(string $path, string $name = null, string $disk = 'public'): BinaryFileResponse {
        return Storage::disk($disk)->download($path, $name);
    }

    /**
     * Delete a file
     *
     * @param string $path
     * @param string $disk
     * @return bool
     */
    public static function delete(string $path, string $disk = 'public'): bool {
        return Storage::disk($disk)->delete($path);
    }

    /**
     * Delete multiple files
     *
     * @param array $paths
     * @param string $disk
     * @return bool
     */
    public static function deleteMultiple(array $paths, string $disk = 'public'): bool {
        return Storage::disk($disk)->delete($paths);
    }

    /**
     * Upload multiple files and return file details
     *
     * @param array $files
     * @param string $directory
     * @param string $disk
     * @param bool $fullPath
     * @return array
     */
    public static function uploadMultipleWithDetails(array $files, string $directory = 'uploads', string $disk = 'public', bool $fullPath = false): array {
        $fileDetails = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $originalName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs($directory, $originalName, $disk);
                $fileDetails[] = [
                    'file_path' => $fullPath ? Storage::disk($disk)->path($path) : $path,
                    'file_name' => $file->getClientOriginalName(),
                ];
            }
        }
        return $fileDetails;
    }
}