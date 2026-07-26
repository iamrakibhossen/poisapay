@php
    $align = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'][$props['align'] ?? 'left'] ?? 'text-left';
    // Rich text is authored in the editor; strip to a safe subset for public render.
    $html = strip_tags((string) ($props['html'] ?? ''), '<b><strong><i><em><a><br><ul><ol><li><p><span>');
@endphp
<div id="{{ $node->id }}" data-edit-rich="html" class="{{ $align }} text-sm leading-relaxed text-neutral-600 [&_a]:underline">{!! $html !!}</div>
