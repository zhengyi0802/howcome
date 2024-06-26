<x-shop::layouts.account>
    <!-- Page Title -->
    <x-slot:title>
        @lang('shop::app.customers.account.downlines.name')
    </x-slot>

    <div class="flex-auto">
        <div class="max-md:max-w-full">
            <h2 class="text-2xl font-medium">
                @lang('shop::app.customers.account.downlines.name')
            </h2>

            {!! view_render_event('bagisto.shop.customers.account.downlines.list.before') !!}

            <x-shop::datagrid :src="route('shop.customers.account.downlines.index')" />

            {!! view_render_event('bagisto.shop.customers.account.downlines.list.after') !!}

        </div>
    </div>
</x-shop::layouts.account>
