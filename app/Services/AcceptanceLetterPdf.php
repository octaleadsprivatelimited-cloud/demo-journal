<?php

namespace App\Services;

use App\Models\Article;
use Dompdf\Dompdf;
use Dompdf\Options;

class AcceptanceLetterPdf
{
    public function render(Article $article, array $acceptance): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', sys_get_temp_dir());
        $pdf = new Dompdf($options);
        $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('assets/larix-logo-transparent.png')));
        $pdf->loadHtml(view('workflow.acceptance-letter', compact('article', 'acceptance', 'logo'))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
