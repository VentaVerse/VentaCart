<x-abandoned-carts::space title="Abandoned Cart Settings" active="settings">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Abandoned Carts</span>
                <span aria-hidden="true">/</span>
                <span>Settings</span>
            </div>
            <h1 class="v-head__title">Abandoned Carts</h1>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="abandoned-carts" />
        </div>
    </div>


    <x-admin.form :action="route('admin.abandoned-carts.settings.update')">
        <div class="v-settle">
            <x-admin.panel title="Recovery emails"
                note="The cron works through the rules and mails customers whose carts went quiet.">
                <div>
                    <label class="v-toggle">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" @checked($enabled)>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        <span>Send recovery emails</span>
                    </label>
                </div>
                <div class="v-gap">
                    <label class="v-toggle">
                        <input type="hidden" name="skip_out_of_stock" value="0">
                        <input type="checkbox" name="skip_out_of_stock" value="1" @checked($skipOutOfStock)>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        <span>Skip carts whose every item is out of stock</span>
                    </label>
                </div>
            </x-admin.panel>

            <x-admin.panel title="Scheduling">
                <div class="v-actrow">
                    <a href="{{ route('admin.settings.section', 'automations') }}" class="v-btn v-btn--secondary v-btn--sm">Open Automations</a>
                    <x-admin.help text="Notifications fire through the Venta scheduler (cart:notify). If your crontab still runs cart:notify directly, remove that line or every cart is notified twice." />
                </div>
            </x-admin.panel>
        </div>
    </x-admin.form>
</x-abandoned-carts::space>
