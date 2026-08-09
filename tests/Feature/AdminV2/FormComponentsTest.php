<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class FormComponentsTest extends TestCase
{
    public function test_field_renders_label_help_and_error(): void
    {
        $this->withViewErrors(['email' => 'The email is invalid.'])
            ->blade('<x-admin.field name="email" label="Email address" help="We never share this."><x-admin.input name="email" /></x-admin.field>')
            ->assertSee('Email address')
            ->assertSee('We never share this.')
            ->assertSee('The email is invalid.');
    }

    public function test_input_keeps_old_value_and_flags_error_state(): void
    {
        $this->withViewErrors(['sku' => 'Taken.'])
            ->blade('<x-admin.input name="sku" value="ABC-1" />')
            ->assertSee('value="ABC-1"', false)
            // The error edge is a state class, not a colour utility: the
            // resting edge moved to --border-control in forms.css so an
            // empty field clears WCAG 1.4.11, and a two-class selector
            // there outranks any single utility the component could write.
            ->assertSee('v-control--error', false);
    }

    public function test_toggle_posts_zero_when_off(): void
    {
        $this->blade('<x-admin.toggle name="active" />')
            ->assertSee('type="hidden"', false)
            ->assertSee('value="0"', false)
            ->assertSee('type="checkbox"', false);
    }

    public function test_radio_group_marks_current_value(): void
    {
        $this->blade('<x-admin.radio-group name="mode" :options="[\'sale\' => \'Sale\', \'override\' => \'Override\']" value="override" />')
            ->assertSee('Sale')->assertSee('Override')
            ->assertSeeInOrder(['value="override"', 'checked'], false);
    }

    public function test_form_actions_renders_sticky_bar(): void
    {
        $this->blade('<x-admin.form-actions><x-admin.button type="submit">Save</x-admin.button></x-admin.form-actions>')
            ->assertSee('v-form-actions', false)->assertSee('Save');
    }
}
