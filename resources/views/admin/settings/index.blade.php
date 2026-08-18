<x-layouts.admin title="Settings" header="Settings">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Key-value configuration used by the platform.</p>
        </div>
    </div>

    <div class="mt-6">
        @if ($settings->isEmpty())
            <x-mbui.card>
                <x-mbui.empty-state title="No settings yet" message="Settings are seeded during setup." />
            </x-mbui.card>
        @else
            <form method="POST" action="{{ route('admin.settings.update') }}" class="mbui-card overflow-hidden">
                @csrf
                <x-mbui.table>
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="mbui-th">Key</th>
                            <th class="mbui-th">Value</th>
                            <th class="mbui-th">Group</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($settings as $setting)
                            <tr>
                                <td class="mbui-td font-mono text-xs font-medium text-gray-900">{{ $setting->key }}</td>
                                <td class="mbui-td">
                                    <input type="hidden" name="settings[{{ $loop->index }}][key]" value="{{ $setting->key }}">
                                    <input type="text" name="settings[{{ $loop->index }}][value]" value="{{ $setting->value }}"
                                        class="mbui-input !py-1.5 !text-sm">
                                </td>
                                <td class="mbui-td">
                                    <x-mbui.badge appearance="neutral">{{ $setting->group }}</x-mbui.badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-mbui.table>
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
                    <p class="text-xs text-gray-500">Changes are recorded in the activity log.</p>
                    <x-mbui.button type="submit">Save settings</x-mbui.button>
                </div>
            </form>
            <div class="mt-6">{{ $settings->links() }}</div>
        @endif
    </div>

</x-layouts.admin>