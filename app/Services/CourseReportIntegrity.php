<?php

namespace App\Services;

use App\Solution;

final class CourseReportIntegrity
{
    public function forStudents($students, $lessons)
    {
        $stats = [];

        foreach ($students as $student) {
            $stats[$student->id] = [
                'overall' => $this->emptyGeekPasteIntegrityBucket(),
                'chapters' => [],
                'lessons' => [],
                'tasks' => [],
            ];
        }

        foreach ($lessons as $lesson) {
            $chapterId = $lesson->chapter_id ?: 0;

            foreach ($lesson->steps as $step) {
                foreach ($step->tasks as $task) {
                    foreach ($task->solutions as $solution) {
                        if (!isset($stats[$solution->user_id])) {
                            continue;
                        }

                        if (!isset($stats[$solution->user_id]['chapters'][$chapterId])) {
                            $stats[$solution->user_id]['chapters'][$chapterId] = $this->emptyGeekPasteIntegrityBucket();
                        }

                        if (!isset($stats[$solution->user_id]['lessons'][$lesson->id])) {
                            $stats[$solution->user_id]['lessons'][$lesson->id] = $this->emptyGeekPasteIntegrityBucket();
                        }

                        if (!isset($stats[$solution->user_id]['tasks'][$task->id])) {
                            $stats[$solution->user_id]['tasks'][$task->id] = $this->emptyGeekPasteIntegrityBucket();
                        }

                        $stats[$solution->user_id]['overall'] = $this->addSolutionToGeekPasteIntegrityBucket($stats[$solution->user_id]['overall'], $solution);
                        $stats[$solution->user_id]['chapters'][$chapterId] = $this->addSolutionToGeekPasteIntegrityBucket($stats[$solution->user_id]['chapters'][$chapterId], $solution);
                        $stats[$solution->user_id]['lessons'][$lesson->id] = $this->addSolutionToGeekPasteIntegrityBucket($stats[$solution->user_id]['lessons'][$lesson->id], $solution);
                        $stats[$solution->user_id]['tasks'][$task->id] = $this->addSolutionToGeekPasteIntegrityBucket($stats[$solution->user_id]['tasks'][$task->id], $solution);
                    }
                }
            }
        }

        foreach ($stats as $studentId => $studentStats) {
            $stats[$studentId]['overall'] = $this->finalizeGeekPasteIntegrityBucket($studentStats['overall']);

            foreach ($studentStats['chapters'] as $chapterId => $bucket) {
                $stats[$studentId]['chapters'][$chapterId] = $this->finalizeGeekPasteIntegrityBucket($bucket);
            }

            foreach ($studentStats['lessons'] as $lessonId => $bucket) {
                $stats[$studentId]['lessons'][$lessonId] = $this->finalizeGeekPasteIntegrityBucket($bucket);
            }

            foreach ($studentStats['tasks'] as $taskId => $bucket) {
                $stats[$studentId]['tasks'][$taskId] = $this->finalizeGeekPasteIntegrityBucket($bucket);
            }
        }

        return $stats;
    }

    private function emptyGeekPasteIntegrityBucket()
    {
        return [
            'submissions' => 0,
            'synced' => 0,
            'ai_warnings' => 0,
            'similarity_warnings' => 0,
            'similarity_critical' => 0,
            'similarity_matches_count' => 0,
            'max_llm_probability' => null,
            'max_similarity_percent' => null,
            'has_high_ai_confidence' => false,
            'has_medium_ai_confidence' => false,
            'dismissed' => 0,
            'risk_level' => 'none',
        ];
    }

    private function addSolutionToGeekPasteIntegrityBucket(array $bucket, Solution $solution)
    {
        if (!$solution->hasGeekPasteIntegrityData()) {
            return $bucket;
        }

        $bucket['submissions']++;

        if ($solution->geekpaste_integrity_synced_at || $solution->geekpaste_code_id) {
            $bucket['synced']++;
        }

        if ($solution->geekpaste_integrity_dismissed_at) {
            $bucket['dismissed']++;

            return $bucket;
        }

        if ($solution->geekpaste_ai_warning) {
            $bucket['ai_warnings']++;
        }

        if ($solution->geekpaste_similarity_warning) {
            $bucket['similarity_warnings']++;
        }

        if ($solution->geekpaste_similarity_critical) {
            $bucket['similarity_critical']++;
        }

        $bucket['similarity_matches_count'] += (int) $solution->geekpaste_similarity_matches_count;

        if ($solution->geekpaste_llm_probability !== null) {
            $bucket['max_llm_probability'] = max((int) $solution->geekpaste_llm_probability, (int) ($bucket['max_llm_probability'] ?? 0));
        }

        if ($solution->geekpaste_similarity_max_percent !== null) {
            $bucket['max_similarity_percent'] = max((int) $solution->geekpaste_similarity_max_percent, (int) ($bucket['max_similarity_percent'] ?? 0));
        }

        if ($solution->geekpaste_ai_confidence === 'high') {
            $bucket['has_high_ai_confidence'] = true;
        }

        if ($solution->geekpaste_ai_confidence === 'medium') {
            $bucket['has_medium_ai_confidence'] = true;
        }

        return $bucket;
    }

    private function finalizeGeekPasteIntegrityBucket(array $bucket)
    {
        $maxLlm = $bucket['max_llm_probability'];
        $maxSimilarity = $bucket['max_similarity_percent'];

        if (
            $bucket['similarity_critical'] > 0 ||
            $bucket['has_high_ai_confidence'] ||
            ($maxLlm !== null && $maxLlm >= 80) ||
            ($maxSimilarity !== null && $maxSimilarity >= 95)
        ) {
            $bucket['risk_level'] = 'high';
        } elseif (
            $bucket['ai_warnings'] > 0 ||
            $bucket['similarity_warnings'] > 0 ||
            $bucket['has_medium_ai_confidence'] ||
            ($maxLlm !== null && $maxLlm >= 60) ||
            ($maxSimilarity !== null && $maxSimilarity >= 75)
        ) {
            $bucket['risk_level'] = 'medium';
        } elseif ($bucket['synced'] > 0) {
            $bucket['risk_level'] = 'low';
        }

        return $bucket;
    }

}
