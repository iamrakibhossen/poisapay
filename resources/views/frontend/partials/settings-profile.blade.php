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
