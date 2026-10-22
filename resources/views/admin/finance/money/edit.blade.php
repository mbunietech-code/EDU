<x-layouts.finance title="Edit money account" header="Finance">

    <div class="mbui-page-header">
        <div>
            <a href="{{ route('admin.finance.money.show', $account) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; {{ $account->name }}</a>
            <h1 class="mbui-title mt-1">Edit money account</h1>
        </div>
    </div>

    <div class="mt-6 mbui-card p-6">
        <form method="POST" action="{{ route('admin.finance.money.update', $account) }}" class="space-y-4">
            @csrf
            @method('PUT')
            @include('admin.finance.money._fields', ['account' => $account])
            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('admin.finance.money.show', $account) }}" class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
                <x-mbui.button type="submit">Save</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.finance>
