<?php

namespace App\Http\Requests\Semrush;

use Illuminate\Foundation\Http\FormRequest;

abstract class SemrushDomainRequest extends FormRequest
{
    /**
     * Normalize the domain before validation: lowercase it, strip any scheme,
     * port, path, or query string, and remove trailing dots/slashes.
     */
    protected function prepareForValidation(): void
    {
        $domain = $this->input('domain');

        if (is_string($domain)) {
            $this->merge(['domain' => $this->normalizeDomain($domain)]);
        }
    }

    /**
     * Shared validation rules for any domain-based Semrush report.
     *
     * @return array<string, array<int, string>>
     */
    protected function domainRules(): array
    {
        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/',
            ],
        ];
    }

    /**
     * Normalize a user-provided domain. `https://www.Example.com/path` and
     * `example.com.` both become `example.com`.
     */
    protected function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $domain) ?? $domain;
        $domain = preg_split('~[/?#:]~', $domain)[0] ?? $domain;
        $domain = rtrim($domain, " \t\n\r\0\x0B.");

        return strtolower($domain);
    }
}
