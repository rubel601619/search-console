<?php

namespace App\Http\Requests\Semrush;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KeywordOverviewRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'max:200'],
            'database' => ['required', 'string', Rule::in(config('semrush.databases'))],
            'device' => ['nullable', 'string', 'in:desktop,mobile'],
        ];
    }
}
