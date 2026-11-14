<?php
namespace App\Services\CodeChecking;

/** Submission formats configured by Task.lang in the original task editor. */
class LegacySubmissionFormat
{
    private const CODE = [
        'python' => ['py'], 'cpp' => ['cpp', 'cc', 'cxx', 'c'], 'java' => ['java'], 'cs' => ['cs'],
        'html' => ['html', 'htm'], 'css' => ['css'], 'js' => ['js', 'mjs', 'cjs'], 'json' => ['json'],
        'xml' => ['xml'], 'swift' => ['swift'], 'php' => ['php'], 'sql' => ['sql'],
    ];

    public static function settings(mixed $legacyLanguage): ?array
    {
        if (!is_string($legacyLanguage)) return null;
        if (isset(self::CODE[$legacyLanguage])) {
            $extensions = self::CODE[$legacyLanguage];
            $value = ['language' => $legacyLanguage, 'allowed_languages' => [$legacyLanguage],
                'submission_modes' => ['code'], 'allowed_extensions' => $extensions, 'max_files' => 1];
            // Non-Python/C++ editors must not invent a .py/.txt filename.
            if (!in_array($legacyLanguage, ['python', 'cpp'], true)) $value['entrypoint'] = 'solution.'.$extensions[0];
            return $value;
        }
        if ($legacyLanguage === 'github') {
            return ['language' => 'text', 'allowed_languages' => ['text'], 'submission_modes' => ['repository'],
                'allowed_extensions' => [], 'max_files' => 100]; // Captured repository files, not URLs.
        }
        $extensions = match ($legacyLanguage) {
            'zip' => ['zip'], 'ipynb' => ['ipynb'], 'xlsx' => ['xlsx'],
            'image' => ['png', 'jpg', 'jpeg', 'gif', 'webp'], default => null,
        };
        if ($extensions === null) return null;
        $language = $legacyLanguage === 'ipynb' ? 'python' : 'text';
        return ['language' => $language, 'allowed_languages' => [$language], 'submission_modes' => ['files'],
            'allowed_extensions' => $extensions, 'max_files' => 1];
    }
}
