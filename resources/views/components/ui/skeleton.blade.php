@props([
    'type' => 'line',   // line | text | card | stat | table | form
    'class' => 'h-4 w-full',
    'rows' => 5,        // table/form rows
    'cols' => 4,        // table columns
    'lines' => 3,       // text lines
])

@php $bar = 'animate-pulse rounded-lg bg-gray-200/70'; @endphp

@switch($type)
    @case('text')
        <div {{ $attributes->merge(['class' => 'space-y-2.5']) }}>
            @for ($i = 0; $i < $lines; $i++)
                <div class="{{ $bar }} h-3.5 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
            @endfor
        </div>
        @break

    @case('card')
        <div {{ $attributes->merge(['class' => 'pp-card p-5']) }}>
            <div class="flex items-center gap-3">
                <div class="{{ $bar }} h-10 w-10 rounded-full"></div>
                <div class="flex-1 space-y-2">
                    <div class="{{ $bar }} h-3.5 w-1/3"></div>
                    <div class="{{ $bar }} h-3 w-1/4"></div>
                </div>
            </div>
            <div class="mt-4 space-y-2.5">
                <div class="{{ $bar }} h-3.5 w-full"></div>
                <div class="{{ $bar }} h-3.5 w-5/6"></div>
            </div>
        </div>
        @break

    @case('stat')
        <div {{ $attributes->merge(['class' => 'pp-card flex items-center gap-4 p-5']) }}>
            <div class="{{ $bar }} h-12 w-12 rounded-xl"></div>
            <div class="flex-1 space-y-2">
                <div class="{{ $bar }} h-3 w-1/2"></div>
                <div class="{{ $bar }} h-6 w-2/3"></div>
            </div>
        </div>
        @break

    @case('table')
        <div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-gray-200 bg-white']) }}>
            <div class="flex gap-4 border-b border-gray-200 bg-gray-50/70 px-4 py-3">
                @for ($c = 0; $c < $cols; $c++)<div class="{{ $bar }} h-3 flex-1"></div>@endfor
            </div>
            @for ($r = 0; $r < $rows; $r++)
                <div class="flex items-center gap-4 border-b border-gray-100 px-4 py-3.5 last:border-0">
                    @for ($c = 0; $c < $cols; $c++)<div class="{{ $bar }} h-4 flex-1"></div>@endfor
                </div>
            @endfor
        </div>
        @break

    @case('form')
        <div {{ $attributes->merge(['class' => 'space-y-5']) }}>
            @for ($i = 0; $i < $rows; $i++)
                <div class="space-y-2">
                    <div class="{{ $bar }} h-3 w-24"></div>
                    <div class="{{ $bar }} h-11 w-full"></div>
                </div>
            @endfor
        </div>
        @break

    @default
        <div {{ $attributes->merge(['class' => $bar.' '.$class]) }}></div>
@endswitch
