<?php

namespace App\Http\Requests\Tasks;

use App\Solution;
use Illuminate\Foundation\Http\FormRequest;

class EstimateTaskSolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $solution = Solution::find($this->route('id'));
        $maxMark = $solution ? $solution->task->max_mark : 1000;

        return [
            'mark' => 'required|integer|min:0|max:' . $maxMark,
        ];
    }
}
