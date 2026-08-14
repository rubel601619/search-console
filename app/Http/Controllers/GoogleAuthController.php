<?php

namespace App\Http\Controllers;

use App\Services\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $googleAuthService,
    ) {}

    /**
     * Redirect the user to Google to authorize the application.
     */
    public function connect(): RedirectResponse
    {
        return $this->googleAuthService->redirect();
    }

    /**
     * Handle the OAuth callback from Google.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Google authorization was not completed.'),
            ]);

            return redirect()->route('search-console');
        }

        try {
            $this->googleAuthService->handleCallback();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Google account connected successfully.'),
            ]);
        } catch (Throwable $e) {
            report($e);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to connect your Google account. Please try again.'),
            ]);
        }

        return redirect()->route('search-console');
    }

    /**
     * Disconnect the user's google account.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $this->googleAuthService->disconnect($request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Google account disconnected.'),
        ]);

        return redirect()->back();
    }
}
