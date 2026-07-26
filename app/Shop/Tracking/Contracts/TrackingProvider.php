<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Contracts;

/**
 * One tracking/pixel integration (Meta, TikTok, GA4, GTM, …). Every provider is a
 * self-contained adapter: it declares its config fields (which drive both the
 * builder UI and server-side validation) and emits a browser snippet that
 * registers an `{ init, fire }` tracker into the shared runtime. Because the
 * runtime only ever calls `init()`/`fire(type, payload)`, adding a new network
 * (Snapchat, Pinterest, Clarity, …) means writing ONE new adapter — nothing else
 * in the system changes.
 */
interface TrackingProvider
{
    /** Stable machine key (meta|tiktok|ga4|gtm) — the config bucket + JS tracker key. */
    public function key(): string;

    /** Human label for the settings UI. */
    public function label(): string;

    /** Official implementation docs, shown next to the section. */
    public function docsUrl(): string;

    /**
     * Config fields — the single source of truth for the settings form AND
     * validation. The FIRST field is the provider's primary id (used to decide
     * whether it's configured). `pattern` is a bare regex (no delimiters) reused
     * by both Laravel validation and client-side checks.
     *
     * @return list<array{key: string, label: string, required: bool, pattern: ?string, hint: ?string, secret: bool}>
     */
    public function fields(): array;

    /**
     * Enabled by the merchant AND its primary id is present + well-formed.
     *
     * @param  array<string, mixed>  $config
     */
    public function isConfigured(array $config): bool;

    /**
     * Browser snippet for <head>: installs the pixel loader inside an `init()` and
     * registers `{ key, init, fire(type, payload) }` on `window.__ppTrackers`. The
     * shared runtime calls `init()` once (consent-gated) then `fire()` per event —
     * so ALL cookie-setting happens inside `init`, enabling consent deferral.
     * Returns '' when not configured.
     *
     * @param  array<string, mixed>  $config
     */
    public function headScript(array $config): string;

    /**
     * Raw HTML injected immediately after <body> (e.g. GTM's <noscript> iframe).
     * '' when the provider needs nothing there.
     *
     * @param  array<string, mixed>  $config
     */
    public function bodyHtml(array $config): string;
}
