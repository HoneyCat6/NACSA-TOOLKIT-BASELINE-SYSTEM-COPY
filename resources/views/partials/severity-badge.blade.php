{{-- Colour is never the only signal: the badge always carries a text label (PRD §11). --}}
<span class="badge {{ $class ?? '' }}" style="background: {{ $severity->colour() }}; color: {{ $severity->textColour() }};">{{ $label }}</span>
