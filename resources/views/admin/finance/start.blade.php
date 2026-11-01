<x-layouts.finance title="Start date" header="Finance">

    <div class="mbui-page-header">
        <div>
            <a href="{{ route('admin.finance.unorganised.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; Unorganised data</a>
            <h1 class="mbui-title mt-1">Start date &amp; opening balances</h1>
            <p class="mt-1 text-sm text-gray-500">Finance counts from this day on. Nothing is deleted or changed: earlier records stay under Unorganised data, read-only, and customer payments, orders and subscriptions are not touched.</p>
        </div>
    </div>

    {{-- Pick another day to see what the records leave in each account just before it. --}}
    <form method="GET" class="mt-6 mbui-card flex flex-wrap items-end gap-3 p-5">
        <div>
            <x-input-label for="start-preview" value="See balances for another day" />
            <x-text-input id="start-preview" class="mbui-input mt-1" type="date" name="date" :value="$date->toDateString()" max="{{ \App\Support\FinanceDates::today() }}" />
        </div>
        <x-mbui.button type="submit" variant="secondary">Show</x-mbui.button>
    </form>

    <form method="POST" action="{{ route('admin.finance.start.update') }}" class="mt-6 mbui-card p-6 space-y-6">
        @csrf
        @method('PUT')

        <div class="max-w-xs">
            <x-input-label for="start-date" value="Finance starts on" />
            <x-text-input id="start-date" class="mbui-input mt-1" type="date" name="start_date" :value="old('start_date', $date->toDateString())" max="{{ \App\Support\FinanceDates::today() }}" required />
            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
        </div>

        <div>
            <h2 class="text-base font-semibold text-gray-900">Money in each account that morning</h2>
            <p class="mt-1 text-sm text-gray-500">Count what really sits in each wallet, bank and cash box at the start of {{ $date->format('d M Y') }} and type it. Left empty, an account starts with what the earlier records leave in it.</p>

            @if ($accounts->isEmpty())
                <p class="mt-3 text-sm text-gray-500">No money accounts yet. Add them under <a href="{{ route('admin.finance.money.index') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Money accounts</a>.</p>
            @else
                <x-mbui.table class="mt-3">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="mbui-th">Account</th>
                            <th class="mbui-th text-right">Left by the records</th>
                            <th class="mbui-th">Counted (TZS)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($accounts as $a)
                            <tr>
                                <td class="mbui-td">
                                    <label for="bal-{{ $a['account']->id }}" class="font-medium text-gray-900">{{ $a['account']->name }}</label>
                                    @unless ($a['account']->is_active) <span class="text-xs text-gray-400">· archived</span>@endunless
                                </td>
                                <td class="mbui-td text-right tabular-nums text-gray-500">{{ number_format($a['records']) }}</td>
                                <td class="mbui-td">
                                    <x-text-input id="bal-{{ $a['account']->id }}" class="mbui-input w-48" type="number" step="0.01" inputmode="decimal"
                                        name="balances[{{ $a['account']->id }}]" :value="old('balances.'.$a['account']->id, $a['counted'] === null ? '' : $a['counted'])" placeholder="{{ (int) $a['records'] }}" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-mbui.table>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-mbui.button type="submit">{{ $start ? 'Save' : 'Start Finance on this day' }}</x-mbui.button>
            @if ($start)
                <span class="text-sm text-gray-500">Now starts on {{ $start->format('d M Y') }}.</span>
            @endif
        </div>
    </form>

    @if ($start)
        <form method="POST" action="{{ route('admin.finance.start.destroy') }}" class="mt-6" onsubmit="return confirm('Count every record again, with no start date?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-500">Stop using a start date (count every record again)</button>
        </form>
    @endif

</x-layouts.finance>
