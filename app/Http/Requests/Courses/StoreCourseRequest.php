<?php

namespace App\Http\Requests\Courses;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'program' => 'required|integer|min:-1',
            'description' => 'required|string',
            'image' => 'image|max:1000',
        ];
    }
}
