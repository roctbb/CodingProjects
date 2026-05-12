<?php

namespace App\Console\Commands;

use App\Services\GeekPasteClient;
use App\Solution;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncGeekPasteIntegrity extends Command
{
    protected $signature = 'geekpaste:sync-integrity
        {course_id? : Limit sync to one course}
        {--student= : Limit sync to one student id}
        {--task= : Limit sync to one task id}
        {--since= : Only solutions submitted after this date/datetime}
        {--force : Refresh rows that already have synced integrity data}
        {--dry-run : Show planned updates without saving}';

    protected $description = 'Fetch AI/LLM and similarity metadata for historical GeekPaste submissions';

    protected GeekPasteClient $geekPaste;

    public function __construct(GeekPasteClient $geekPaste)
    {
        parent::__construct();
        $this->geekPaste = $geekPaste;
    }

    public function handle()
    {
        $query = Solution::query()
            ->whereNotNull('task_id')
            ->whereNotNull('course_id')
            ->whereNotNull('user_id')
            ->whereNotNull('text')
            ->whereHas('task', function ($query) {
                $query->where('is_code', true);
            });

        if ($this->argument('course_id')) {
            $query->where('course_id', (int) $this->argument('course_id'));
        }

        if ($this->option('student')) {
            $query->where('user_id', (int) $this->option('student'));
        }

        if ($this->option('task')) {
            $query->where('task_id', (int) $this->option('task'));
        }

        if ($this->option('since')) {
            $query->where('submitted', '>=', Carbon::parse($this->option('since')));
        }

        if (!$this->option('force')) {
            $query->whereNull('geekpaste_integrity_synced_at');
        }

        $total = $query->count();
        $this->info("GeekPaste integrity sync candidates: {$total}");

        $bar = $this->output->createProgressBar($total);
        $updated = 0;
        $missing = 0;

        $query->chunkById(100, function ($solutions) use (&$updated, &$missing, $bar) {
            foreach ($solutions as $solution) {
                $payload = $this->fetchGeekPastePayload($solution);

                if (!$payload) {
                    $missing++;
                    $bar->advance();
                    continue;
                }

                $changes = $this->applyPayload($solution, $payload);

                if (!empty($changes)) {
                    $updated++;
                    if ($this->option('dry-run')) {
                        $this->line('');
                        $this->line("Solution #{$solution->id}: " . json_encode($changes, JSON_UNESCAPED_UNICODE));
                    } else {
                        $solution->save();
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->line('');
        $this->info(($this->option('dry-run') ? 'Would update' : 'Updated') . ": {$updated}; missing in GeekPaste: {$missing}");

        return 0;
    }

    protected function fetchGeekPastePayload(Solution $solution): ?array
    {
        $codeId = $solution->geekpaste_code_id ?: $this->extractGeekPasteCodeId($solution->text);
        if ($codeId) {
            $payload = $this->geekPaste->solution($codeId);
            if (is_array($payload) && $this->matchesSolution($payload, $solution)) {
                return $payload;
            }
        }

        $payload = $this->geekPaste->studentSolutions((int) $solution->user_id, (int) $solution->task_id, 100, null, null);
        if (!is_array($payload)) {
            return null;
        }

        return $this->pickClosestSolution($payload['solutions'] ?? [], $solution);
    }

    protected function pickClosestSolution(array $items, Solution $solution): ?array
    {
        $matches = collect($items)
            ->filter(function ($item) use ($solution) {
                return is_array($item) && $this->matchesSolution($item, $solution);
            })
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        if (!$solution->submitted) {
            return $matches->first();
        }

        $submittedTs = $solution->submitted->getTimestamp();

        return $matches->sortBy(function ($item) use ($submittedTs) {
            if (empty($item['created_at'])) {
                return PHP_INT_MAX;
            }

            return abs(Carbon::parse($item['created_at'])->getTimestamp() - $submittedTs);
        })->first();
    }

    protected function matchesSolution(array $item, Solution $solution): bool
    {
        if (!empty($item['user_id']) && (int) $item['user_id'] !== (int) $solution->user_id) {
            return false;
        }

        if (!empty($item['task_id']) && (int) $item['task_id'] !== (int) $solution->task_id) {
            return false;
        }

        if (!empty($item['course_id']) && (int) $item['course_id'] !== (int) $solution->course_id) {
            return false;
        }

        return true;
    }

    protected function applyPayload(Solution $solution, array $payload): array
    {
        $integrity = $payload['academic_integrity'] ?? [];
        $ai = is_array($integrity) ? ($integrity['ai'] ?? []) : [];
        $similarity = is_array($integrity) ? ($integrity['similarity'] ?? []) : [];

        $values = [
            'geekpaste_code_id' => $payload['id'] ?? $solution->geekpaste_code_id,
            'geekpaste_ai_warning' => (bool) ($ai['warning'] ?? ($payload['has_ai_warning'] ?? false)),
            'geekpaste_ai_confidence' => $ai['confidence'] ?? ($payload['ai_confidence'] ?? null),
            'geekpaste_ai_reasons' => $ai['reasons'] ?? ($payload['ai_warning_reasons'] ?? null),
            'geekpaste_llm_probability' => $this->normalizePercent($ai['llm_probability'] ?? ($payload['gpt_llm_probability'] ?? null)),
            'geekpaste_similarity_checked' => (bool) ($similarity['checked'] ?? ($payload['similarity_checked'] ?? false)),
            'geekpaste_similarity_warning' => (bool) ($similarity['warning'] ?? ($payload['has_similarity_warning'] ?? false)),
            'geekpaste_similarity_critical' => (bool) ($similarity['critical'] ?? ($payload['has_critical_similarity_warning'] ?? false)),
            'geekpaste_similarity_max_percent' => $this->normalizePercent($similarity['max_percent'] ?? ($payload['similarity_max_percent'] ?? null)),
            'geekpaste_similarity_matches_count' => max(0, (int) ($similarity['matches_count'] ?? ($payload['similarity_matches_count'] ?? 0))),
            'geekpaste_integrity_synced_at' => Carbon::now(),
        ];

        $changes = [];
        foreach ($values as $field => $value) {
            if ($solution->{$field} != $value) {
                $changes[$field] = $value;
                $solution->{$field} = $value;
            }
        }

        return $changes;
    }

    protected function normalizePercent($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(100, (int) $value));
    }

    protected function extractGeekPasteCodeId($text): ?string
    {
        $decoded = html_entity_decode((string) $text);

        if (preg_match('/(?:\\?|&|&amp;)id=([A-Za-z0-9_-]+)/', $decoded, $matches)) {
            return $matches[1];
        }

        if (preg_match('#paste\.geekclass\.ru/(?:raw/|view/)?([A-Za-z0-9_-]+)#', $decoded, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
