<x-layouts.admin title="Alerts" header="Alerts">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Tahadhari (Email &amp; SMS)</h1>
            <p class="mt-1 text-sm text-gray-500">Scan ya kila siku ikigundua jambo la haraka, admins wanapata ujumbe kwa email na SMS (Beem Africa). Ujumbe ule ule hautumwi tena ndani ya saa 24.</p>
        </div>
        <a href="{{ route('admin.optimization.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">&larr; AI Optimization</a>
    </div>

    @php $input = 'mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

    <form method="POST" action="{{ route('admin.alerts.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <div class="mbui-card space-y-4 p-6">
            <h2 class="text-base font-semibold text-gray-900">Wapokeaji</h2>
            <div>
                <label class="text-sm font-medium text-gray-700">Emails (tenganisha kwa koma)</label>
                <input type="text" name="alert_emails" value="{{ old('alert_emails', $values['alert_emails']) }}" class="{{ $input }}" placeholder="Ukiacha wazi, zinatumwa kwa super admins wote">
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700">Namba za simu (tenganisha kwa koma)</label>
                <input type="text" name="alert_phones" value="{{ old('alert_phones', $values['alert_phones']) }}" class="{{ $input }}" placeholder="0712345678, 255755123456">
            </div>
        </div>

        <div class="mbui-card space-y-4 p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Beem Africa (SMS)</h2>
                <x-mbui.badge appearance="{{ $smsConfigured ? 'success' : 'warning' }}">{{ $smsConfigured ? 'Imeunganishwa' : 'Haijawekwa' }}</x-mbui.badge>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="text-sm font-medium text-gray-700">API Key</label>
                    <input type="text" name="beem_api_key" value="{{ old('beem_api_key', $values['beem_api_key']) }}" class="{{ $input }}" autocomplete="off">
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Secret Key</label>
                    <input type="password" name="beem_secret_key" class="{{ $input }}" autocomplete="new-password" placeholder="{{ $smsConfigured ? '•••••• (imehifadhiwa — acha wazi kuiweka)' : '' }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Sender ID</label>
                    <input type="text" name="beem_sender_id" value="{{ old('beem_sender_id', $values['beem_sender_id']) }}" class="{{ $input }}" maxlength="20">
                </div>
            </div>
        </div>

        <div class="mbui-card space-y-4 p-6">
            <h2 class="text-base font-semibold text-gray-900">Vizingiti (0 = zima)</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="text-sm font-medium text-gray-700">Accounts zilizoisha (≥)</label>
                    <input type="number" min="0" name="alert_expired_min" value="{{ old('alert_expired_min', $values['alert_expired_min']) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Zinazoisha siku 7 (≥)</label>
                    <input type="number" min="0" name="alert_expiring_min" value="{{ old('alert_expiring_min', $values['alert_expiring_min']) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Ukubwa wa DB MB (≥)</label>
                    <input type="number" min="0" name="alert_storage_mb" value="{{ old('alert_storage_mb', $values['alert_storage_mb']) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700">Hitilafu zisizotatuliwa (≥)</label>
                    <input type="number" min="0" name="alert_errors_min" value="{{ old('alert_errors_min', $values['alert_errors_min']) }}" class="{{ $input }}">
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <x-mbui.button type="submit" variant="primary">Hifadhi</x-mbui.button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.alerts.test') }}" class="mt-4">
        @csrf
        <x-mbui.button type="submit" variant="secondary">Tuma ujumbe wa majaribio</x-mbui.button>
        <p class="mt-1 text-xs text-gray-400">Hifadhi mabadiliko kwanza, kisha bonyeza hapa kuthibitisha email/SMS zinafika.</p>
    </form>

</x-layouts.admin>
