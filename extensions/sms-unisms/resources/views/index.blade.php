<x-admin.shell title="UniSMS">
    @push('head')
        @vite('extensions/sms-unisms/resources/css/unisms.css')
    @endpush

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>UniSMS</span>
            </div>
            <h1 class="v-head__title">UniSMS</h1>
            <p class="v-head__standing">The gateway text messages are sent through.</p>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="sms-unisms" />
        </div>
    </div>

    {{-- Two tabs, one save: both halves of the form stay inside the same
         x-admin.form, and each tab only hides its half. A required field on
         the hidden tab is revealed by the save bar, which finds tabs by role. --}}
    <div class="v-settle"
         x-data="{ tab: @js(in_array(request('tab'), ['settings', 'messages'], true) ? request('tab') : ($errors->has('templates.*') ? 'messages' : 'settings')),
                   pick(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); history.replaceState(null, '', u); } }">
        <div class="v-tabs" role="tablist">
            <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'settings' ? 'true' : 'false'" @click="pick('settings')">Settings</button>
            <button type="button" class="v-tab" role="tab" :aria-selected="tab === 'messages' ? 'true' : 'false'" @click="pick('messages')">Status and message</button>
        </div>

        <x-admin.form :action="route('admin.extensions.unisms.update')">
            <div x-show="tab === 'settings'" class="v-settle">
                <x-admin.panel title="Credentials">
                    <x-slot:acts>
                        @if ($readiness['ready'])
                            <span class="v-pill v-pill--ok">Ready to send</span>
                        @else
                            <span class="v-pill">{{ $readiness['reason'] }}</span>
                        @endif
                    </x-slot:acts>

                    <x-admin.field name="api_key" label="API key"
                                   :hint="$masked ? 'A key is saved, ending ' . substr($masked, -4) . '. Leave blank to keep it.' : null">
                        <input type="password" name="api_key" autocomplete="off" maxlength="500"
                               class="v-control v-mono"
                               placeholder="{{ $masked ?? 'Paste the key from unismsapi.com' }}">
                    </x-admin.field>

                    <x-admin.field name="sender_id" label="Sender ID" required
                                   hint="Registered with UniSMS. Philippine telcos refuse a message without one; new accounts get a test ID until a custom one is approved.">
                        <input type="text" name="sender_id" maxlength="32" class="v-control v-w-name"
                               value="{{ old('sender_id', $senderId) }}" autocomplete="off">
                    </x-admin.field>

                    <x-admin.field name="base_url" label="API base URL">
                        <input type="url" name="base_url" maxlength="255" class="v-control v-mono"
                               value="{{ old('base_url', $baseUrl) }}">
                    </x-admin.field>

                    <div class="v-gap">
                        <label class="v-toggle">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" @checked($enabled)>
                            <span class="v-toggle__track" aria-hidden="true"></span>
                            <span>Send messages through UniSMS</span>
                        </label>
                    </div>
                </x-admin.panel>
            </div>

            <div x-show="tab === 'messages'" x-cloak class="v-settle">
                {{-- Every template is a set of real fields in this one form, so
                     adding, editing and deleting only arm the save bar, and
                     Discard puts everything back. Only the selected template
                     is shown; the rest stay in the form, hidden. --}}
                <x-admin.panel title="Templates"
                               x-data="{
                                   items: [],
                                   statuses: {{ \Illuminate\Support\Js::from($statuses) }},
                                   errors: {{ \Illuminate\Support\Js::from(collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'templates.'))) }},
                                   cur: null,
                                   seq: 0,
                                   preview: { text: '', chars: 0, segments: 0, unicode: false, busy: false },
                                   _timer: null,
                                   init() {
                                       this.items = {{ \Illuminate\Support\Js::from(array_values($templates)) }}.map((t) => this.shape(t));
                                       const failed = this.items.findIndex((t, i) => Object.keys(this.errors).some((k) => k.startsWith('templates.' + i + '.')));
                                       this.cur = this.items[failed >= 0 ? failed : 0]?.key ?? null;
                                       this.$watch('cur', () => this.render());
                                       this.render();
                                   },
                                   shape(t) {
                                       return { key: 'k' + (++this.seq), id: t.id ? Number(t.id) : null, title: t.title ?? '', message: t.message ?? '',
                                                status_id: t.status_id ? Number(t.status_id) : null };
                                   },
                                   get current() { return this.items.find((t) => t.key === this.cur) ?? null; },
                                   add() {
                                       const t = this.shape({ message: {{ \Illuminate\Support\Js::from(\Extensions\SmsUnisms\UniSms::DEFAULT_TEMPLATE) }} });
                                       this.items.push(t);
                                       this.cur = t.key;
                                       this.$nextTick(() => this.$root.querySelector('[data-title=' + t.key + ']')?.focus());
                                   },
                                   remove(key) {
                                       const at = this.items.findIndex((t) => t.key === key);
                                       this.items.splice(at, 1);
                                       this.errors = {};
                                       this.cur = this.items[Math.min(at, this.items.length - 1)]?.key ?? null;
                                   },
                                   owner(id, key) { return this.items.find((t) => t.key !== key && t.status_id === id)?.title || 'another template'; },
                                   claimed(id, key) { return this.items.some((t) => t.key !== key && t.status_id === id); },
                                   status(id) { return this.statuses.find((s) => s.id === id) ?? null; },
                                   err(i, field) { return (this.errors['templates.' + i + '.' + field] ?? [])[0] ?? null; },
                                   hasErr(i) { return Object.keys(this.errors).some((k) => k.startsWith('templates.' + i + '.')); },
                                   render() {
                                       clearTimeout(this._timer);
                                       const t = this.current;
                                       if (! t) return;
                                       this.preview.busy = true;
                                       this._timer = setTimeout(() => {
                                           const ask = ++this.seq;
                                           fetch({{ \Illuminate\Support\Js::from(route('admin.extensions.unisms.preview')) }}, {
                                               method: 'POST',
                                               headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                                                          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                                               body: JSON.stringify({ message: t.message, status_id: t.status_id }),
                                           }).then((r) => r.ok ? r.json() : null).then((p) => {
                                               if (! p || ask !== this.seq) return;
                                               this.preview = { ...p, busy: false };
                                           }).catch(() => { this.preview.busy = false; });
                                       }, 250);
                                   },
                               }">
                    <x-slot:acts>
                        <span class="v-statuspick__count" aria-live="polite">
                            <strong x-text="items.filter((t) => t.status_id).length"></strong> of {{ count($statuses) }} statuses text
                        </span>
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="add()">Add template</button>
                    </x-slot:acts>

                    @error('templates')
                        <p class="v-field__error v-gap-sm">{{ $message }}</p>
                    @enderror

                    <div class="v-smsbook" x-show="items.length > 0">
                        <div class="v-smsbook__list" role="tablist" aria-label="Templates" aria-orientation="vertical">
                            <template x-for="(t, i) in items" :key="t.key">
                                <button type="button" class="v-smsbook__item" role="tab"
                                        :aria-selected="cur === t.key ? 'true' : 'false'"
                                        :class="{ 'is-invalid': hasErr(i) }"
                                        @click="cur = t.key">
                                    <span class="v-smsbook__title" :class="{ 'is-untitled': ! t.title.trim() }" x-text="t.title.trim() || 'Untitled'"></span>
                                    <span class="v-smsbook__statuses" x-text="status(t.status_id)?.name || 'No status yet'"></span>
                                </button>
                            </template>
                        </div>

                        <div class="v-smsbook__detail">
                            <template x-for="(t, i) in items" :key="t.key">
                                <div x-show="cur === t.key" class="v-smsbook__editor" role="tabpanel">
                                    <input type="hidden" :name="'templates[' + i + '][id]'" :value="t.id ?? ''">

                                    <div class="v-smsbook__top">
                                        <label class="v-field v-smsbook__titlefield">
                                            <span class="v-field__label">Title <span class="v-req">Required</span></span>
                                            <input type="text" class="v-control" maxlength="100" required
                                                   :data-title="t.key"
                                                   :name="'templates[' + i + '][title]'" x-model="t.title">
                                            <span class="v-field__error" x-show="err(i, 'title')" x-text="err(i, 'title')"></span>
                                        </label>
                                        {{-- One status per template, found by typing: a store can
                                             carry thirty-odd statuses, too many to scan as ticks. --}}
                                        <div class="v-field v-smsbook__statusfield"
                                             x-data="{
                                                 open: false, q: '', active: 0,
                                                 get matches() {
                                                     const n = this.q.trim().toLowerCase();
                                                     return statuses.filter((s) => ! n || s.name.toLowerCase().includes(n));
                                                 },
                                                 show() {
                                                     this.open = true;
                                                     this.q = '';
                                                     this.active = Math.max(0, this.matches.findIndex((s) => s.id === t.status_id));
                                                 },
                                                 close() { this.open = false; this.q = ''; },
                                                 pick(s) {
                                                     if (claimed(s.id, t.key)) return;
                                                     t.status_id = s.id;
                                                     this.close();
                                                     render();
                                                 },
                                                 move(step) {
                                                     if (! this.open) { this.show(); return; }
                                                     const n = this.matches.length;
                                                     if (! n) return;
                                                     this.active = (this.active + step + n) % n;
                                                     this.$nextTick(() => this.$refs.list?.querySelector('[data-active=true]')?.scrollIntoView({ block: 'nearest' }));
                                                 },
                                             }"
                                             @click.outside="close()">
                                            <label class="v-field__label" :for="'st-' + t.key">Order status <span class="v-req">Required</span></label>
                                            <input type="hidden" :name="'templates[' + i + '][status_id]'" :value="t.status_id ?? ''">
                                            <div class="v-statuscombo" :class="{ 'is-open': open }">
                                                <span class="v-statuspick__dot v-statuscombo__dot" aria-hidden="true"
                                                      x-show="status(t.status_id)"
                                                      :class="'v-statuspick__dot--' + (status(t.status_id)?.tone ?? 'neutral')"></span>
                                                <input type="text" class="v-control v-statuscombo__input" autocomplete="off"
                                                       :id="'st-' + t.key" role="combobox" aria-autocomplete="list"
                                                       :aria-expanded="open ? 'true' : 'false'" :aria-controls="'stl-' + t.key"
                                                       :aria-activedescendant="open && matches[active] ? 'sto-' + t.key + '-' + matches[active].id : ''"
                                                       :placeholder="open && t.status_id ? status(t.status_id)?.name : 'Search order statuses'"
                                                       :value="open ? q : (status(t.status_id)?.name ?? '')"
                                                       @focus="show()" @click="open || show()"
                                                       @input="q = $event.target.value; open = true; active = 0"
                                                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                                                       @keydown.enter.prevent="open && matches[active] && pick(matches[active])"
                                                       @keydown.escape.prevent="close()" @keydown.tab="close()">
                                                <ul class="v-statuscombo__list" role="listbox" x-ref="list" :id="'stl-' + t.key" x-show="open" x-cloak>
                                                    <template x-for="(s, n) in matches" :key="s.id">
                                                        <li role="option" class="v-statuscombo__opt"
                                                            :id="'sto-' + t.key + '-' + s.id"
                                                            :aria-selected="t.status_id === s.id ? 'true' : 'false'"
                                                            :aria-disabled="claimed(s.id, t.key) ? 'true' : 'false'"
                                                            :data-active="n === active ? 'true' : 'false'"
                                                            @mousedown.prevent="pick(s)" @mousemove="active = n">
                                                            <span class="v-statuspick__dot" :class="'v-statuspick__dot--' + s.tone" aria-hidden="true"></span>
                                                            <span class="v-statuscombo__name" x-text="s.name"></span>
                                                            <span class="v-statuscombo__owner" x-show="claimed(s.id, t.key)" x-text="owner(s.id, t.key)"></span>
                                                        </li>
                                                    </template>
                                                    <li class="v-statuscombo__none" x-show="matches.length === 0">No status matches.</li>
                                                </ul>
                                            </div>
                                            <span class="v-field__error" x-show="err(i, 'status_id')" x-text="err(i, 'status_id')"></span>
                                        </div>
                                        <button type="button" class="v-btn v-btn--ghost v-btn--sm v-smsbook__delete" @click="remove(t.key)">Delete template</button>
                                    </div>

                                    <div class="v-smsbook__compose">
                                        <div>
                                            <label class="v-field">
                                                <span class="v-field__label">
                                                    Message <span class="v-req">Required</span>
                                                    <x-admin.help text="Never a link: Philippine telcos drop texts that carry one." />
                                                </span>
                                                <textarea class="v-control v-smstemplate" rows="5" required
                                                          maxlength="{{ \Extensions\SmsUnisms\UniSms::MAX_LENGTH }}"
                                                          :name="'templates[' + i + '][message]'"
                                                          x-model="t.message" @input="render()"></textarea>
                                                <span class="v-field__error" x-show="err(i, 'message')" x-text="err(i, 'message')"></span>
                                            </label>

                                            <div class="v-chiprow" role="group" aria-label="Placeholders">
                                                @foreach (\Extensions\SmsUnisms\UniSms::PLACEHOLDERS as $placeholder)
                                                    <button type="button" class="v-chip" title="Copy"
                                                            @click="vCopy({{ \Illuminate\Support\Js::from('{' . $placeholder . '}') }}).then(ok => { if (!ok) return; const s = $el; const c = s.textContent; s.textContent = 'Copied'; setTimeout(() => s.textContent = c, 1500); })">{{ '{' . $placeholder . '}' }}</button>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- Rendered by the server with the method that sends, so
                                             every space and line break here is one the customer gets. --}}
                                        <figure class="v-smsphone" aria-label="Preview with a sample order">
                                            <figcaption class="v-smsphone__from">
                                                <strong>{{ config('app.name') }}</strong>
                                                <span>Sample order #10482</span>
                                            </figcaption>
                                            <div class="v-smsphone__bubble" :class="{ 'is-busy': preview.busy }" x-text="preview.text"></div>
                                            <div class="v-smsphone__meter" aria-live="polite">
                                                <span x-show="preview.unicode" class="v-smsphone__unicode">Unicode</span>
                                                <span :class="{ 'is-over': preview.segments > 1 }">
                                                    <strong x-text="preview.chars"></strong> characters
                                                    · <strong x-text="preview.segments"></strong>
                                                    <span x-text="preview.segments === 1 ? 'text' : 'texts'"></span>
                                                </span>
                                            </div>
                                        </figure>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="v-smsbook__empty" x-show="items.length === 0">
                        <p>No SMS templates yet.</p>
                        <button type="button" class="v-btn v-btn--primary v-btn--sm" @click="add()">Add template</button>
                    </div>
                </x-admin.panel>
            </div>
        </x-admin.form>

        <div x-show="tab === 'settings'" class="v-settle">
            {{-- An action, not a record edit, so it posts through vPost like every
                 other one-shot button in the admin. Wrapping it in a v form would
                 give it a Save, which is not what this button does. --}}
            <x-admin.panel title="Check the connection">
                <x-slot:acts>
                    <div x-data="{ busy: false }">
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                                @disabled(! $masked)
                                :disabled="busy"
                                @click="busy = true; vPost(@js(route('admin.extensions.unisms.test'))).finally(() => busy = false)">
                            <span x-text="busy ? 'Checking…' : 'Test connection'">Test connection</span>
                        </button>
                    </div>
                </x-slot:acts>
                <p class="v-gap-sm">Asks UniSMS for this account. Costs nothing and sends no message.</p>
            </x-admin.panel>

            <x-admin.panel title="Send a test message">
                <div x-data="{ busy: false, recipient: '', content: @js('Test from ' . config('app.name') . '.') }">
                    <x-admin.field name="recipient" label="To"
                                       hint="E.164, with the plus and country code. UniSMS refuses a local 09 number.">
                        <input type="tel" x-model="recipient" maxlength="20" class="v-control v-mono v-w-name"
                               placeholder="+639171234567">
                    </x-admin.field>

                    <x-admin.field name="content" label="Message">
                        <textarea x-model="content" rows="3" maxlength="{{ \Extensions\SmsUnisms\UniSms::MAX_LENGTH }}"
                                  class="v-control"></textarea>
                    </x-admin.field>

                    <div class="v-gap v-actrow">
                        <button type="button" class="v-btn v-btn--secondary"
                                @disabled(! $readiness['ready'])
                                :disabled="busy || ! recipient.trim() || ! content.trim()"
                                @click="busy = true; vPost(@js(route('admin.extensions.unisms.send-test')), { recipient, content }).finally(() => busy = false)">
                            <span x-text="busy ? 'Sending…' : 'Send'">Send</span>
                        </button>
                    </div>
                </div>
            </x-admin.panel>
        </div>
    </div>
</x-admin.shell>
