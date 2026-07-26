<?php

declare(strict_types=1);

namespace App\Shop\Tracking;

use App\Shop\Tracking\Contracts\TrackingProvider;

/**
 * Composes every registered {@see TrackingProvider} into the head/body markup for
 * a single sales page, plus a tiny consent-gated runtime that fans one internal
 * event out to all enabled providers. The rest of the app only touches this class
 * (and {@see TrackingEvent}); providers are pluggable — register one more and it
 * "just works" everywhere.
 */
class TrackingManager
{
    /** @var array<string, TrackingProvider> */
    private array $providers = [];

    /** @param  iterable<TrackingProvider>  $providers */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    /** @return array<string, TrackingProvider> */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * Any provider on this page configured + enabled? (skip all rendering if not)
     *
     * @param  array<string, mixed>  $tracking
     */
    public function isActive(array $tracking): bool
    {
        return $this->configured($tracking) !== [];
    }

    /**
     * Everything for <head>: each enabled provider's install snippet followed by
     * the runtime that fires the page's initial events. '' when nothing is active.
     *
     * @param  array<string, mixed>  $tracking
     * @param  list<TrackingEvent>  $events
     */
    public function head(array $tracking, array $events = []): string
    {
        $active = $this->configured($tracking);
        if ($active === []) {
            return '';
        }

        $out = '';
        foreach ($active as $provider) {
            $out .= $provider->headScript($tracking[$provider->key()])."\n";
        }

        return $out.$this->runtime($tracking, $events);
    }

    /**
     * Everything for right-after-<body> (e.g. GTM's noscript iframe).
     *
     * @param  array<string, mixed>  $tracking
     */
    public function body(array $tracking): string
    {
        $out = '';
        foreach ($this->configured($tracking) as $provider) {
            $out .= $provider->bodyHtml($tracking[$provider->key()]);
        }

        return $out;
    }

    /**
     * Laravel validation rules for the whole `tracking` settings payload, derived
     * from each provider's declared fields (single source of truth).
     *
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        $rules = [
            'tracking' => ['sometimes', 'array'],
            'tracking.privacy.cookies' => ['sometimes', 'boolean'],
            'tracking.privacy.consent_required' => ['sometimes', 'boolean'],
            'tracking.privacy.anonymize_ip' => ['sometimes', 'boolean'],
        ];

        foreach ($this->providers as $provider) {
            $k = $provider->key();
            $rules["tracking.$k"] = ['sometimes', 'array'];
            $rules["tracking.$k.enabled"] = ['sometimes', 'boolean'];

            foreach ($provider->fields() as $i => $field) {
                $rule = ['nullable', 'string', 'max:512'];
                if ($field['pattern'] !== null) {
                    $rule[] = 'regex:/'.$field['pattern'].'/';
                }
                if ($i === 0) {
                    // Primary id is required only when the provider is switched on.
                    $rule[] = "required_if:tracking.$k.enabled,1,true";
                }
                $rules["tracking.$k.{$field['key']}"] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Providers that are enabled AND validly configured, in registration order.
     *
     * @param  array<string, mixed>  $tracking
     * @return list<TrackingProvider>
     */
    private function configured(array $tracking): array
    {
        $out = [];
        foreach ($this->providers as $key => $provider) {
            if (is_array($tracking[$key] ?? null) && $provider->isConfigured($tracking[$key])) {
                $out[] = $provider;
            }
        }

        return $out;
    }

    /**
     * The shared runtime: defines window.ppTrack(type, data) to fan out to every
     * registered tracker, calls each init() once (deferred until consent when
     * required), then fires the page's initial events. Events fired before consent
     * are queued and flushed on ppTrackingConsent().
     *
     * @param  array<string, mixed>  $tracking
     * @param  list<TrackingEvent>  $events
     */
    private function runtime(array $tracking, array $events): string
    {
        $eventsJson = (string) json_encode(
            array_map(static fn (TrackingEvent $e) => $e->toArray(), $events),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES,
        );
        $consentRequired = ! empty($tracking['privacy']['consent_required']) ? 'true' : 'false';

        return <<<HTML
        <!-- PoishaPay tracking runtime -->
        <script>
        (function(){var started=false,q=[],ev={$eventsJson};window.ppTrack=function(t,d){if(!started){q.push([t,d]);return;}(window.__ppTrackers||[]).forEach(function(x){try{x.fire(t,d||{})}catch(e){}})};function start(){if(started)return;started=true;(window.__ppTrackers||[]).forEach(function(x){try{x.init()}catch(e){}});ev.forEach(function(e){window.ppTrack(e.type,e.data)});q.splice(0).forEach(function(z){window.ppTrack(z[0],z[1])})}window.ppTrackingConsent=function(){start()};function dp(el){if(!el||!el.getAttribute)return;var t=el.getAttribute('data-pp-track');if(!t)return;var d={};try{d=JSON.parse(el.getAttribute('data-pp-track-data')||'{}')}catch(e){}window.ppTrack(t,d)}document.addEventListener('click',function(e){dp(e.target.closest&&e.target.closest('[data-pp-track]'))},true);document.addEventListener('submit',function(e){dp(e.target&&e.target.closest&&e.target.closest('[data-pp-track]'))},true);if(!{$consentRequired}){start()}})();
        </script>
        HTML;
    }
}
