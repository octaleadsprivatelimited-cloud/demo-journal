<?php
namespace App\Console\Commands;

use App\Models\{Article,Author,Category,EditorialMember,Setting,User};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportSjcMetadata extends Command
{
    protected $signature='journal:import-sjc-metadata {file} {--apply : Save the reviewed metadata locally}';
    protected $description='Import public SJC article metadata as drafts and editorial profiles without creating login accounts.';

    public function handle(): int
    {
        if (!app()->environment(['local','testing'])) { $this->error('This import is restricted to local or testing environments.'); return self::FAILURE; }
        $data=json_decode(file_get_contents($this->argument('file')),true,512,JSON_THROW_ON_ERROR);
        if (($data['source']??null)!=='https://sjcjournal.com' || !empty($data['failures'])) { $this->error('The source manifest is invalid or has unresolved fetch failures.');return self::FAILURE; }
        $this->info(count($data['articles']).' article records; '.count($data['editorial_members']).' editorial profiles. Article bodies and PDFs are not included.');
        if (!$this->option('apply')) { $this->info('Preview only. Use --apply to import.');return self::SUCCESS; }
        $creator=User::whereHas('roles',fn($q)=>$q->whereIn('slug',['admin','super-admin']))->orderBy('id')->first() ?? User::where('is_local_admin_bypass',true)->firstOrFail();
        $counts=['articles'=>0,'authors'=>0,'editorial_members'=>0,'existing_articles'=>0];
        DB::transaction(function () use($data,$creator,&$counts) {
            $category=Category::firstOrCreate(['slug'=>'cardiology'],['name'=>'Cardiology','is_active'=>true]);
            foreach ($data['articles'] as $row) {
                if (!preg_match('~^https://sjcjournal\.com/journals/(\d+)/[^/]+/abstract$~',$row['source_url'],$matches)) throw new \RuntimeException('Invalid article source URL.');
                $key='SJC-LEGACY-'.$matches[1];
                $existing=Article::withTrashed()->where('article_number',$key)->when($row['doi']??null,fn($q,$doi)=>$q->orWhere('doi',$doi))->exists();
                if ($existing) { $counts['existing_articles']++; continue; }
                $body='<p>Legacy article metadata imported from the original SJC website. Full manuscript content and PDF await source-file migration.</p><p><a href="'.e($row['source_url']).'">View original article</a></p>';
                $article=Article::create(['created_by_id'=>$creator->id,'category_id'=>$category->id,'article_number'=>$key,'title'=>$row['title'],'doi'=>$row['doi']?:null,'status'=>'draft','content'=>$body,'published_at'=>$row['published_date'] ? \Carbon\Carbon::parse($row['published_date']) : null,'pdf_download_enabled'=>false,'is_homepage_latest'=>false]);
                $counts['articles']++;
                foreach ($row['authors'] as $position=>$name) {
                    $slug=Str::slug($name);
                    $author=Author::withTrashed()->where('slug',$slug)->first();
                    if ($author?->trashed()) continue;
                    if (!$author) { $author=Author::create(['name'=>$name,'slug'=>$slug,'is_active'=>true,'is_verified'=>false]);$counts['authors']++; }
                    $article->authors()->syncWithoutDetaching([$author->id=>['sort_order'=>$position,'is_corresponding'=>false]]);
                }
                Setting::put('import.sjc.article.'.$matches[1],$row,'imports',false);
            }
            foreach($data['editorial_members'] as $position=>$member) {
                $model=EditorialMember::firstOrCreate(['name'=>$member['name'],'role'=>$member['role']],['group'=>'editorial_board','institution'=>$member['institution'],'sort_order'=>$position,'is_active'=>true]);
                if ($model->wasRecentlyCreated) $counts['editorial_members']++;
            }
            Setting::put('import.sjc.summary',['imported_at'=>now()->toIso8601String(),'counts'=>$counts,'reviewer_note'=>$data['reviewer_note']],'imports',false);
        });
        $this->line(json_encode($counts,JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
