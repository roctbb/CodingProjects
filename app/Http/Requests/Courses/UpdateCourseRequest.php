<?php

namespace App\Http\Requests\Courses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $courseId = $this->route('id');

        return [
            'name' => 'required|string',
            'description' => 'required|string',
            'invite' => [
                'required',
                'string',
                Rule::unique('courses', 'invite')->ignore($courseId),
                'unique:providers,invite',
            ],
        ];
    }
}
