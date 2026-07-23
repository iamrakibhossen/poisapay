@php
    $home = route('home');
    $footCols = [
        __('Product') => [
            [__('Virtual card'), $home.'#cards'],
            [__('Wallet'), $home.'#wallet'],
            [__('Exchange'), $home.'#exchange'],
            [__('Merchant pay'), route('merchants')],
        ],
        __('Company') => [
            [__('Features'), $home.'#features'],
            [__('Security'), $home.'#security'],
            [__('Create account'), route('register')],
            [__('Log in'), route('login')],
        ],
        __('Resources') => [
            [__('Help center'), route('faqs.public')],
            [__('FAQ'), $home.'#faq'],
            [__('Live prices'), $home.'#exchange'],
            [__('Get started'), route('register')],
        ],
    ];
@endphp
<footer class="relative border-t border-slate-200 bg-white/60 px-4 py-14 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
            <div>
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-xl text-white" style="background:linear-gradient(120deg,var(--brand),var(--brand-600))"><x-heroicon-s-bolt class="h-5 w-5" /></span>
                    <span class="text-lg font-bold text-slate-900">PoisaPay</span>
                </a>
                <p class="mt-4 max-w-xs text-sm text-slate-500">{{ __('A premium crypto wallet with a beautiful virtual card. Spend crypto like cash, built for Bangladesh.') }}</p>
                <div class="mt-5 flex gap-2.5">
                    @foreach (['x','github','telegram','discord'] as $soc)
                        <a href="#" aria-label="{{ $soc }}" class="grid h-9 w-9 place-items-center rounded-xl glass text-slate-500 transition hover:text-slate-900 hover:bg-slate-50">
                            <span class="text-xs font-bold uppercase">{{ substr($soc,0,1) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            @foreach ($footCols as $title => $links)
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ $title }}</p>
                    <ul class="mt-4 space-y-2.5 text-sm text-slate-500">
                        @foreach ($links as $link)
                            <li><a href="{{ $link[1] }}" class="transition hover:text-slate-900">{{ $link[0] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-3 border-t border-slate-200 pt-6 text-sm text-slate-500 sm:flex-row">
            <p>© {{ date('Y') }} PoisaPay · {{ __('Custodial · KYC/AML gated') }}</p>
            <div class="flex gap-5">
                <a href="#" class="transition hover:text-slate-900">{{ __('Privacy') }}</a>
                <a href="#" class="transition hover:text-slate-900">{{ __('Terms') }}</a>
                <a href="#" class="transition hover:text-slate-900">{{ __('Legal') }}</a>
            </div>
        </div>
    </div>
</footer>
