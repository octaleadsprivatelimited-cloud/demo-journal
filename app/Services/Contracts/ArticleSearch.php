<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ArticleSearch
{
    /** @param array<string, mixed> $filters */
    public function search(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
