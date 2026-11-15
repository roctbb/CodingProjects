<?php
namespace App\Services\CodeChecking;

use App\Solution;

final class ReviewActions
{
    public function forSolution(?Solution $solution): ?array
    {
        if (!$solution || !$solution->isPendingReview()) return null;
        return ['skip_url'=>url('/insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/skip-review'),
            'token'=>$solution->reviewToken()];
    }
}
