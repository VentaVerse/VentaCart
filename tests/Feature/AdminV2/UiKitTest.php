<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The UI kit is the enforcement surface for the shared admin layer: every
 * promoted primitive, in its real states, on one page.
 *
 * Both tests below tightened when the foundation sweep promoted the
 * dashboard's and the fulfillment queue's private vocabularies into the
 * shared layer. The old assertion checked seven section headings from the
 * v2.0 kit and nothing else, so a primitive could be dropped from the page
 * without a single test noticing. It now pins the promoted INVENTORY by
 * name, and separately pins that each primitive's own class actually
 * renders, which is what makes this page a check rather than a gallery.
 */
class UiKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_kit_renders_every_promoted_primitive_section(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.ui-kit'))
            ->assertOk()
            ->assertSeeInOrder([
                'Buttons',
                'Badges',
                'Segment bar',
                'Toolbar and filters',
                'Table, many rows',
                'Table, one row',
                'Table, no rows',
                'Forms',
                'Workspace rail',
                'Slates',
                'Switches',
                'Checklist',
                'Picker',
                'Selectable cards',
                'Feedback',
                'Empty and unconfigured',
                'Pagers',
                'Data display',
                'Measures and plots',
                'Card',
            ]);
    }

    /**
     * One primitive family per class name. If the shared layer loses a
     * shape, the kit stops rendering it and this fails.
     */
    public function test_ui_kit_renders_each_promoted_primitive_class(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('admin.ui-kit'))->assertOk();

        foreach ([
            'v-day__head',      // page head band
            'v-panel__head',    // panel and sheet
            'v-stat--',         // figure, filled
            'v-money',          // figure, money
            'v-facts',          // figure, readings
            'v-table',          // table
            'v-table--tiered',  // table, two tiers
            'v-segment__item',  // segment bar
            'v-toolbar',        // toolbar
            'v-filters',        // filters
            'v-tally',          // tally
            'v-pager__count',   // pager, stepped
            'v-pager__page',    // pager, numbered
            'v-line__count',    // list row, count and destination
            'v-item__thumb',    // list row, thing with a picture
            'v-auto__name',     // list row, name and state
            'v-feed__row',      // feed
            'v-choice__card',   // selectable card
            'v-empty__title',   // empty state
            'v-notice__title',  // unconfigured state
            'v-flash__text',    // flash
            'v-rank__bar',      // data display
            'v-slice-0',        // data display, identity accents
            'v-sidenav__link',  // workspace rail
            'v-sidenav__picker',// workspace rail, narrow
            'v-slate__mark',    // slate
            'v-slate__acts',    // slate, carrying controls
            'v-slate__link',    // slate, a line that is a destination
            'v-check',          // the target around a checkbox
            'v-check--bare',    // the same, with no words to widen it
            'v-badgebtn',       // a badge that is the control flipping it
            'v-badge__x',       // a chip's remove control
            'v-form-actions',   // the bar that carries a form's commit
            'v-switch__text',   // switch, with its words
            'v-toggle--btn',    // switch, button driven
            'v-checklist',      // checklist
            'v-secret__row',    // a stored key, masked and read only at rest
            'v-secret__box',    // the box the key never leaves
            'v-secret__acts',   // its View and Edit controls
            'v-picker__opt',    // picker, an option
            'v-picker__mid',    // picker, the traffic between the panes
            'v-day__title',     // page head, the page's own name
            'v-day__trail',     // page head, the ancestor trail
            'v-tile__art',      // tile
            'v-drop__text',     // tile grid, the drag overlay
            'v-req',            // field, the required mark
            'v-cellstack',      // table cell, facts stacked
            'v-lead',           // table cell, the lead line
            'v-band--pulse',    // the comparison band
            'v-fig__cell',      // the comparison band's measures
            'v-change--ahead',  // the change word, up
            'v-change--behind', // the change word, down
            'v-change--level',  // the change word, flat
            'v-plot__key',      // the plot
            'v-ring__seg',      // the share ring
            'v-stroke-0',       // the ring's identity strokes
            'v-chan__q',        // the part-to-whole chips
            'v-usual',          // the one-sentence reading
            'v-settle',         // the page stagger
        ] as $class) {
            $response->assertSee($class, false);
        }
    }

    /**
     * Every state a control can be in besides resting, so a theme change
     * or a token repoint cannot quietly break one that no screen happens
     * to be showing at the time.
     */
    public function test_ui_kit_renders_the_control_states(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.ui-kit'))
            ->assertOk()
            ->assertSee('data-loading="true"', false)
            ->assertSee('v-btn-spinner', false)
            ->assertSee('disabled', false)
            ->assertSee('is-active', false)
            ->assertSee('is-disabled', false)
            ->assertSee('is-current', false)
            ->assertSee('v-money--zero', false)
            // The switch and the segment both key state on an attribute
            // rather than a class, so the CSS and the screen reader read
            // the same thing. Pin both ends of each.
            ->assertSee('aria-checked="true"', false)
            ->assertSee('aria-checked="false"', false)
            ->assertSee('aria-current="page"', false)
            // The picker keys its highlight on aria-selected for the same
            // reason. Both ends again.
            ->assertSee('aria-selected="true"', false)
            ->assertSee('aria-selected="false"', false);
    }
}
