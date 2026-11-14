<?php

namespace App\Queries;

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

    public function paginate(string $search, string $role): LengthAwarePaginator
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

        $users = $this->ordered($query)->with('manual_rank')->paginate(self::PER_PAGE)
            ->appends(array_filter(['q' => $search, 'role' => $role === 'all' ? '' : $role], fn ($value) => $value !== ''));

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

    private function search(Builder $query, string $search): void
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $driver = DB::connection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        $scoreType = $driver === 'mysql' ? 'CHAR' : 'TEXT';
        $xp = "CAST(users.community_score AS {$scoreType})";
        $xp = $driver === 'mysql' ? "CONCAT({$xp}, ' XP')" : "({$xp} || ' XP')";

        // Manual ranks take priority; otherwise use the same ordered intervals as the cards.
        $rankName = $this->ranks->isEmpty() ? '' : 'CASE';
        $rankBindings = [];
        foreach ($this->ranks as $rank) {
            $rankName .= ' WHEN users.rank_id = ? THEN ?';
            array_push($rankBindings, $rank->id, $rank->name);
        }
        foreach ($this->ranks as $rank) {
            $rankName .= ' WHEN users.community_score >= ? AND users.community_score < ? THEN ?';
            array_push($rankBindings, $rank->from, $rank->to, $rank->name);
        }
        $rankName .= $this->ranks->isEmpty() ? '?' : ' ELSE ? END';
        $rankBindings[] = $this->ranks->first()?->name ?? '';

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
