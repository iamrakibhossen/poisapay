@foreach (['message', 'success', 'danger', 'error', 'warning'] as $type)
    @if (session()->has($type))
        <x-flash-message :type="$type">{{ session($type) }}</x-flash-message>
    @endif
@endforeach
