<x-layouts.admin title="New Message" header="New Message">

    <div class="max-w-lg">
        <form method="POST" action="{{ route('admin.chat.start') }}" class="mbui-card p-6 space-y-5">
            @csrf
            <div>
                <x-input-label for="user_id" value="Customer" />
                <select id="user_id" name="user_id" class="mbui-input mt-1" required>
                    <option value="">Select a customer</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                <p class="mt-1 text-xs text-gray-500">Opens (or starts) a conversation with this customer so you can send the first message.</p>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.chat.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <x-mbui.button type="submit">Start conversation</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
