<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ManuscriptController extends Controller
{
    public function __invoke(Article $article): StreamedResponse
    {
        Gate::authorize('view', $article);
        abort_unless($article->pdf_path && Storage::disk('local')->exists($article->pdf_path), 404);

        return Storage::disk('local')->download($article->pdf_path, str($article->title)->slug().'.pdf');
    }
}
