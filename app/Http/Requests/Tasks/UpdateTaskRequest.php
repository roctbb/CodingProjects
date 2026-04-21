<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => 'required|string',
            'name' => 'required|string',
            'price' => 'nullable|numeric|min:0',
            'max_mark' => 'required|integer|min:0|max:1000',
        ];
    }
}
