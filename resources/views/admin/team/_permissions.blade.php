{{-- $groups: array<string, array<key,label>> ; $selected: list<string> --}}
<div x-data="{ role: @js($currentRole ?? 'admin') }">
    <div class="mb-4">
        <x-input-label value="Role" />
        <select name="role" x-model="role" class="mbui-input mt-1 sm:w-96">
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $currentRole ?? 'admin') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div x-show="role !== 'super_admin'" class="space-y-5">
        <p class="text-sm text-gray-500">Tick only what this admin should see and manage.</p>

        @foreach ($groups as $groupName => $perms)
            <fieldset class="rounded-lg border border-gray-200 p-4">
                <legend class="px-1 text-sm font-semibold text-gray-900">{{ $groupName }}</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($perms as $key => $label)
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                @checked(in_array($key, old('permissions', $selected ?? []), true))
                                class="mt-0.5 rounded border-gray-300 text-indigo-600">
                            <span>{{ $label }}<span class="block text-xs text-gray-400">{{ $key }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>

    <p x-show="role === 'super_admin'" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
        Super admins have full access to everything, including this Team page and the Database page.
    </p>
</div>
