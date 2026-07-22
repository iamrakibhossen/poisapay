<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/** A user may only listen on their own private channel (§9.2 authz). */
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});

Broadcast::channel('App.Models.User.{id}', function (User $user, string $id) {
    return $user->id === $id;
});
