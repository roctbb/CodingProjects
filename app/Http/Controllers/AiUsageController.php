<?php

namespace App\Http\Controllers;

use App\Services\Ai\TokenUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AiUsageController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request)
    {
        $periods = ['all' => 'Всё время', '7' => 'Последние 7 дней', '30' => 'Последние 30 дней', '90' => 'Последние 90 дней'];
        $filters = array_filter($request->validate([
            'period' => ['sometimes', Rule::in(array_keys($periods))],
            'purpose' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:200'],
            'user' => ['nullable', 'integer', 'min:0'],
        ]), static fn ($value) => $value !== null);
        $period = $filters['period'] ?? 'all';
        $ledger = DB::table('ai_token_usage');
        $usage = clone $ledger;
        if ($period !== 'all') {
            $usage->where('ai_token_usage.created_at', '>=', today()->subDays((int) $period - 1));
        }
        foreach (['purpose', 'model'] as $field) {
            if (isset($filters[$field])) {
                $usage->where('ai_token_usage.'.$field, $filters[$field]);
            }
        }
        $selectedUser = null;
        if (isset($filters['user'])) {
            $userId = (int) $filters['user'];
            if ($userId === 0) {
                $usage->whereNull('ai_token_usage.user_id');
                $selectedUser = 'Без пользователя';
            } else {
                $usage->where('ai_token_usage.user_id', $userId);
                $selectedUser = DB::table('users')->where('id', $userId)->value('name') ?? 'Пользователь #'.$userId;
            }
        }

        $aggregate = 'COUNT(*) AS calls, COUNT(*) - COUNT(total_tokens) AS calls_without_total,
            SUM(known_tokens) AS tokens, SUM(input_tokens) AS input, SUM(output_tokens) AS output';
        $totals = (clone $usage)->selectRaw($aggregate)->first();
        $breakdowns = [];
        foreach (['purpose', 'model'] as $field) {
            $breakdowns[$field] = (clone $usage)->select($field)->selectRaw($aggregate)->groupBy($field)
                ->orderByRaw('COALESCE(SUM(known_tokens), 0) DESC')->orderBy($field)->get();
        }
        $users = $usage->leftJoin('users', 'users.id', '=', 'ai_token_usage.user_id')
            ->select('ai_token_usage.user_id', 'users.name')->selectRaw($aggregate)
            ->groupBy('ai_token_usage.user_id', 'users.name')
            ->orderByRaw('COALESCE(SUM(known_tokens), 0) DESC')->orderBy('ai_token_usage.user_id')
            ->paginate(25)->appends($filters);

        $purposes = (clone $ledger)->distinct()->orderBy('purpose')->pluck('purpose')
            ->mapWithKeys(fn ($purpose) => [$purpose => TokenUsage::PURPOSES[$purpose] ?? $purpose]);
        $models = $ledger->whereNotNull('model')->distinct()->orderBy('model')->pluck('model');

        return response()->view('settings.ai-usage', compact(
            'filters', 'period', 'periods', 'selectedUser', 'totals', 'breakdowns', 'users', 'purposes', 'models',
        ))->header('Cache-Control', 'no-store, private');
    }
}
