<?php

declare(strict_types=1);

/*
 * Isolated Landing module — render smoke. The Landing module owns the public
 * marketing surface at its real paths (/, /prices, /help-center, …) with stable
 * route names. These assertions confirm each page renders through the landing::
 * views + <x-landing::*> chrome + isolated bundle, loading only the landing
 * assets (no app.css / frontend.js).
 */

it('renders the landing home at / with the isolated bundle only', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('lp-wrapper', false)
        ->assertSee('resources/landing/css/landing.css', false)
        ->assertDontSee('resources/css/app.css', false)       // isolation: no app CSS
        ->assertDontSee('resources/js/frontend.js', false);   // isolation: no app JS
});

it('renders the live prices page', function () {
    $this->get(route('marketing.prices'))->assertOk()->assertSee('lp-wrapper', false);
});

it('returns the rates JSON feed', function () {
    $this->get(route('marketing.rates'))
        ->assertOk()
        ->assertJsonStructure(['base', 'symbol', 'rates', 'as_of']);
});

it('renders the help center', function () {
    $this->get(route('help-center'))->assertOk()->assertSee('lp-wrapper', false);
});

it('renders the system status page', function () {
    $this->get(route('status'))->assertOk()->assertSee('lp-wrapper', false);
});

it('renders a product marketing page', function () {
    $this->get(route('products.show', 'virtual-card'))
        ->assertOk()
        ->assertSee('Spend crypto like cash', false);
});

it('404s an unknown product', function () {
    $this->get(route('products.show', 'nope'))->assertNotFound();
});

it('redirects the legacy /faqs path to the help center', function () {
    $this->get('/faqs')->assertRedirect('/help-center');
});

it('redirects /merchants to the merchant-pay product page', function () {
    $this->get('/merchants')->assertRedirect(route('products.show', 'merchant-pay'));
});
