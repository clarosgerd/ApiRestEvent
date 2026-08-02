<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class OpsLogController extends Controller
{
    private const ALLOWED_FILES = ['scheduler.log', 'laravel.log'];
    private const LINES = 300;

    public function index(Request $request): View
    {
        $file = $request->query('file', 'scheduler.log');
        if (!in_array($file, self::ALLOWED_FILES, true)) {
            $file = 'scheduler.log';
        }

        $path = storage_path('logs/'.$file);
        $lines = file_exists($path) ? $this->tail($path, self::LINES) : 'El archivo todavía no existe.';

        return view('ops.logs', [
            'file' => $file,
            'files' => self::ALLOWED_FILES,
            'lines' => $lines,
        ]);
    }

    /**
     * Lee las últimas $limit líneas sin cargar el archivo completo en
     * memoria — los logs de scheduler/laravel pueden crecer bastante.
     */
    private function tail(string $path, int $limit): string
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $limit);
        $file->seek($startLine);

        $output = [];
        while (!$file->eof()) {
            $output[] = $file->fgets();
        }

        return implode('', $output);
    }
}
