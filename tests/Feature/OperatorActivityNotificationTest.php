<?php

declare(strict_types=1);

use App\Events\DepositCredited;
use App\Events\UserRegistered;
use App\Models\Admin;
use App\Models\Deposit;
use App\Models\User;
use App\Notifications\OperatorNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->admin = Admin::create(['name' => 'Op', 'email' => 'ops@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->user = User::factory()->create(['name' => 'Rahim']);
});

it('notifies operators when a new user registers', function () {
    Notification::fake();

    UserRegistered::dispatch($this->user->id);

    Notification::assertSentTo($this->admin, OperatorNotification::class, fn ($n) => $n->title === 'New registration');
});

it('notifies operators when a deposit is credited', function () {
    $deposit = Deposit::create([
        'user_id' => $this->user->id, 'asset_id' => $this->usdt->id, 'source' => 'manual',
        'amount' => '5000000', 'confirmations' => 0, 'required_confirmations' => 0, 'status' => 'detected',
    ]);
    Notification::fake();

    DepositCredited::dispatch($deposit->id);

    Notification::assertSentTo($this->admin, OperatorNotification::class, fn ($n) => $n->title === 'Deposit credited');
});
