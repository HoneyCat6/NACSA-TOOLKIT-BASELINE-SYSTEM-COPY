{{-- Signed change with a word, not only colour or arrow. --}}
@php($value = (float) $delta)
<span class="{{ $class ?? '' }} fw-semibold {{ $value < 0 ? 'text-danger' : ($value > 0 ? 'text-success' : 'text-muted') }}">
    {{ $value > 0 ? '▲ +' : ($value < 0 ? '▼ ' : '') }}{{ $delta }}
    <span class="small fw-normal">{{ $value > 0 ? 'improved' : ($value < 0 ? 'declined' : 'no change') }}</span>
</span>
