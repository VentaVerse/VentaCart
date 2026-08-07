<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class FeedbackComponentsTest extends TestCase
{
    public function test_toasts_container_bridges_session_flash(): void
    {
        session()->flash('success', 'Product saved.');
        $this->blade('<x-admin.toasts />')
            ->assertSee('Product saved.')
            ->assertSee('$store.toasts', false);
    }

    public function test_modal_renders_hidden_with_name_binding(): void
    {
        $this->blade('<x-admin.modal name="edit-brand" title="Edit brand">Body</x-admin.modal>')
            ->assertSee('Edit brand')->assertSee('Body')
            ->assertSee('open-modal', false)->assertSee('x-cloak', false);
    }

    public function test_confirm_dialog_wraps_a_spoofed_form(): void
    {
        $this->blade('<x-admin.confirm-dialog name="del" title="Delete?" message="Gone forever." confirm="Delete" action="/admin/things/1" method="DELETE" />')
            ->assertSee('Gone forever.')
            ->assertSee('name="_method"', false)
            ->assertSee('value="DELETE"', false);
    }
}
