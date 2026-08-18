<x-layouts.admin title="Payment Methods" header="Payment Methods">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payment Methods</h1>
            <p class="mt-1 text-sm text-gray-500">Enable or disable payment methods shown at checkout and manage their QR codes and instructions.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @forelse ($paymentMethods as $method)
            <div class="mbui-card p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ $method->name }}</h2>
                        <p class="text-xs font-mono text-gray-400">{{ $method->code }}</p>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-wide {{ $method->enabled ? 'text-emerald-600' : 'text-gray-400' }}">
                        {{ $method->enabled ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('admin.payment-methods.update', $method) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="enabled" value="0">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-medium text-gray-700" for="enabled-{{ $method->id }}">Enable at checkout</label>
                        <input type="checkbox" name="enabled" id="enabled-{{ $method->id }}" value="1"
                            class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            @checked(old('enabled', $method->enabled))>
                    </div>
                    <div>
                        <x-input-label for="description-{{ $method->id }}" value="Description" />
                        <x-text-input id="description-{{ $method->id }}" class="mbui-input mt-1" type="text" name="description" :value="old('description', $method->description)" />
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="instructions-{{ $method->id }}" value="Payment instructions" />
                        <textarea id="instructions-{{ $method->id }}" name="instructions" rows="3" class="mbui-input mt-1">{{ old('instructions', $method->instructions) }}</textarea>
                        <x-input-error :messages="$errors->get('instructions')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="sort_order-{{ $method->id }}" value="Sort order" />
                        <x-text-input id="sort_order-{{ $method->id }}" class="mbui-input mt-1" type="number" min="0" name="sort_order" :value="old('sort_order', $method->sort_order)" />
                        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                    </div>
                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <x-mbui.button type="submit">Save details</x-mbui.button>
                    </div>
                </form>

                <div class="mt-6 border-t border-gray-100 pt-5">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">QR code</h3>
                        @if ($method->qrImageUrl())
                            <form method="POST" action="{{ route('admin.payment-methods.qr.remove', $method) }}" onsubmit="return confirm('Remove this QR code?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-800">Remove</button>
                            </form>
                        @endif
                    </div>

                    @if ($method->qrImageUrl())
                        <img src="{{ $method->qrImageUrl() }}" alt="{{ $method->name }} QR code"
                            class="mt-3 h-32 w-32 rounded-lg border border-gray-200 object-cover">
                    @else
                        <p class="mt-3 text-sm text-gray-400">No QR code uploaded yet.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.payment-methods.qr', $method) }}" enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <input type="file" name="qr_image" accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                        <x-input-error :messages="$errors->get('qr_image')" class="mt-2" />
                        <div class="mt-3 flex justify-end">
                            <x-mbui.button type="submit" variant="secondary">Upload QR</x-mbui.button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No payment methods configured.</p>
        @endforelse
    </div>

</x-layouts.admin>