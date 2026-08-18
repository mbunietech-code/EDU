@if (session('success'))
    <x-mbui.alert type="success" class="mb-4">{{ session('success') }}</x-mbui.alert>
@endif

@if (session('error'))
    <x-mbui.alert type="error" class="mb-4">{{ session('error') }}</x-mbui.alert>
@endif

@if ($errors->any())
    <x-mbui.alert type="error" class="mb-4">
        <ul class="list-disc pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-mbui.alert>
@endif