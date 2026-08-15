<?php

namespace App\Http\Requests\Semrush;

class BacklinksRequest extends SemrushDomainRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->domainRules();
    }
}
