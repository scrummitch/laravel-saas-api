<?php

namespace App\Http\Concerns;

use Illuminate\Http\Request;

/**
 * Shared `per_page` resolver. Keeps user-controlled pagination from
 * being a DoS-amplification vector — eager-loading 9999 rows × deep
 * relations is a way to OOM the API with a single request.
 */
trait ResolvesPerPage
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 100;

    protected function perPage(Request $request, int $default = self::DEFAULT_PER_PAGE): int
    {
        $requested = $request->integer('per_page', $default);

        return max(1, min(self::MAX_PER_PAGE, $requested));
    }
}
