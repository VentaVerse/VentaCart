{{-- The bar that carries a form's commit. Everything it looks like
     lives in components/forms.css: it used to be written here in
     utilities, with a fixed 24px bleed that overhung the sheet's own
     20px gutter by 4px (8px at 390) on every settings form. The bleed
     now comes from `--v-bleed`, which the container publishes.

     One per page. Two sticky bars pinned to the same edge read as one
     control that moved. --}}
<div {{ $attributes->merge(['class' => 'v-form-actions']) }}>
    {{ $slot }}
</div>
