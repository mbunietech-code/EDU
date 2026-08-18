<x-layouts.admin title="Payment Methods" header="Payment Methods">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Payment Methods</h1>
            <p class="mt-1 text-sm text-gray-500">Enable or disable payment methods shown at checkout and manage their QR codes and instructions.</p>
        </div>
    </div>

    <div class="mt-6 mbui-card p-6">
        <h2 class="text-base font-semibold text-gray-900">Add payment method</h2>
        <p class="mt-1 text-sm text-gray-500">A method appears at checkout as soon as you upload its QR code. Provide an app link to enable tap-QR-to-open-app.</p>

        <form method="POST" action="{{ route('admin.payment-methods.store') }}" class="mt-5 space-y-4">
            @csrf
            <input type="hidden" name="enabled" value="1">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <x-input-label for="new-code" value="Code (unique, e.g. mpesa)" />
                    <x-text-input id="new-code" class="mbui-input mt-1" type="text" name="code" :value="old('code')" placeholder="mpesa" required />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="new-name" value="Name" />
                    <x-text-input id="new-name" class="mbui-input mt-1" type="text" name="name" :value="old('name')" placeholder="M-Pesa" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="new-sort_order" value="Sort order" />
                    <x-text-input id="new-sort_order" class="mbui-input mt-1" type="number" min="0" name="sort_order" :value="old('sort_order', 4)" />
                    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="new-description" value="Description" />
                <x-text-input id="new-description" class="mbui-input mt-1" type="text" name="description" :value="old('description')" />
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="new-instructions" value="Payment instructions" />
                <textarea id="new-instructions" name="instructions" rows="2" class="mbui-input mt-1" placeholder="Open the app, scan the QR code, enter the exact amount and complete the payment.">{{ old('instructions') }}</textarea>
                <x-input-error :messages="$errors->get('instructions')" class="mt-2" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="new-link_url" value="App link (tap QR opens app)" />
                    <x-text-input id="new-link_url" class="mbui-input mt-1" type="text" name="link_url" :value="old('link_url')" placeholder="intent://#Intent;package=...;S.browser_fallback_url=...;end" />
                    <x-input-error :messages="$errors->get('link_url')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-400">Android deep link / intent URL used when the customer taps the QR on a phone.</p>
                </div>
                <div>
                    <x-input-label for="new-store_url" value="App store link (fallback)" />
                    <x-text-input id="new-store_url" class="mbui-input mt-1" type="text" name="store_url" :value="old('store_url')" placeholder="https://play.google.com/store/apps/details?id=..." />
                    <x-input-error :messages="$errors->get('store_url')" class="mt-2" />
                    <p class="mt-1 text-xs text-gray-400">Opens when the app cannot be launched (iPhone/desktop or app not installed).</p>
                </div>
            </div>
            <div class="flex justify-end border-t border-gray-100 pt-4">
                <x-mbui.button type="submit">Add payment method</x-mbui.button>
            </div>
        </form>
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
                        <x-input-label for="link_url-{{ $method->id }}" value="App link (tap QR opens app)" />
                        <x-text-input id="link_url-{{ $method->id }}" class="mbui-input mt-1" type="text" name="link_url" :value="old('link_url', $method->link_url)"
                            placeholder="intent://#Intent;package=tz.tigo.mfsapp;S.browser_fallback_url=...;end" />
                        <x-input-error :messages="$errors->get('link_url')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-400">Android deep link / intent URL used when the customer taps the QR on a phone.</p>
                    </div>
                    <div>
                        <x-input-label for="store_url-{{ $method->id }}" value="App store link (fallback)" />
                        <x-text-input id="store_url-{{ $method->id }}" class="mbui-input mt-1" type="text" name="store_url" :value="old('store_url', $method->store_url)"
                            placeholder="https://play.google.com/store/apps/details?id=tz.tigo.mfsapp" />
                        <x-input-error :messages="$errors->get('store_url')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-400">Opens when the app cannot be launched (iPhone/desktop or app not installed).</p>
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