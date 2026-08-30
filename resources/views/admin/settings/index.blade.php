<x-layouts.admin title="Settings" header="Settings">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Settings</h1>
            <p class="mt-1 text-sm text-gray-500">Key-value configuration used by the platform.</p>
        </div>
    </div>

    <div class="mt-6">
        <x-mbui.card class="p-6">
            <h2 class="mbui-section-label">Branding</h2>
            <p class="mt-1 text-sm text-gray-500">Your logo appears in the site header, footer, admin sidebar and sign-in page. The favicon is the small icon shown in the browser tab.</p>

            <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data" class="mt-5 grid gap-8 sm:grid-cols-2">
                @csrf

                <div>
                    <x-input-label value="Logo" />
                    <div class="mt-2 flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">
                            @if ($branding['logo'])
                                <img src="{{ $branding['logo'] }}" alt="Current logo" class="h-full w-full object-contain">
                            @else
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600 font-bold text-white">M</span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                class="mbui-input file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700">
                            <p class="mt-1 text-xs text-gray-500">PNG, JPG or WebP. Max 2 MB. Square works best.</p>
                        </div>
                    </div>
                    @if ($branding['logo'])
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> Remove current logo
                        </label>
                    @endif
                    <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                </div>

                <div>
                    <x-input-label value="Favicon" />
                    <div class="mt-2 flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">
                            @if ($branding['favicon'])
                                <img src="{{ $branding['favicon'] }}" alt="Current favicon" class="h-8 w-8 object-contain">
                            @else
                                <span class="text-xs text-gray-400">none</span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <input type="file" name="favicon" accept=".png,.ico,image/png,image/x-icon,image/vnd.microsoft.icon"
                                class="mbui-input file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700">
                            <p class="mt-1 text-xs text-gray-500">PNG or ICO. Max 512 KB. 32×32 or 48×48 px.</p>
                        </div>
                    </div>
                    @if ($branding['favicon'])
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remove_favicon" value="1" class="rounded border-gray-300"> Remove current favicon
                        </label>
                    @endif
                    <x-input-error :messages="$errors->get('favicon')" class="mt-2" />
                </div>

                <div class="sm:col-span-2 flex items-center justify-end border-t border-gray-100 pt-5">
                    <x-mbui.button type="submit">Save branding</x-mbui.button>
                </div>
            </form>
        </x-mbui.card>
    </div>

    <div class="mt-6">
        <x-mbui.card class="p-6">
            <h2 class="mbui-section-label">Email (SMTP) settings</h2>
            <p class="mt-1 text-sm text-gray-500">Used to send order, payment and password-reset emails. Leave the password blank to keep the current one.</p>

            <form method="POST" action="{{ route('admin.settings.mail.update') }}" class="mt-5 space-y-5">
                @csrf

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="mail_mailer" value="Mailer" />
                        <select id="mail_mailer" name="mail_mailer" class="mbui-input mt-1">
                            <option value="smtp" @selected($mailSettings->get('mail_mailer', 'log') === 'smtp')>SMTP (sends real emails)</option>
                            <option value="log" @selected($mailSettings->get('mail_mailer', 'log') === 'log')>Log only (no email sent, for testing)</option>
                        </select>
                        <x-input-error :messages="$errors->get('mail_mailer')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="mail_encryption" value="Encryption" />
                        <select id="mail_encryption" name="mail_encryption" class="mbui-input mt-1">
                            <option value="tls" @selected($mailSettings->get('mail_encryption', 'tls') === 'tls')>TLS</option>
                            <option value="ssl" @selected($mailSettings->get('mail_encryption') === 'ssl')>SSL</option>
                            <option value="" @selected($mailSettings->get('mail_encryption') === '')>None</option>
                        </select>
                        <x-input-error :messages="$errors->get('mail_encryption')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-input-label for="mail_host" value="SMTP host" />
                        <x-text-input id="mail_host" class="mbui-input mt-1" type="text" name="mail_host" :value="$mailSettings->get('mail_host')" placeholder="smtp.gmail.com" />
                        <x-input-error :messages="$errors->get('mail_host')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="mail_port" value="Port" />
                        <x-text-input id="mail_port" class="mbui-input mt-1" type="number" name="mail_port" :value="$mailSettings->get('mail_port', 587)" />
                        <x-input-error :messages="$errors->get('mail_port')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="mail_username" value="Username" />
                        <x-text-input id="mail_username" class="mbui-input mt-1" type="text" name="mail_username" :value="$mailSettings->get('mail_username')" autocomplete="off" />
                        <x-input-error :messages="$errors->get('mail_username')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="mail_password" value="Password" />
                        <x-text-input id="mail_password" class="mbui-input mt-1" type="password" name="mail_password" placeholder="{{ $mailSettings->get('mail_password') ? '••••••••' : '' }}" autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('mail_password')" class="mt-2" />
                        <p class="mt-1 text-xs text-gray-500">Stored encrypted. Leave blank to keep the current password.</p>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="mail_from_address" value="From address" />
                        <x-text-input id="mail_from_address" class="mbui-input mt-1" type="email" name="mail_from_address" :value="$mailSettings->get('mail_from_address')" required placeholder="hello@example.com" />
                        <x-input-error :messages="$errors->get('mail_from_address')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="mail_from_name" value="From name" />
                        <x-text-input id="mail_from_name" class="mbui-input mt-1" type="text" name="mail_from_name" :value="$mailSettings->get('mail_from_name', config('app.name'))" required />
                        <x-input-error :messages="$errors->get('mail_from_name')" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                    <x-mbui.button type="submit">Save email settings</x-mbui.button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="mt-4 border-t border-gray-100 pt-4">
                @csrf
                <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-500">Sends a test email to your own address ({{ auth()->user()->email }}) using the settings above.</p>
                    <x-mbui.button type="submit" variant="secondary">Send test email</x-mbui.button>
                </div>
            </form>
        </x-mbui.card>
    </div>

    <div class="mt-6">
        @if ($settings->isEmpty())
            <x-mbui.card>
                <x-mbui.empty-state title="No settings yet" message="Settings are seeded during setup." />
            </x-mbui.card>
        @else
            <form method="POST" action="{{ route('admin.settings.update') }}" class="mbui-card overflow-hidden">
                @csrf
                <x-mbui.table :title="'Other settings'">
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
