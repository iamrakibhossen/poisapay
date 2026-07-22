<x-layouts.admin :title="'Edit '.$user->name">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-ui.page-header title="Edit user" :subtitle="$user->email">
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.users.show', $user) }}" variant="ghost" size="sm" icon="arrow-left">Back to profile</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="pp-card space-y-6 p-6">
            @csrf
            @method('PUT')

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Profile</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Name" name="name" :value="old('name', $user->name)" :error="$errors->first('name')" />
                    <x-ui.input label="Email" name="email" type="email" :value="old('email', $user->email)" :error="$errors->first('email')" />
                    <x-ui.input label="Phone" name="phone" :value="old('phone', $user->phone)" :error="$errors->first('phone')" placeholder="—" />
                    <x-ui.input label="Handle" name="handle" :value="old('handle', $user->handle)" :error="$errors->first('handle')" placeholder="username" />
                    <x-ui.input label="Base currency" name="base_currency" :value="old('base_currency', $user->base_currency ?? 'USD')" maxlength="3" :error="$errors->first('base_currency')" />
                </div>
            </div>

            <div class="border-t border-neutral-100 pt-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Verification &amp; KYC</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.select label="KYC tier" name="kyc_tier" :error="$errors->first('kyc_tier')">
                        @foreach ($tiers as $tier)
                            <option value="{{ $tier->value }}" @selected(old('kyc_tier', $user->kyc_tier->value) === $tier->value)>{{ $tier->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="KYC status" name="kyc_status" :error="$errors->first('kyc_status')">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('kyc_status', $user->kyc_status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <label class="mt-4 inline-flex items-center gap-2 text-sm text-neutral-700">
                    <input type="hidden" name="email_verified" value="0" />
                    <input type="checkbox" name="email_verified" value="1" @checked(old('email_verified', $user->email_verified_at !== null)) class="h-4 w-4 rounded border-neutral-300 text-brand-500 focus:ring-brand-500" />
                    Email verified
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-6">
                <x-ui.button href="{{ route('admin.users.show', $user) }}" variant="secondary">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">Save changes</x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>
