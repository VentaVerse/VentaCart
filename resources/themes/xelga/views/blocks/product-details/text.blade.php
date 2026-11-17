{{-- A text panel the merchant writes, such as a size guide (rich text, cleaned again here before it is printed). --}}
@if (! $panel['empty'])
    <div class="rte" data-setting="body">{!! \Themes\Xelga\Settings\RichTextPolicy::clean($panel['settings']['body']) ?? '' !!}</div>
@else
    @include('xelga::snippets.editor-note', ['note' => 'Write this panel\'s text, or shoppers do not see it.'])
@endif
