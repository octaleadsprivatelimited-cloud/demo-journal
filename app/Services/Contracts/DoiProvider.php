<?php

namespace App\Services\Contracts;

use App\Models\Article;

interface DoiProvider
{
    /** Prepare provider-neutral metadata for manual deposit or a future Crossref adapter. */
    public function prepare(Article $article): array;
}
