<?php

namespace App\Http\Requests\Semrush;

use App\Services\Semrush\SemrushService;
use Illuminate\Validation\Rule;

class CompetitorsRequest extends SemrushDomainRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->domainRules(),
            'database' => ['required', 'string', Rule::in(config('semrush.databases'))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('semrush.max_limit')],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'string', Rule::in(array_keys(SemrushService::COMPETITOR_SORTS))],
        ];
    }
}
