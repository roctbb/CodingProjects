<?php

namespace App\Queries;

use App\Lesson;
use App\Rank;
use App\Solution;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommunityDirectory
{
    public const PER_PAGE = 20;

    private Collection $ranks;

    public function __construct()
    {
        $this->ranks = Rank::orderBy('from')->orderBy('id')->get();
    }

    public function rankOptions(): Collection
    {
        return $this->ranks->reverse()->pluck('name', 'id');
    }

    public function paginate(string $search, string $role, ?int $rankId = null): LengthAwarePaginator
    {
        $query = $this->visibleUsers();
        if ($role === 'teacher') {
            $this->teachers($query);
        } elseif ($role === 'student') {
            $this->students($query);
        }
        if ($search !== '') {
            $this->search($query, $search);
        }
        if ($rankId !== null) {
            [$rank, $values] = $this->rankExpression('id');
            $query->whereRaw("CAST(($rank) AS DECIMAL(20,0)) = ?", [...$values, $rankId]);
        }

        $stickers = Lesson::query()->whereHas('steps.tasks')
            ->join('lesson_student_stats as progress', 'lessons.id', '=', 'progress.lesson_id')
            ->join('stickers', 'lessons.sticker', '=', 'stickers.image')
            ->where('progress.percent', '>', 90)->groupBy('progress.student_id')
            ->selectRaw('progress.student_id, COUNT(DISTINCT stickers.id) AS earned');
        [$rankOrder, $bindings] = $this->rankExpression('from');
        $query->leftJoinSub($stickers, 'albums', fn ($join) => $join->on('albums.student_id', '=', 'users.id'))
            ->selectRaw('COALESCE(albums.earned, 0) AS community_stickers')
            ->orderByRaw("CAST(($rankOrder) AS DECIMAL(20,0)) DESC", $bindings)->orderByDesc('community_stickers');
        $users = $this->ordered($query)->with(['manual_rank', 'roomCharacterGeneration'])->paginate(self::PER_PAGE)
            ->appends(array_filter(['q' => $search, 'role' => $role === 'all' ? '' : $role, 'rank'=>$rankId], fn ($value) => $value !== '' && $value !== null));

        $users->getCollection()->each(function (User $user) {
            $score = (int) $user->community_score;
            $rank = $user->manual_rank ?: $this->ranks->first(fn ($rank) => $rank->from <= $score && $rank->to > $score);
            $user->setComputedScore($score)->setComputedRank($rank ?: $this->ranks->first());
        });

        return $users;
    }

    public function leaderIds(): Collection
    {
        $query = $this->visibleUsers();
        $this->students($query);

        return $this->ordered($query)->limit(3)->toBase()->pluck('users.id')->map(fn ($id) => (int) $id)->values();
    }

    private function visibleUsers(): Builder
    {
        // A task contributes its best attempt once, including tasks in completed courses.
        $bestMarks = Solution::query()->select('user_id', 'task_id')
            ->selectRaw('MAX(COALESCE(mark, 0)) AS mark')->groupBy('user_id', 'task_id');
        $scores = DB::query()->fromSub($bestMarks, 'best_marks')->select('user_id')
            ->selectRaw('SUM(mark) AS score')->groupBy('user_id');
        $manualTo = DB::connection()->getQueryGrammar()->wrap('community_manual_rank.to');

        $scoredUsers = User::query()->select('users.*')
            ->leftJoinSub($scores, 'community_scores', fn ($join) => $join->on('users.id', '=', 'community_scores.user_id'))
            ->leftJoin('ranks as community_manual_rank', 'users.rank_id', '=', 'community_manual_rank.id')
            ->selectRaw("CASE WHEN community_manual_rank.id IS NOT NULL THEN {$manualTo} - 1 ELSE COALESCE(community_scores.score, 0) END AS community_score");

        // Keep scores available to WHERE and ORDER BY before applying LIMIT/OFFSET.
        return User::query()->fromSub($scoredUsers, 'users')->select('users.*')
            ->where(fn ($query) => $query->where('users.is_hidden', false)->orWhereNull('users.is_hidden'));
    }

    private function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('users.community_score')->orderBy('users.name')->orderBy('users.id');
    }

    private function teachers(Builder $query): void
    {
        $query->where(fn ($query) => $query->where('users.is_teacher', true)->orWhereIn('users.role', ['teacher', 'admin']));
    }

    private function students(Builder $query): void
    {
        $query->where(fn ($query) => $query->where('users.is_teacher', false)->orWhereNull('users.is_teacher'))
            ->where(fn ($query) => $query->whereNotIn('users.role', ['teacher', 'admin'])->orWhereNull('users.role'));
    }

    private function rankExpression(string $attribute): array
    {
        // Manual ranks take priority; otherwise use the same ordered intervals as the cards.
        $expression = $this->ranks->isEmpty() ? '' : 'CASE';
        $bindings = [];
        foreach ($this->ranks as $rank) {
            $expression .= ' WHEN users.rank_id = ? THEN ?';
            array_push($bindings, $rank->id, $rank->{$attribute});
        }
        foreach ($this->ranks as $rank) {
            $expression .= ' WHEN users.community_score >= ? AND users.community_score < ? THEN ?';
            array_push($bindings, $rank->from, $rank->to, $rank->{$attribute});
        }
        $expression .= $this->ranks->isEmpty() ? '?' : ' ELSE ? END';
        $bindings[] = $this->ranks->first()?->{$attribute} ?? ($attribute === 'from' ? 0 : '');
        return [$expression, $bindings];
    }

    private function search(Builder $query, string $search): void
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $driver = DB::connection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        $scoreType = $driver === 'mysql' ? 'CHAR' : 'TEXT';
        $xp = "CAST(users.community_score AS {$scoreType})";
        $xp = $driver === 'mysql' ? "CONCAT({$xp}, ' XP')" : "({$xp} || ' XP')";

        [$rankName, $rankBindings] = $this->rankExpression('name');

        $query->where(function (Builder $matches) use ($search, $pattern, $operator, $xp, $rankName, $rankBindings) {
            $matches->whereRaw("users.name {$operator} ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("({$rankName}) {$operator} ? ESCAPE '!'", [...$rankBindings, $pattern])
                ->orWhereRaw("{$xp} {$operator} ? ESCAPE '!'", [$pattern])
                ->orWhere(function (Builder $titles) use ($pattern, $operator) {
                    $titles->where('users.custom_title_expires_at', '>', now())
                        ->whereRaw("users.custom_title {$operator} ? ESCAPE '!'", [$pattern]);
                });

            $search = str_replace('ё', 'е', mb_strtolower($search));
            if (str_contains('преподаватель', $search)) {
                $matches->orWhere(fn (Builder $teachers) => $this->teachers($teachers));
            }
            if (str_contains('ученик', $search)) {
                $matches->orWhere(fn (Builder $students) => $this->students($students));
            }
            if (str_contains('стажер', $search)) {
                $matches->orWhere('users.is_trainee', true);
            }
        });
    }
}
