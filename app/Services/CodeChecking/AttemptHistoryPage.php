<?php

namespace App\Services\CodeChecking;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Select the page and its summary from the same database snapshot. */
final class AttemptHistoryPage
{
    private const NATIVE = '/code-submissions/([a-f0-9-]{36})(?:[/?#]|$)';
    private const ARCHIVE = '(?:[?&]|&amp;)id=([A-Za-z0-9_-]+)';

    public function select(int $course, int $task, ?int $author, ?bool $viewed, int $page): array
    {
        $driver = DB::connection()->getDriverName();
        $mysql = in_array($driver, ['mysql', 'mariadb'], true);
        $textType = $mysql ? 'char' : 'text';
        $legacyId = $mysql ? "concat('solution-', e.id)" : "'solution-' || cast(e.id as text)";
        $scope = 'course_id = ? and task_id = ?'.($author === null ? '' : ' and user_id = ?');
        $bindings = $author === null ? [$course, $task] : [$course, $task, $author];
        $args = $bindings;
        $visible = $viewed === null ? '1=1' : ($viewed ? 'viewed = 1' : 'viewed = 0');
        $withBest = $author === null ? '1=0' : 'grade_rank = 1 and xp is not null';
        $events = '';
        $standalone = '';
        if (Schema::hasTable('solutions')) {
            [$native, $archive] = $this->references($driver);
            $optional = fn ($column) => Schema::hasColumn('solutions', $column) ? $column : 'NULL';
            $graded = $optional('evaluated_mark');
            $raw = $optional('raw_mark');
            $paste = $optional('geekpaste_code_id');
            $args = array_merge($args, $bindings);
            $events = ", parsed as (
                select id, user_id, submitted, mark, coalesce($graded, $raw, mark) as evaluation,
                       $native as native_ref, $paste as archive_id, $archive as archive_ref
                from solutions where $scope and submitted is not null
            ), linked as (
                select e.*, coalesce(n.id, g.id, a.id, d.id) as source_id
                from parsed e
                left join sources n on n.id = e.native_ref
                left join sources g on g.legacy_id = e.archive_id
                left join sources a on a.legacy_id = e.archive_ref
                left join sources d on d.solution_id = e.id and d.solution_rank = 1
            )";
            $canonical = 's.legacy_id is null and s.solution_id = e.id';
            $standalone = "select case when $canonical then s.id else $legacyId end as entry_id,
                       e.id as event_id, s.id as source_id, e.user_id,
                       case when $canonical then s.created_at else e.submitted end as event_time,
                       case when s.teacher_viewed then 1 else 0 end as viewed,
                       1 as attempt, e.mark as xp, case when e.mark is not null then e.evaluation end as evaluation,
                       case when $canonical then coalesce(s.state, 'preparing')
                            when e.mark is null then 'awaiting_review' else 'completed' end as state
                from linked e left join sources s on s.id = e.source_id and s.user_id = e.user_id
                union all ";
        }
        $unlinked = $events === '' ? '' : 'where not exists (select 1 from linked e where e.source_id = s.id and e.user_id = s.user_id)';
        $order = 'event_time desc, entry_id desc';
        $sql = "with sources as (
                select cast(s.id as $textType) as id, s.user_id, s.legacy_id, s.solution_id,
                       s.created_at, s.teacher_viewed, r.state, r.points,
                       row_number() over (partition by s.solution_id order by s.id desc) as solution_rank
                from (select * from code_submissions where $scope) s
                left join code_execution_runs r on r.id = s.current_run_id
            ) $events, timeline as (
                $standalone select s.id as entry_id, null as event_id, s.id as source_id, s.user_id,
                       s.created_at as event_time, case when s.teacher_viewed then 1 else 0 end as viewed,
                       case when s.legacy_id is null then 1 else 0 end as attempt, null as xp,
                       case when s.legacy_id is null and s.state = 'completed' then s.points end as evaluation,
                       coalesce(s.state, 'preparing') as state from sources s $unlinked
            ), ranked as (
                select timeline.*, row_number() over (partition by user_id order by
                       case when xp is null then 1 else 0 end, xp desc, $order) as grade_rank from timeline
            ), summary as (
                select count(case when $visible then 1 end) as total, count(case when attempt = 1 then 1 end) as attempts,
                       max(evaluation) as best_evaluation from ranked
            ), page_items as (
                select * from ranked where $visible order by $order limit 20 offset ?
            ), selected as (
                select page_items.*, 1 as on_page from page_items
                union all select ranked.*, 0 as on_page from ranked where $withBest
            )
            select summary.*, (select state from timeline where attempt = 1 order by $order limit 1) as latest_state,
                   selected.* from summary left join selected on 1=1 order by on_page desc, $order";
        $args[] = (max(1, $page) - 1) * 20;
        return DB::select($sql, $args);
    }

    private function references(string $driver): array
    {
        $text = 'substr(solutions.text, 1, 2048)';
        if ($driver === 'sqlite') {
            DB::connection()->getReadPdo()->sqliteCreateFunction('gc_attempt_reference', static function ($text, $native) {
                $pattern = $native ? self::NATIVE : self::ARCHIVE;
                return preg_match('~'.$pattern.'~'.($native ? 'i' : ''), $text ?? '', $match) ? $match[1] : null;
            }, 2);
            return ["gc_attempt_reference($text, 1)", "gc_attempt_reference($text, 0)"];
        }
        if ($driver === 'pgsql') {
            return ["substring($text from '(?i)".self::NATIVE."')", "substring($text from '".self::ARCHIVE."')"];
        }
        return ["regexp_substr($text, '(?i)(?<=/code-submissions/)[a-f0-9-]{36}(?=[/?#]|$)')",
            "substring_index(regexp_substr($text, '(?-i)(?:[?&]|&amp;)id=[A-Za-z0-9_-]+'), '=', -1)"];
    }
}
