<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Password-reset email for operators — identical to the framework notification
 * but the link points at the ADMIN reset page (its own `admins` broker), not the
 * consumer one.
 */
class AdminResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
