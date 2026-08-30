<x-layouts.admin title="Contact Message" header="Contact Message">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $contactMessage->subject }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $contactMessage->created_at->format('d M Y H:i') }}</p>
        </div>
        <a href="{{ route('admin.contact-messages.index') }}" class="mbui-anchor text-sm">Back to messages</a>
    </div>

    <div class="mt-6 max-w-2xl">
        <x-mbui.card class="p-6">
            <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <dt class="mbui-section-label">From</dt>
                    <dd class="mt-1 font-medium text-gray-900">{{ $contactMessage->name }}</dd>
                </div>
                <div>
                    <dt class="mbui-section-label">Email</dt>
                    <dd class="mt-1 font-medium text-gray-900">
                        <a href="mailto:{{ $contactMessage->email }}" class="mbui-anchor">{{ $contactMessage->email }}</a>
                    </dd>
                </div>
            </dl>

            <div class="mt-4 border-t border-gray-100 pt-4">
                <dt class="mbui-section-label">Message</dt>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $contactMessage->message }}</p>
            </div>

            <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4">
                <a href="mailto:{{ $contactMessage->email }}?subject=Re: {{ $contactMessage->subject }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Reply by email
                </a>
                <x-mbui.reasoned-action :action="route('admin.contact-messages.destroy', $contactMessage)" method="DELETE" label="Delete" prompt-text="Why are you deleting this message?" class="text-sm font-medium text-red-600 hover:text-red-800" />
            </div>
        </x-mbui.card>
    </div>

</x-layouts.admin>
