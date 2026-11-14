{{-- The widget picker, opened in the place the widget will go. Picking one
     adds it at once; a widget that needs a choice (an image, a banner group)
     opens its settings straight after. --}}
<div class="v-pick" role="dialog" aria-modal="false" aria-label="Add a widget to {{ $label }}"
     @keydown.escape.stop="closePicker()">
    <p class="v-pick__intro">A widget is one block of content, such as a banner, a product grid or a text block. Pick one to add it here.</p>
    <div class="v-pick__grid">
        <template x-for="w in offerable(adding?.region)" :key="w.key">
            <button type="button" class="v-pickcard" @click="add(w)">
                <span class="v-wire" :class="'v-wire--' + (w.shape || 'block')" aria-hidden="true">
                    <i></i><i></i><i></i><i></i><i></i><i></i>
                </span>
                <span class="v-pickcard__nm" x-text="w.title"></span>
            </button>
        </template>
    </div>
    <p class="v-slot__empty" x-show="offerable(adding?.region).length === 0">No widget fits this position.</p>
    <button type="button" class="v-btn v-btn--ghost v-btn--sm v-pick__cancel" @click="closePicker()">Cancel</button>
</div>
