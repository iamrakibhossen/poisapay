@props([
    'text' => '',
    'label' => 'Copy',
    'success' => 'Copied!',
])

<div x-data="{
    copied: false,
    copyText() {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(@js($text)).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 1500);
            }).catch(err => {
                console.error('Clipboard error:', err);
            });
        } else {
            alert('Clipboard not supported.');
        }
    }
}" class="inline-flex items-center space-x-2">

    <x-ui.tooltip message="{{ $label }}" width="w-24">
        <x-heroicon-o-clipboard-document
            x-bind:class="copied ? 'text-amber-700' : 'text-gray-500 hover:text-gray-700'"
            class="h-5 w-5 cursor-pointer transition hover:text-gray-700"
            @click="copyText" role="button" aria-label="Copy to clipboard" x-show="!copied" />
        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-amber-700 transition" x-show="copied" />
    </x-ui.tooltip>

</div>
