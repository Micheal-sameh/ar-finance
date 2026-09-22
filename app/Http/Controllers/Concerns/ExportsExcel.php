<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Excel\ExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adds a uniform exportXlsx() to index controllers. Export actions reuse the
 * same paginate($filters, …) call as index() but with a large per-page, so
 * the download always matches the currently applied filters/search without
 * a separate "unfiltered" query path to keep in sync.
 */
trait ExportsExcel
{
    /**
     * Large enough to cover any tenant's full filtered result set in one
     * page while still going through the existing paginate() query (same
     * filters, same ordering) rather than a bespoke unfiltered fetch.
     */
    private const EXPORT_MAX_ROWS = 100_000;

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    protected function exportXlsx(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return ExcelExport::download($filename, $headers, $rows);
    }

    protected function exportMaxRows(): int
    {
        return self::EXPORT_MAX_ROWS;
    }
}
