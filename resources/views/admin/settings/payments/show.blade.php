<x-admin.settings-shell :title="$name">
    <x-admin.tabs default="config" param="tab" :names="['config', 'availability']">
        <x-slot:nav>
            <x-admin.tabs.tab name="config">Configuration</x-admin.tabs.tab>
            <x-admin.tabs.tab name="availability">Availability</x-admin.tabs.tab>
        </x-slot:nav>

        <x-admin.tabs.panel name="config">
            @include('admin.settings.payments.partials.config-' . str_replace('_', '-', $method))
        </x-admin.tabs.panel>

        <x-admin.tabs.panel name="availability">
            @include('admin.settings.payments.partials.availability')
        </x-admin.tabs.panel>
    </x-admin.tabs>
</x-admin.settings-shell>
