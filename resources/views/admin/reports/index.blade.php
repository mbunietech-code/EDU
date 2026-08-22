<x-layouts.admin title="Reports" header="Reports">

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('admin.reports.revenue') }}" class="mbui-card group p-6 transition hover:shadow-md">
            <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600">Revenue</h3>
            <p class="mt-1 text-sm text-gray-500">Monthly approved revenue. Current month: TZS {{ number_format($metrics['monthly_revenue']) }}</p>
            <x-currency-conversion :amount="$metrics['monthly_revenue']" class="mt-1 text-xs font-semibold text-gray-600" />
        </a>
        <a href="{{ route('admin.reports.orders') }}" class="mbui-card group p-6 transition hover:shadow-md">
            <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600">Orders</h3>
            <p class="mt-1 text-sm text-gray-500">Order volume by status and month.</p>
        </a>
        <a href="{{ route('admin.reports.products') }}" class="mbui-card group p-6 transition hover:shadow-md">
            <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600">Products</h3>
            <p class="mt-1 text-sm text-gray-500">Order and subscription counts per product.</p>
        </a>
        <a href="{{ route('admin.reports.accounts') }}" class="mbui-card group p-6 transition hover:shadow-md">
            <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600">Accounts</h3>
            <p class="mt-1 text-sm text-gray-500">Account inventory by status.</p>
        </a>
        <a href="{{ route('admin.reports.subscriptions') }}" class="mbui-card group p-6 transition hover:shadow-md">
            <h3 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600">Subscriptions</h3>
            <p class="mt-1 text-sm text-gray-500">Subscription distribution by status.</p>
        </a>
    </div>

</x-layouts.admin>