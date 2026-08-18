<x-abandoned-carts::space title="Abandoned Cart Settings" active="settings">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Abandoned Carts</span>
                <span aria-hidden="true">/</span>
                <span>Settings</span>
            </div>
            <h1 class="v3-head__title">Abandoned Carts</h1>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch name="abandoned-carts" />
        </div>
    </div>


    <x-admin3.form :action="route('admin.abandoned-carts.settings.update')">
        <div class="v3-settle">
            <x-admin3.panel title="Recovery emails"
                note="The cron works through the rules and mails customers whose carts went quiet.">
                <div>
                    <label class="v3-toggle">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" @checked($enabled)>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        <span>Send recovery emails</span>
                    </label>
                </div>
                <div class="v3-gap">
                    <label class="v3-toggle">
                        <input type="hidden" name="skip_out_of_stock" value="0">
                        <input type="checkbox" name="skip_out_of_stock" value="1" @checked($skipOutOfStock)>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        <span>Skip carts whose every item is out of stock</span>
                    </label>
                </div>
            </x-admin3.panel>

            <x-admin3.panel title="Scheduling">
                <div class="v3-actrow">
                    <a href="{{ route('admin.settings.section', 'automations') }}" class="v3-btn v3-btn--secondary v3-btn--sm">Open Automations</a>
                    <x-admin3.help text="Notifications fire through the Venta scheduler (cart:notify). If your crontab still runs cart:notify directly, remove that line or every cart is notified twice." />
                </div>
            </x-admin3.panel>
        </div>
    </x-admin3.form>
</x-abandoned-carts::space>
