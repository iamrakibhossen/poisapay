<?php

declare(strict_types=1);

/*
 * Google reCAPTCHA. Keys, provider, score and per-feature toggles are ALL stored
 * in Admin Settings (never here) — this file only declares the protectable feature
 * registry + transport defaults. Add a new protected feature with ONE entry below;
 * it then appears as an admin toggle and can be enforced via <x-captcha>, the
 * CaptchaResponse rule, or the `captcha:{feature}` middleware.
 */
return [
    // Google's server-side verification endpoint + transport safety limits.
    'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
    'timeout' => (int) env('CAPTCHA_TIMEOUT', 5),

    // Verifications per IP per minute (abuse guard on the verify path itself).
    'rate_limit' => (int) env('CAPTCHA_RATE_LIMIT', 60),

    // A verified token is single-use; remember it this long to block replays.
    'replay_ttl' => (int) env('CAPTCHA_REPLAY_TTL', 180),

    /*
     * Protectable features, grouped for the admin UI. key => human label.
     * The key is what you pass to <x-captcha feature="...">, Captcha::rule('...'),
     * or ->middleware('captcha:...'). Adding a row here is the ONLY change needed
     * to expose a new toggle.
     */
    'features' => [
        'Authentication' => [
            'login' => 'Login',
            'register' => 'Register',
            'forgot_password' => 'Forgot Password',
            'reset_password' => 'Reset Password',
            'verify_email_resend' => 'Email Verification Resend',
            'change_email' => 'Change Email',
            'change_password' => 'Change Password',
        ],
        'PoisaPay' => [
            'deposit' => 'Deposit',
            'withdraw' => 'Withdraw',
            'p2p_create_order' => 'P2P Create Order',
            'p2p_buy' => 'P2P Buy',
            'p2p_sell' => 'P2P Sell',
            'p2p_chat_send' => 'P2P Chat Send',
            'dispute_create' => 'Dispute Create',
        ],
        'Shop' => [
            'shop_checkout' => 'Checkout',
            'shop_contact' => 'Contact Form',
            'shop_review' => 'Product Review',
            'shop_coupon' => 'Coupon Apply',
        ],
        'Merchant' => [
            'merchant_register' => 'Merchant Registration',
            'merchant_login' => 'Merchant Login',
        ],
        'Forms' => [
            'contact_us' => 'Contact Us',
            'support_ticket' => 'Support Ticket',
            'feedback' => 'Feedback',
            'newsletter' => 'Newsletter Subscribe',
        ],
        'API' => [
            'api_public' => 'Public API',
            'api_auth' => 'Authentication API',
        ],
        'Admin' => [
            'admin_login' => 'Admin Login',
        ],
    ],
];
