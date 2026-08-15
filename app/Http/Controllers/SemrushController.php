<?php

namespace App\Http\Controllers;

use App\Exceptions\SemrushException;
use App\Http\Requests\Semrush\BacklinksRequest;
use App\Http\Requests\Semrush\CompetitorsRequest;
use App\Http\Requests\Semrush\DomainOverviewRequest;
use App\Http\Requests\Semrush\KeywordOverviewRequest;
use App\Http\Requests\Semrush\OrganicKeywordsRequest;
use App\Services\Semrush\SemrushService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SemrushController extends Controller
{
    public function __construct(
        private readonly SemrushService $semrushService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Semrush/Index', [
            'configured' => filled(config('semrush.api_key')),
            'databases' => config('semrush.databases'),
        ]);
    }

    public function domainOverview(DomainOverviewRequest $request): JsonResponse
    {
        return $this->respond(
            fn () => $this->semrushService->getDomainOverview(
                $request->validated('domain'),
                $request->validated('database'),
                $request->validated('device', 'desktop'),
            ),
            $this->context($request, ['domain', 'database', 'device']),
        );
    }

    public function organicKeywords(OrganicKeywordsRequest $request): JsonResponse
    {
        return $this->respond(
            fn () => $this->semrushService->getOrganicKeywords(
                $request->validated('domain'),
                $request->validated('database'),
                $request->validated('device', 'desktop'),
                (int) $request->validated('limit', config('semrush.default_limit')),
                (int) $request->validated('offset', 0),
                $request->validated('sort'),
            ),
            $this->context($request, ['domain', 'database', 'device', 'limit', 'offset', 'sort']),
        );
    }

    public function competitors(CompetitorsRequest $request): JsonResponse
    {
        return $this->respond(
            fn () => $this->semrushService->getCompetitors(
                $request->validated('domain'),
                $request->validated('database'),
                (int) $request->validated('limit', config('semrush.default_limit')),
                (int) $request->validated('offset', 0),
                $request->validated('sort'),
            ),
            $this->context($request, ['domain', 'database', 'limit', 'offset', 'sort']),
        );
    }

    public function backlinks(BacklinksRequest $request): JsonResponse
    {
        return $this->respond(
            fn () => $this->semrushService->getBacklinkOverview($request->validated('domain')),
            $this->context($request, ['domain']),
        );
    }

    public function keywordOverview(KeywordOverviewRequest $request): JsonResponse
    {
        return $this->respond(
            fn () => $this->semrushService->getKeywordOverview(
                $request->validated('keyword'),
                $request->validated('database'),
                $request->validated('device', 'desktop'),
            ),
            $this->context($request, ['keyword', 'database', 'device']),
        );
    }

    public function apiBalance(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->semrushService->getApiBalance());
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function context(FormRequest $request, array $keys): array
    {
        return array_combine(
            $keys,
            array_map(fn (string $key) => $request->validated($key), $keys),
        ) ?: [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function respond(Closure $callback, array $meta = []): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => '',
                'data' => $callback(),
                'meta' => $meta,
            ]);
        } catch (SemrushException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'meta' => [
                    'code' => $e->errorCode(),
                    'retryable' => $e->retryable(),
                ],
            ], $e->statusCode());
        }
    }
}
