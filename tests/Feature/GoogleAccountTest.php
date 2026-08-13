<?php

use App\Models\GoogleAccount;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

test('a user has one google account', function () {
    $user = User::factory()->create();

    $account = GoogleAccount::factory()->for($user)->create();

    expect($user->googleAccount->id)->toBe($account->id);
});

test('google account tokens are encrypted at rest', function () {
    GoogleAccount::factory()->create([
        'access_token' => 'secret-access-token',
        'refresh_token' => 'secret-refresh-token',
    ]);

    $this->assertDatabaseCount('google_accounts', 1)
        ->assertDatabaseMissing('google_accounts', ['access_token' => 'secret-access-token'])
        ->assertDatabaseMissing('google_accounts', ['refresh_token' => 'secret-refresh-token']);
});

test('google account scopes and expiry are cast to their native types', function () {
    $account = GoogleAccount::factory()->create();

    expect($account->scopes)->toBeArray()
        ->and($account->expires_at)->toBeInstanceOf(CarbonInterface::class);
});

test('a google account is deleted when its user is deleted', function () {
    $user = User::factory()->create();

    GoogleAccount::factory()->for($user)->create();

    $user->delete();

    expect(GoogleAccount::query()->count())->toBe(0);
});

test('a user cannot store more than one google account', function () {
    $user = User::factory()->create();

    GoogleAccount::factory()->for($user)->create();

    $this->expectException(QueryException::class);

    GoogleAccount::factory()->for($user)->create();
});
