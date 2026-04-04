<x-admin-layout>
    <x-slot name="header">{{ $rule ? 'Edit Rule' : 'Create Rule' }}</x-slot>

    <div class="max-w-2xl">
        <form method="POST" action="{{ $rule ? route('admin.abandoned-carts.rules.update', $rule) : route('admin.abandoned-carts.rules.store') }}" class="space-y-6">
            @csrf
            @if ($rule) @method('PUT') @endif

            <div>
                <label for="email_template_id" class="block text-sm font-medium text-gray-700 mb-1">Email Template</label>
                <select name="email_template_id" id="email_template_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select template...</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" {{ old('email_template_id', $rule?->email_template_id) == $template->id ? 'selected' : '' }}>
                            {{ $template->name }}
                        </option>
                    @endforeach
                </select>
                @error('email_template_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="customer_group_id" class="block text-sm font-medium text-gray-700 mb-1">Customer Group</label>
                <select name="customer_group_id" id="customer_group_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Groups</option>
                    @foreach ($customerGroups as $group)
                        <option value="{{ $group->id }}" {{ old('customer_group_id', $rule?->customer_group_id) == $group->id ? 'selected' : '' }}>
                            {{ $group->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_group_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="max_sends" class="block text-sm font-medium text-gray-700 mb-1">Max Sends</label>
                    <input type="number" name="max_sends" id="max_sends" min="1" max="100" value="{{ old('max_sends', $rule?->max_sends ?? 1) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Per cart, per rule</p>
                    @error('max_sends') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="min_cart_age_hours" class="block text-sm font-medium text-gray-700 mb-1">Min Cart Age (hours)</label>
                    <input type="number" name="min_cart_age_hours" id="min_cart_age_hours" min="1" max="8760" value="{{ old('min_cart_age_hours', $rule?->min_cart_age_hours ?? 1) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">How old before first send</p>
                    @error('min_cart_age_hours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <input type="number" name="priority" id="priority" min="0" max="999" value="{{ old('priority', $rule?->priority ?? 0) }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Higher = checked first</p>
                    @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $rule?->is_active ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="ml-2 text-sm text-gray-700">Active</label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                    {{ $rule ? 'Update Rule' : 'Create Rule' }}
                </button>
                <a href="{{ route('admin.abandoned-carts.rules') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</x-admin-layout>
