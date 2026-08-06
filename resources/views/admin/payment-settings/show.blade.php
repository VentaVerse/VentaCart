<x-admin.shell :title="$name">
    <x-admin.page :title="$name" :breadcrumb="[
        ['label' => 'Settings', 'url' => route('admin.settings.index')],
        ['label' => 'Payments', 'url' => route('admin.payment-settings.index')],
    ]">
        <x-admin.tabs default="config" param="tab">
            <x-slot:nav>
                <x-admin.tabs.tab name="config">Configuration</x-admin.tabs.tab>
                <x-admin.tabs.tab name="availability">Availability</x-admin.tabs.tab>
            </x-slot:nav>

            <x-admin.tabs.panel name="config">
                @include('admin.payment-settings.partials.config-' . str_replace('_', '-', $method))
            </x-admin.tabs.panel>

            <x-admin.tabs.panel name="availability">
                <x-admin.card>
                    <p class="text-[13px] text-v-secondary">Availability rules — configured in the next task.</p>
                </x-admin.card>
            </x-admin.tabs.panel>
        </x-admin.tabs>
    </x-admin.page>
</x-admin.shell>
