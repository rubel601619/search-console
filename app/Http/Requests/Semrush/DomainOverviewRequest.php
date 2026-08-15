<?php

namespace App\Http\Requests\Semrush;

use Illuminate\Validation\Rule;

class DomainOverviewRequest extends SemrushDomainRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->domainRules(),
            'database' => ['required', 'string', Rule::in(config('semrush.databases'))],
            'device' => ['nullable', 'string', 'in:desktop,mobile'],
        ];
    }
}
