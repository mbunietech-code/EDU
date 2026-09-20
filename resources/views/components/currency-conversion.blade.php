@props(['amount', 'stacked' => false])

@if ($amount > 0)
    <p {{ $attributes }}>&asymp; ${{ number_format($amount * $rates['USD'], 2) }} USD @if ($stacked)<br>@else&middot; @endif&asymp; &yen;{{ number_format($amount * $rates['CNY'], 2) }} CNY</p>
@endif
