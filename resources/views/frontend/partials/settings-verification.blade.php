{{-- Settings › Verification tab — identity status + KYC application wizard.
     Expects (from the settings view scope): $kyc, $canApplyKyc, $errors. --}}
@php
    $kycBadge = fn ($color) => [
        'success' => 'bg-emerald-50 text-emerald-600', 'warning' => 'bg-amber-50 text-amber-600',
        'danger' => 'bg-red-50 text-red-600', 'info' => 'bg-sky-50 text-sky-600',
        'primary' => 'bg-brand-50 text-brand-700', 'gray' => 'bg-neutral-100 text-neutral-600',
    ][$color] ?? 'bg-neutral-100 text-neutral-600';
    $kycErrorStep = $errors->hasAny(['documentNumber', 'documentFront', 'documentBack', 'selfie', 'documentType']) ? 2 : 1;
@endphp

<x-settings.section title="Identity verification" description="Verify your identity to unlock higher limits and cards.">
    {{-- Current status --}}
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-neutral-500">Verification status</span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $kycBadge($kyc['color']) }}">
            <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
            <span>{{ $kyc['label'] }}</span>
        </span>
    </div>

    @if ($kyc['key'] === 'pending')
        <x-ui.alert type="info" title="Under review" class="mt-5">
            Your verification is being reviewed. This usually takes a short while — we'll notify you once it's complete.
        </x-ui.alert>
    @elseif ($kyc['key'] === 'approved')
        <x-ui.alert type="success" title="You're verified" class="mt-5">
            Your identity has been verified. Enjoy higher limits across PoisaPay.
        </x-ui.alert>
    @elseif ($kyc['key'] === 'rejected')
        <x-ui.alert type="danger" title="Previous submission was rejected" class="mt-5">
            Please review your details and resubmit below.
        </x-ui.alert>
    @endif

    {{-- Application wizard --}}
    @if ($canApplyKyc)
        <form method="POST" action="{{ route('kyc.submit') }}" enctype="multipart/form-data" class="mt-6" x-data="{
            step: {{ $errors->any() ? $kycErrorStep : 1 }},
            review: {},
            goReview() {
                const f = this.$root;
                const val = (n) => {
                    const el = f.querySelector('[name=' + n + ']');
                    if (! el) return '—';
                    return (el.tagName === 'SELECT' ? el.options[el.selectedIndex]?.text : el.value) || '—';
                };
                this.review = {
                    'Full name': val('fullName'),
                    'Date of birth': val('dateOfBirth'),
                    'Country': val('country'),
                    'Address': val('address'),
                    'Document type': val('documentType'),
                    'Document number': val('documentNumber'),
                };
                this.step = 3;
            },
        }">
            @csrf

            {{-- Stepper --}}
            <div class="mb-6 flex items-center">
                @foreach (['Personal', 'Document', 'Review'] as $i => $label)
                    <div class="flex items-center {{ $i < 2 ? 'flex-1' : '' }}">
                        <div class="flex items-center gap-2">
                            <span class="grid h-8 w-8 place-items-center rounded-full text-sm font-semibold transition"
                                :class="step >= {{ $i + 1 }} ? 'bg-brand-500 text-white' : 'bg-neutral-100 text-neutral-500'">{{ $i + 1 }}</span>
                            <span class="hidden text-sm font-medium sm:inline" :class="step >= {{ $i + 1 }} ? 'text-neutral-900' : 'text-neutral-400'">{{ $label }}</span>
                        </div>
                        @if ($i < 2)
                            <span class="mx-3 h-px flex-1 transition" :class="step > {{ $i + 1 }} ? 'bg-brand-600' : 'bg-neutral-200'"></span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Step 1 --}}
            <div x-show="step === 1" class="space-y-4">
                <x-ui.input label="Full legal name" name="fullName" :value="old('fullName')" icon="user" placeholder="As on your document" :error="$errors->first('fullName')" />
                <x-ui.input label="Date of birth" type="date" name="dateOfBirth" :value="old('dateOfBirth')" :error="$errors->first('dateOfBirth')" />
                <x-ui.select label="Country" name="country" :error="$errors->first('country')">
                    @foreach (['BD' => 'Bangladesh', 'IN' => 'India', 'US' => 'United States', 'GB' => 'United Kingdom', 'AE' => 'United Arab Emirates'] as $code => $name)
                        <option value="{{ $code }}" @selected(old('country', 'BD') === $code)>{{ $name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input label="Residential address" name="address" :value="old('address')" icon="map-pin" placeholder="Street, city, postcode" :error="$errors->first('address')" />
                <div class="flex justify-end">
                    <x-ui.button type="button" x-on:click="step = 2" iconRight="arrow-right">Continue</x-ui.button>
                </div>
            </div>

            {{-- Step 2 --}}
            <div x-show="step === 2" x-cloak class="space-y-4">
                <x-ui.select label="Document type" name="documentType" :error="$errors->first('documentType')">
                    <option value="nid" @selected(old('documentType', 'nid') === 'nid')>National ID (NID)</option>
                    <option value="passport" @selected(old('documentType') === 'passport')>Passport</option>
                </x-ui.select>
                <x-ui.input label="Document number" name="documentNumber" :value="old('documentNumber')" icon="identification" :error="$errors->first('documentNumber')" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.file-upload label="Document front" name="documentFront" :error="$errors->first('documentFront')" />
                    <x-ui.file-upload label="Document back" name="documentBack" optional :error="$errors->first('documentBack')" />
                    <x-ui.file-upload label="Selfie" name="selfie" :error="$errors->first('selfie')" />
                </div>
                <div class="flex justify-between">
                    <x-ui.button type="button" variant="ghost" x-on:click="step = 1" icon="arrow-left">Back</x-ui.button>
                    <x-ui.button type="button" x-on:click="goReview()" iconRight="arrow-right">Continue</x-ui.button>
                </div>
            </div>

            {{-- Step 3 --}}
            <div x-show="step === 3" x-cloak class="space-y-4">
                <x-ui.alert type="info">
                    Please confirm your details are accurate. Submitting a fraudulent application may lead to account suspension.
                </x-ui.alert>
                <div>
                    <p class="mb-2 text-sm font-medium text-neutral-700">Review your details</p>
                    <dl class="divide-y divide-neutral-100 overflow-hidden rounded-xl border border-neutral-200">
                        <template x-for="(value, key) in review" :key="key">
                            <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                                <dt class="shrink-0 text-neutral-500" x-text="key"></dt>
                                <dd class="min-w-0 truncate text-right font-medium text-neutral-900" x-text="value"></dd>
                            </div>
                        </template>
                    </dl>
                </div>
                <div class="flex justify-between">
                    <x-ui.button type="button" variant="ghost" x-on:click="step = 2" icon="arrow-left">Back</x-ui.button>
                    <x-ui.button type="submit" variant="success" icon="check">Submit for review</x-ui.button>
                </div>
            </div>
        </form>
    @endif
</x-settings.section>
