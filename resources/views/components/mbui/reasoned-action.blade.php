@props(['action', 'method' => 'DELETE', 'label' => 'Delete', 'promptText' => 'Please give a reason:'])

<form method="POST" action="{{ $action }}" onsubmit="return window.submitWithReason(this, {{ \Illuminate\Support\Js::from($promptText) }});">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="reason" value="">
    <button type="submit" {{ $attributes->class($attributes->has('class') ? [] : ['text-sm font-medium text-red-600 hover:text-red-800']) }}>{{ $label }}</button>
</form>
