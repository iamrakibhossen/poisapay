<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP-based two-factor authentication (§8.2). Secrets and recovery codes are
 * stored encrypted at rest; enable() provisions but does not confirm — the user
 * must prove possession via confirm() before 2FA becomes active.
 */
class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Provision 2FA for a user: persist an encrypted secret + recovery codes and
     * return the plaintext artefacts needed to render the enrolment screen.
     *
     * @return array{secret: string, qr_svg: string, otpauth_url: string, recovery_codes: array<int, string>}
     */
    public function enable(Model $user): array
    {
        $secret = $this->generateSecret();

        $recoveryCodes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => Str::upper(Str::random(10)))
            ->all();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => null,
        ])->save();

        $company = (string) config('app.name');

        return [
            'secret' => $secret,
            'qr_svg' => $this->qrCodeSvg($company, $user->email, $secret),
            'otpauth_url' => $this->otpauthUrl($company, $user->email, $secret),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /** Confirm enrolment: mark 2FA active once the user proves a valid TOTP code. */
    public function confirm(Model $user, string $code): bool
    {
        if (! $this->verifyTotp($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return true;
    }

    /** Verify a login challenge: accept either a live TOTP code or a one-time recovery code. */
    public function verify(Model $user, string $code): bool
    {
        if ($this->verifyTotp($user, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    /** Fully disable 2FA by clearing all stored 2FA state. */
    public function disable(Model $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function qrCodeSvg(string $company, string $email, string $secret): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString(
            $this->otpauthUrl($company, $email, $secret),
        );
    }

    private function otpauthUrl(string $company, string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($company, $email, $secret);
    }

    private function verifyTotp(Model $user, string $code): bool
    {
        if (empty($user->two_factor_secret)) {
            return false;
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        return $this->google2fa->verifyKey($secret, $code);
    }

    /** Remove and accept a recovery code; returns false if it is unknown. */
    private function consumeRecoveryCode(Model $user, string $code): bool
    {
        if (empty($user->two_factor_recovery_codes)) {
            return false;
        }

        $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?? [];

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $remaining = array_values(array_filter($codes, fn (string $c): bool => $c !== $code));

        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remaining)),
        ])->save();

        return true;
    }
}
