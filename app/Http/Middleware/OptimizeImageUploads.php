<?php

namespace App\Http\Middleware;

use App\Services\ImageUploadOptimizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class OptimizeImageUploads
{
    public function handle(Request $request, Closure $next)
    {
        $walk = function (array $files, string $prefix = '') use (&$walk): void {
            foreach ($files as $key => $file) {
                $field = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                if (is_array($file)) {
                    $walk($file, $field);
                } elseif ($file instanceof SymfonyUploadedFile) {
                    app(ImageUploadOptimizer::class)->optimize(UploadedFile::createFromBase($file), $field);
                }
            }
        };
        $walk($request->files->all());

        return $next($request);
    }
}
