<x-layouts.admin title="VPN devices" header="Mbunie VPN">
    @include('admin.vpn._header', ['title' => 'VPN devices', 'subtitle' => 'Every phone and computer signed in to Mbunie VPN. Remove one the customer does not recognise.'])

    @if ($error)
        <x-mbui.alert type="warning" class="mt-4">{{ $error }}</x-mbui.alert>
    @endif

    <form method="GET" class="mt-4 vpn-row">
        @foreach (['all' => 'All', 'online' => 'Online now', 'errors' => 'With errors', 'removed' => 'Removed'] as $key => $label)
            <a class="vpn-btn {{ $filter === $key ? 'primary' : '' }}" href="{{ route('admin.vpn.devices', array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $q])) }}">{{ $label }}</a>
        @endforeach
        <input type="hidden" name="filter" value="{{ $filter === 'all' ? '' : $filter }}">
        <input class="vpn-input" style="max-width:18rem" type="search" name="q" value="{{ $q }}" placeholder="Customer email, phone or model">
        <button class="vpn-btn" type="submit">Search</button>
    </form>

    @if ($result)
        <div class="mbui-card mt-4" style="overflow:visible">
            @include('admin.vpn._devices_table', ['devices' => $result['data'], 'showCustomer' => true, 'canManage' => auth()->user()->can('vpn.manage')])
        </div>
        @include('admin.vpn._pager', ['meta' => $result['meta']])
    @endif
</x-layouts.admin>
