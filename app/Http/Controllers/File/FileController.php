<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use Helper\Response\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Translation\Message;

class FileController extends Controller {
    /**
     * Serve a secure file from private storage.
     *
     * @param string $disk
     * @param string $path
     */
    public function serve(string $disk, string $path) {
        if (!Auth::check()) {
            Response::_401();
        }

        $allowedDisks = ['local'];
        if (!in_array($disk, $allowedDisks)) {
            Response::_403(Message::get('invalid_disk'));
        }

        if (!Storage::disk($disk)->exists($path)) {
            return Response::_404(Message::get('file_not_found'));
        }

        $headers = [];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $inlineExtensions = SECURE_FILE_ALLOWED_EXTENSIONS;

        if (in_array($extension, $inlineExtensions)) {
            $headers['Content-Disposition'] = 'inline; filename="' . basename($path) . '"';
        }

        return Storage::disk($disk)->response($path, null, $headers);
    }
}
