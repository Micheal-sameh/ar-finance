<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a PDF rendered from a Blade view. The single place every
 * document-download route goes through, so every PDF shares the same mPDF
 * setup — including automatic per-script font switching, which is what
 * makes Arabic text (client/vendor/employee names, addresses) render with
 * correct letter shaping and joining instead of the disconnected glyphs
 * you'd get from a Latin-only font.
 */
class PdfExport
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws MpdfException
     */
    public static function download(string $filename, string $view, array $data): StreamedResponse
    {
        $mpdf = new Mpdf([
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'margin_right' => 15,
        ]);

        // Lets mPDF detect Arabic (and other non-Latin) runs inside the
        // otherwise-Latin template and swap in a script-appropriate font
        // (xbriyaz for Arabic) with proper OTL shaping/kashida, rather than
        // rendering Arabic glyphs in DejaVu Sans, which has no Arabic set.
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->useSubstitutions = true;

        $mpdf->WriteHTML(View::make($view, $data)->render());

        return response()->streamDownload(function () use ($mpdf) {
            echo $mpdf->Output('', 'S');
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
