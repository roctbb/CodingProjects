<?php
/**
 * Script: scripts/generate_fix_links_sql.php
 * Purpose: Parse dupes.txt and generate SQL to replace references to deleted files
 *          in lesson and task texts with the kept file paths.
 *
 * Usage:
 *   php scripts/generate_fix_links_sql.php
 *
 * Output:
 *   database/scripts/fix_dupe_links.sql
 *
 * Notes:
 * - We replace by filename only (UUID.ext), which covers absolute/relative URLs.
 * - A single transaction is used in the generated SQL.
 * - Tables/columns updated:
 *     tasks.text, tasks.solution,
 *     course_steps.description, course_steps.theory, course_steps.notes,
 *     lessons.description
 */

$projectRoot = dirname(__DIR__);
$dupesFile = $projectRoot . DIRECTORY_SEPARATOR . 'dupes.txt';
$outDir = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'scripts';
$outFile = $outDir . DIRECTORY_SEPARATOR . 'fix_dupe_links.sql';

if (!file_exists($dupesFile)) {
    fwrite(STDERR, "dupes.txt not found at project root: {$dupesFile}\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Failed to create output directory: {$outDir}\n");
    exit(1);
}

$lines = file($dupesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$groups = [];
$current = [
    'keep' => null,
    'deletes' => [],
];
$groupIndex = 0;

$flushGroup = function() use (&$groups, &$current, &$groupIndex) {
    if ($current['keep'] && !empty($current['deletes'])) {
        $groups[] = $current;
        $groupIndex++;
    }
    $current = [ 'keep' => null, 'deletes' => [] ];
};

foreach ($lines as $rawLine) {
    $line = trim($rawLine);
    if ($line === '' ) {
        continue;
    }
    if (mb_strpos($line, 'Группа дубликатов') !== false) {
        // Start of a new group; flush previous
        $flushGroup();
        continue;
    }

    // Lines look like: [KEEP] ./filename.ext or [DELETE] ./filename.ext
    if (preg_match('/^\[(KEEP|DELETE)\]\s+(\S+)/u', $line, $m)) {
        $tag = $m[1];
        $path = $m[2];
        // Normalize and extract filename only
        $path = trim($path);
        // Skip placeholder lines with just [DELETE] and no path captured (shouldn't happen due to regex)
        $filename = basename($path);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            continue;
        }
        if ($tag === 'KEEP') {
            $current['keep'] = $filename;
        } elseif ($tag === 'DELETE') {
            // Avoid accidental duplicate entries
            if (!in_array($filename, $current['deletes'], true)) {
                $current['deletes'][] = $filename;
            }
        }
    }
}
// Flush last group
$flushGroup();

if (empty($groups)) {
    fwrite(STDERR, "No groups with [KEEP] and [DELETE] entries found in dupes.txt.\n");
    exit(1);
}

$tables = [
    array('tasks', 'text'),
    array('tasks', 'solution'),
    array('course_steps', 'description'),
    array('course_steps', 'theory'),
    array('course_steps', 'notes'),
    array('lessons', 'description'),
];

$fh = fopen($outFile, 'w');
if (!$fh) {
    fwrite(STDERR, "Failed to open output file: {$outFile}\n");
    exit(1);
}

$header = "-- Auto-generated SQL to fix links after duplicate file cleanup\n" .
          "-- Generated at: " . date('Y-m-d H:i:s') . "\n" .
          "-- Source: dupes.txt\n" .
          "-- This script replaces occurrences of deleted filenames with the kept filename\n" .
          "-- across lesson and task text columns.\n\n" .
          "START TRANSACTION;\n\n";

fwrite($fh, $header);

$totalPairs = 0;
foreach ($groups as $i => $g) {
    $keep = $g['keep'];
    foreach ($g['deletes'] as $del) {
        if ($del === $keep) continue;
        $totalPairs++;
        fwrite($fh, sprintf("-- Group %d: %s -> %s\n", $i + 1, $del, $keep));
        // Optional pre-checks (commented)
        foreach ($tables as $t) {
            $table = $t[0];
            $col = $t[1];
            $like = addslashes($del);
            fwrite($fh, sprintf("-- SELECT COUNT(*) AS hits FROM %s WHERE %s LIKE '%%%s%%';\n", $table, $col, $like));
        }
        fwrite($fh, "\n");
        // Updates
        foreach ($tables as $t) {
            $table = $t[0];
            $col = $t[1];
            $from = addslashes($del);
            $to = addslashes($keep);
            $sql = sprintf(
                "UPDATE %s SET %s = REPLACE(%s, '%s', '%s') WHERE %s LIKE '%%%s%%';\n",
                $table, $col, $col, $from, $to, $col, $from
            );
            fwrite($fh, $sql);
        }
        fwrite($fh, "\n");
    }
}

fwrite($fh, "COMMIT;\n");

fclose($fh);

fwrite(STDOUT, "Generated {$totalPairs} replace pairs into {$outFile}\n");
