<?php
namespace App\Providers;

use App\MaterialReview\ContentObserver;
use Illuminate\Support\ServiceProvider;

class MaterialHistoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([\App\Lesson::class,\App\ProgramStep::class,\App\Task::class] as $model) $model::observe(ContentObserver::class);
        \App\Solution::saving(function ($m) {
            ContentObserver::freeze($m);
            if ($m->material_score_cap!==null && $m->mark!==null) $m->mark=min((int)$m->mark,(int)$m->material_score_cap);
        });
        \App\CodeSubmission::creating(fn ($m)=>ContentObserver::freeze($m));
        $this->loadRoutesFrom(base_path('routes/material-history.php'));
    }
}
