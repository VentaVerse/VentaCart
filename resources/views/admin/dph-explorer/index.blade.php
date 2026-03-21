<x-admin-layout>
    <x-slot name="header">DPH API Explorer</x-slot>

    <div x-data="dphExplorer()" class="max-w-7xl mx-auto space-y-6">
        {{-- Endpoint Selector --}}
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium mb-4">API Request</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Endpoint</label>
                    <select x-model="endpoint" @change="loadTemplate()" class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        <option value="login">loginV2 (authenticate)</option>
                        <option value="getPartners">getPartners</option>
                        <option value="getScheduledRates">v3/getAvailablePartners (scheduled rates)</option>
                        <option value="getOnDemandRates">v3/getOnDemandQuotation (on-demand rates)</option>
                        <option value="getPost">getPost</option>
                        <option value="createPost">v3/createPost</option>
                        <option value="cancelPost">v3/cancelPost</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Query Params (JSON)</label>
                    <input type="text" x-model="query" class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm font-mono" placeholder="{}">
                </div>
                <div class="flex items-end">
                    <button @click="send()" :disabled="loading"
                        class="w-full px-4 py-2 rounded-md text-white text-sm font-medium transition-colors"
                        :class="loading ? 'bg-gray-400' : 'bg-indigo-600 hover:bg-indigo-700'">
                        <span x-show="!loading">Send Request</span>
                        <span x-show="loading">Sending...</span>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Request Body (JSON)</label>
                <textarea x-model="body" rows="12" class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm font-mono" placeholder="{}"></textarea>
            </div>
        </div>

        {{-- Response --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">Response</h3>
                <template x-if="lastDuration">
                    <span class="text-xs text-gray-500" x-text="lastDuration + 'ms'"></span>
                </template>
            </div>

            <div x-show="!response && !error" class="text-sm text-gray-400 italic">No response yet. Select an endpoint and click Send Request.</div>

            <template x-if="error">
                <pre class="dph-error-output" x-text="error"></pre>
            </template>

            <template x-if="response">
                <div>
                    <p class="text-xs text-gray-500 mb-2">
                        <span class="font-medium" x-text="'Endpoint: ' + response.endpoint"></span>
                        <span x-text="' | ' + response.duration_ms + 'ms'"></span>
                    </p>
                    <h4 class="text-sm font-medium text-gray-600 mb-1">Request Sent:</h4>
                    <pre class="dph-log-output mb-4" x-text="JSON.stringify(response.request, null, 2)"></pre>
                    <h4 class="text-sm font-medium text-gray-600 mb-1">API Response:</h4>
                    <pre class="dph-response-output" x-text="JSON.stringify(response.response, null, 2)"></pre>
                </div>
            </template>
        </div>

        {{-- API Log --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">API Log (today)</h3>
                <button @click="refreshLogs()" class="px-3 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Refresh
                </button>
            </div>
            <pre class="dph-log-output" x-text="logContent"></pre>
        </div>
    </div>

    @push('scripts')
    <script>
    function dphExplorer() {
        const templates = {
            login: { body: {}, query: {} },
            getPartners: { body: {}, query: {} },
            getScheduledRates: {
                body: {
                    pickup: { province: "metro manila", city: "quezon city", barangay: "katipunan" },
                    delivery: { province: "rizal", city: "antipolo city", barangay: "san juan" },
                    dimensions: { length: 10, width: 10, height: 10 },
                    weight: 2
                },
                query: {}
            },
            getOnDemandRates: {
                body: {
                    pickup: { name: "Store", phone: "09123456789", address: "Katipunan, Quezon City", province: "Metro Manila", lat: 14.6308, lng: 121.0753 },
                    delivery: { name: "Customer", phone: "09987654321", address: "BGC, Taguig", lat: 14.5528, lng: 121.0519 },
                    dimensions: { length: 10, width: 10, height: 10 },
                    weight: 2
                },
                query: {}
            },
            getPost: { body: {}, query: { postId: "" } },
            createPost: {
                body: {
                    refNo: "TEST-" + Date.now(),
                    isPSGC: false,
                    pickupDetails: {
                        senderName: "Test Store", contactNumber: "09123456789", emailAddress: "test@example.com",
                        pickupDateTime: new Date(Date.now() + 3600000).toISOString().slice(0, 16),
                        pickupAddress: "Katipunan, Quezon City", pickupCity: "Quezon City",
                        province: "Metro Manila", barangay: "Katipunan", postalCode: "1105"
                    },
                    deliveryDetails: {
                        recipientName: "Test Customer", contactNumber: "09987654321", emailAddress: "customer@example.com",
                        deliveryDateTime: new Date(Date.now() + 86400000).toISOString().slice(0, 16),
                        deliveryAddress: "San Juan, Antipolo City", deliveryCity: "Antipolo City",
                        province: "Rizal", barangay: "San Juan", postalCode: "1870",
                        productSize: "L", itemDescription: "Guitar effects pedal",
                        weight: 2, length: 10, width: 8, height: 4
                    }
                },
                query: { partnerId: "SANDBOX_QA" }
            },
            cancelPost: {
                body: { postId: "", trackingNumber: "", courierId: "" },
                query: {}
            }
        };

        return {
            endpoint: 'login',
            body: '{}',
            query: '{}',
            response: null,
            error: null,
            loading: false,
            lastDuration: null,
            logContent: @json($logs),

            init() {
                this.loadTemplate();
            },

            loadTemplate() {
                this.response = null;
                this.error = null;
                const t = templates[this.endpoint] || { body: {}, query: {} };
                this.body = JSON.stringify(t.body, null, 2);
                this.query = JSON.stringify(t.query, null, 2);
            },

            async send() {
                this.loading = true;
                this.response = null;
                this.error = null;
                this.lastDuration = null;

                try {
                    const res = await fetch('{{ route("admin.dph-explorer.execute") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            endpoint: this.endpoint,
                            body: this.body,
                            query: this.query
                        })
                    });

                    const data = await res.json();

                    if (data.success) {
                        this.response = data;
                        this.lastDuration = data.duration_ms;
                    } else {
                        this.error = data.error || JSON.stringify(data, null, 2);
                    }
                } catch (e) {
                    this.error = e.message;
                }

                this.loading = false;
                this.refreshLogs();
            },

            async refreshLogs() {
                try {
                    const res = await fetch('{{ route("admin.dph-explorer.logs") }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    this.logContent = data.logs;
                } catch (e) {
                    // silent
                }
            }
        };
    }
    </script>
    @endpush
</x-admin-layout>
