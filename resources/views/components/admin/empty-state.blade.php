@props(['icon' => 'info', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-12 px-6']) }}>
    <div class="w-10 h-10 rounded-v-lg bg-v-sunken flex items-center justify-center text-v-muted">
        <x-admin.icon :name="$icon" class="w-5 h-5" />
    </div>
    <h3 class="mt-3 text-[15px] font-semibold text-v-text">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-[13px] text-v-secondary">{{ $description }}</p>@endif
    @if (trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
</div>
