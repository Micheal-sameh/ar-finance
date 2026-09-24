<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Pdf\PdfExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adds a uniform downloadPdf() to show controllers, mirroring
 * ExportsExcel::exportXlsx() so every document-download route goes through
 * the same mPDF setup.
 */
trait GeneratesPdf
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function downloadPdf(string $filename, string $view, array $data): StreamedResponse
    {
        return PdfExport::download($filename, $view, $data);
    }
}
