{{-- Settings › Profile tab — identity header + personal details form.
     Expects (from the settings view scope): $user, $profile, $errors. --}}
{{-- Identity header --}}
<div class="flex items-center gap-4">
    <x-ui.avatar :name="$user->name" size="lg" />
    <div class="min-w-0">
        <p class="truncate text-base font-semibold text-neutral-900">{{ $user->name }}</p>
        <p class="truncate text-sm text-neutral-500">{{ $user->email }}</p>
    </div>
    <span class="ms-auto hidden sm:block">
        <x-ui.badge :color="$user->kyc_status->color()">{{ $user->kyc_status->label() }}</x-ui.badge>
    </span>
</div>

{{-- Shareable PoisaPay ID — hand this number out to get paid. --}}
<div class="flex items-center justify-between rounded-xl border border-neutral-200 bg-neutral-50/60 px-4 py-3">
    <div class="min-w-0">
        <p class="text-xs font-medium text-neutral-500">{{ __('Your PoisaPay ID') }}</p>
        <p class="tabular mt-0.5 text-lg font-semibold tracking-wide text-neutral-900">{{ $user->uid }}</p>
        <p class="mt-0.5 text-xs text-neutral-400">{{ __('Share this ID so anyone can send you money.') }}</p>
    </div>
    <x-ui.copy-text :text="(string) $user->uid" :label="__('Copy ID')" />
</div>

<x-settings.section :title="__('Personal details')" :description="__('Your name and how we reach you.')">
    <form method="POST" action="{{ route('settings.profile') }}" class="space-y-5">
        @csrf
        @method('PUT')
        <x-ui.input :label="__('Full name')" name="name" :value="old('name', $profile['name'])" :error="$errors->first('name')" />
        <x-ui.input :label="__('Phone')" name="phone" :value="old('phone', $profile['phone'])" placeholder="+8801…" :error="$errors->first('phone')" />
        <x-ui.select :label="__('Base currency')" name="baseCurrency" :error="$errors->first('baseCurrency')">
            @foreach (['BDT' => 'BDT — Bangladeshi Taka', 'USD' => 'USD — US Dollar', 'EUR' => 'EUR — Euro'] as $code => $label)
                <option value="{{ $code }}" @selected(old('baseCurrency', $profile['baseCurrency']) === $code)>{{ $label }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select :label="__('Timezone')" name="timezone" :error="$errors->first('timezone')">
            @foreach (['Asia/Dhaka', 'Asia/Kolkata', 'Asia/Dubai', 'UTC', 'Europe/London', 'America/New_York'] as $tz)
                <option value="{{ $tz }}" @selected(old('timezone', $profile['timezone']) === $tz)>{{ $tz }}</option>
            @endforeach
        </x-ui.select>
        <div class="pt-1">
            <x-ui.button type="submit" variant="dark">{{ __('Save changes') }}</x-ui.button>
        </div>
    </form>
</x-settings.section>
