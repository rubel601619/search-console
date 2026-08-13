<?php

namespace App\Http\Controllers;

use App\Exceptions\SearchConsoleException;
use App\Http\Requests\SearchConsoleQueryRequest;
use App\Models\User;
use App\Services\SearchConsoleService;
use App\Support\SearchConsoleQuery;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchConsoleController extends Controller
{
    public function __construct(
        private readonly SearchConsoleService $searchConsoleService,
    ) {}

    public function properties(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->searchConsoleService->getProperties($this->user($request)));
    }

    public function analytics(SearchConsoleQueryRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->searchConsoleService->getAnalytics(
            $this->user($request),
            SearchConsoleQuery::fromRequest($request),
        ));
    }

    public function queries(SearchConsoleQueryRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->searchConsoleService->getTopQueries(
            $this->user($request),
            SearchConsoleQuery::fromRequest($request),
        ));
    }

    public function pages(SearchConsoleQueryRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->searchConsoleService->getTopPages(
            $this->user($request),
            SearchConsoleQuery::fromRequest($request),
        ));
    }

    public function countries(SearchConsoleQueryRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->searchConsoleService->getCountries(
            $this->user($request),
            SearchConsoleQuery::fromRequest($request),
        ));
    }

    public function devices(SearchConsoleQueryRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->searchConsoleService->getDevices(
            $this->user($request),
            SearchConsoleQuery::fromRequest($request),
        ));
    }

    public function searchAppearance(SearchConsoleQueryRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->searchConsoleService->getSearchAppearance(
            $this->user($request),
            SearchConsoleQuery::fromRequest($request),
        ));
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new SearchConsoleException(
                'You must be signed in to view Search Console data.',
                'not_connected',
                401,
            );
        }

        return $user;
    }

    protected function respond(Closure $callback): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()]);
        } catch (SearchConsoleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode(),
                'retryable' => $e->retryable(),
            ], $e->statusCode());
        }
    }
}
