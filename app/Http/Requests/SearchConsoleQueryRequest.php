<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchConsoleQueryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'property' => ['required', 'string', 'max:2048'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'country' => ['nullable', 'string', 'max:100'],
            'device' => ['nullable', 'string', 'max:100'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'query' => ['nullable', 'string', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:25000'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
