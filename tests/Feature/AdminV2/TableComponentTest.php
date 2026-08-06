<?php

namespace Tests\Feature\AdminV2;

use Tests\TestCase;

class TableComponentTest extends TestCase
{
    public function test_table_renders_head_rows_and_footer(): void
    {
        $this->blade(<<<'BLADE'
            <x-admin.table>
                <x-slot:head><x-admin.table.th>Name</x-admin.table.th></x-slot:head>
                <x-admin.table.row><x-admin.table.cell>Ernie Ball</x-admin.table.cell></x-admin.table.row>
                <x-slot:footer>PAGINATION</x-slot:footer>
            </x-admin.table>
            BLADE)
            ->assertSeeInOrder(['Name', 'Ernie Ball', 'PAGINATION'])
            ->assertSee('overflow-x-auto', false);
    }

    public function test_sortable_header_toggles_direction(): void
    {
        $this->get('/'); // seed a request so url()->current() works
        $this->blade('<x-admin.table.th sortable field="name">Name</x-admin.table.th>', [])
            ->assertSee('sort=name', false)
            ->assertSee('dir=asc', false);
    }

    public function test_mono_cell_uses_numeric_font(): void
    {
        $this->blade('<x-admin.table.cell mono>₱2,450</x-admin.table.cell>')
            ->assertSee('v-num', false);
    }
}
