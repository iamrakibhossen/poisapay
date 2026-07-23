<x-layouts.admin :title="__('Edit :name', ['name' => $user->name])">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-ui.page-header :title="__('Edit user')" :subtitle="$user->email">
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.users.show', $user) }}" variant="ghost" size="sm" icon="arrow-left">{{ __('Back to profile') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="pp-card space-y-6 p-6">
            @csrf
            @method('PUT')

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ __('Profile') }}</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.input :label="__('Name')" name="name" :value="old('name', $user->name)" :error="$errors->first('name')" />
                    <x-ui.input :label="__('Email')" name="email" type="email" :value="old('email', $user->email)" :error="$errors->first('email')" />
                    <x-ui.input :label="__('Phone')" name="phone" :value="old('phone', $user->phone)" :error="$errors->first('phone')" placeholder="{{ __('—') }}" />
                    <x-ui.input :label="__('Handle')" name="handle" :value="old('handle', $user->handle)" :error="$errors->first('handle')" placeholder="{{ __('username') }}" />
                    <x-ui.input :label="__('Base currency')" name="base_currency" :value="old('base_currency', $user->base_currency ?? 'USD')" maxlength="3" :error="$errors->first('base_currency')" />
                </div>
            </div>

            <div class="border-t border-neutral-100 pt-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ __('Verification & KYC') }}</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.select :label="__('KYC tier')" name="kyc_tier" :error="$errors->first('kyc_tier')">
                        @foreach ($tiers as $tier)
                            <option value="{{ $tier->value }}" @selected(old('kyc_tier', $user->kyc_tier->value) === $tier->value)>{{ $tier->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select :label="__('KYC status')" name="kyc_status" :error="$errors->first('kyc_status')">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('kyc_status', $user->kyc_status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <label class="mt-4 inline-flex items-center gap-2 text-sm text-neutral-700">
                    <input type="hidden" name="email_verified" value="0" />
                    <input type="checkbox" name="email_verified" value="1" @checked(old('email_verified', $user->email_verified_at !== null)) class="h-4 w-4 rounded border-neutral-300 text-brand-500 focus:ring-brand-500" />
                    {{ __('Email verified') }}
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-6">
                <x-ui.button href="{{ route('admin.users.show', $user) }}" variant="secondary">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ __('Save changes') }}</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>
