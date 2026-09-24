{{-- Confirmation dialog for in-call actions (end session, remove participant, delete message). --}}
<div x-show="confirm.open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
    role="alertdialog" aria-modal="true" aria-labelledby="classroom-confirm-title" aria-describedby="classroom-confirm-body">
    <div class="fixed inset-0 bg-black/60" @click="closeConfirm()" aria-hidden="true"></div>
    <div x-show="confirm.open" x-transition class="relative w-full max-w-md rounded-xl bg-white p-6 text-left text-gray-900 shadow-xl"
        @keydown.tab="
            const f = [...$el.querySelectorAll('button')];
            if ($event.shiftKey && document.activeElement === f[0]) { $event.preventDefault(); f[f.length - 1].focus(); }
            else if (!$event.shiftKey && document.activeElement === f[f.length - 1]) { $event.preventDefault(); f[0].focus(); }">
        <div class="flex gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                @include('learn.rooms.partials.icon', ['name' => 'warning'])
            </div>
            <div class="min-w-0">
                <h2 id="classroom-confirm-title" class="break-words text-base font-semibold" x-text="confirm.title"></h2>
                <p id="classroom-confirm-body" class="mt-1 text-sm text-gray-600" x-text="confirm.body"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" x-ref="confirmCancel" @click="closeConfirm()"
                class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
            <button type="button" @click="runConfirm()"
                class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500"
                x-text="confirm.confirmLabel || 'Confirm'"></button>
        </div>
    </div>
</div>
