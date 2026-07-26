<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('Pixel test') }} · {{ $label }}</title>
    {!! $head !!}
    <style>
        body{margin:0;font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;display:grid;place-items:center;min-height:100vh}
        .card{max-width:26rem;padding:2rem;text-align:center}
        .dot{display:inline-block;width:.6rem;height:.6rem;border-radius:99px;background:#22c55e;margin-inline-end:.4rem}
        .muted{color:#94a3b8;font-size:.85rem}
        code{background:#1e293b;padding:.1rem .35rem;border-radius:.3rem;font-size:.8rem}
    </style>
</head>
<body>
    {!! $body !!}
    <div class="card">
        @if ($active)
            <h1 style="font-size:1.1rem"><span class="dot"></span>{{ __('Test events sent to :label', ['label' => $label]) }}</h1>
            <p class="muted">{{ __('Fired a sample :pv and :purchase. Open your :label Events Manager / test-events tool to confirm they arrived (may take a minute).', ['pv' => 'PageView', 'purchase' => 'Purchase', 'label' => $label]) }}</p>
            <p class="muted">{{ __('Order id') }}: <code>TEST-{{ strtoupper(request('provider')) }}</code></p>
        @else
            <h1 style="font-size:1.1rem">{{ __(':label isn’t configured', ['label' => $label]) }}</h1>
            <p class="muted">{{ __('Save a valid ID for this network first, then run the test.') }}</p>
        @endif
        <p class="muted" style="margin-top:1.5rem">{{ __('You can close this tab.') }}</p>
    </div>
</body>
</html>
