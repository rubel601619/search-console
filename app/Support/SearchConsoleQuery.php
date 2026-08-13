<?php

namespace App\Support;

use App\Http\Requests\SearchConsoleQueryRequest;
use Illuminate\Support\Carbon;

class SearchConsoleQuery
{
    public function __construct(
        public readonly string $property,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?string $country = null,
        public readonly ?string $device = null,
        public readonly ?string $page = null,
        public readonly ?string $query = null,
        public readonly int $rowLimit = 25,
        public readonly int $startRow = 0,
    ) {}

    public static function fromRequest(SearchConsoleQueryRequest $request): self
    {
        $endDate = $request->date('end_date')?->toDateString() ?? now()->toDateString();
        $startDate = $request->date('start_date')?->toDateString() ?? now()->subDays(28)->toDateString();

        $rowLimit = (int) $request->input('per_page', 25);
        $page = (int) $request->input('page', 1);

        return new self(
            property: (string) $request->string('property'),
            startDate: $startDate,
            endDate: $endDate,
            country: $request->filled('country') ? (string) $request->string('country') : null,
            device: $request->filled('device') ? (string) $request->string('device') : null,
            page: $request->filled('page_url') ? (string) $request->string('page_url') : null,
            query: $request->filled('query') ? (string) $request->string('query') : null,
            rowLimit: $rowLimit,
            startRow: ($page - 1) * $rowLimit,
        );
    }

    public function daysInRange(): int
    {
        return (int) Carbon::parse($this->endDate)->diffInDays(Carbon::parse($this->startDate), true) + 1;
    }

    public function withRowLimit(int $rowLimit): self
    {
        return new self(
            property: $this->property,
            startDate: $this->startDate,
            endDate: $this->endDate,
            country: $this->country,
            device: $this->device,
            page: $this->page,
            query: $this->query,
            rowLimit: $rowLimit,
            startRow: $this->startRow,
        );
    }

    public function previousPeriod(): self
    {
        $days = $this->daysInRange();

        return new self(
            property: $this->property,
            startDate: Carbon::parse($this->startDate)->subDays($days)->toDateString(),
            endDate: Carbon::parse($this->startDate)->subDay()->toDateString(),
            country: $this->country,
            device: $this->device,
            page: $this->page,
            query: $this->query,
            rowLimit: $this->rowLimit,
            startRow: $this->startRow,
        );
    }
}
