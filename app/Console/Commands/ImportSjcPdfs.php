<?php

namespace App\Console\Commands;

use App\Models\{Article, Setting};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Storage};

class ImportSjcPdfs extends Command
{
    protected $signature = 'journal:import-sjc-pdfs {directory} {--apply}';
    protected $description = 'Attach validated source PDFs to locally imported SJC articles.';

    public function handle(): int
    {
        if (!app()->environment(['local','testing'])) { $this->error('Local imports only.'); return self::FAILURE; }
        $directory = rtrim($this->argument('directory'), '/');
        $manifest = json_decode(file_get_contents($directory.'/pdf-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $plan = [];
        foreach ($manifest as $row) {
            if (isset($row['error']) || !ctype_digit((string)($row['id'] ?? ''))) throw new \RuntimeException('Invalid PDF manifest.');
            $article = Article::where('article_number','SJC-LEGACY-'.$row['id'])->first();
            if (!$article) continue;
            if ($article->pdf_path) continue; // Preserve subsequent editorial replacements.
            $file = $directory.'/pdfs/'.$row['id'].'.pdf';
            if (!is_file($file) || !hash_equals($row['sha256'], hash_file('sha256',$file)) || file_get_contents($file,false,null,0,5)!=='%PDF-') throw new \RuntimeException('PDF validation failed.');
            $plan[] = [$article,$file,$row];
        }
        $this->info(count($plan).' imported articles ready for local PDFs.');
        if (!$this->option('apply')) return self::SUCCESS;
        foreach ($plan as [$article,$file,$row]) {
            $path = 'articles/sjc/'.$row['id'].'-'.$row['sha256'].'.pdf';
            $stream = fopen($file,'rb');
            try { if (!Storage::disk('local')->put($path,$stream)) throw new \RuntimeException('PDF could not be stored.'); }
            finally { fclose($stream); }
            DB::transaction(function () use($article,$path,$row) {
                $article->update([
                    'pdf_path'=>$path, 'pdf_download_enabled'=>true,
                    'content'=>'<p>Published in the Singapore Journal of Cardiology on '.e($article->published_at->format('F j, Y')).'. The complete published manuscript is available in the reader above and as a PDF download.</p>',
                    'excerpt'=>'Read the complete published article, including its figures, tables and references.',
                ]);
                Setting::put('import.sjc.pdf.'.$row['id'],$row,'imports',false);
            });
        }
        $this->info(count($plan).' PDFs attached.');
        return self::SUCCESS;
    }
}
