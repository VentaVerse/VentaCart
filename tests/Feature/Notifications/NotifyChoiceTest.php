<?php

namespace Tests\Feature\Notifications;

use App\Support\NotifyChoice;
use Tests\TestCase;

class NotifyChoiceTest extends TestCase
{
    public function test_follow_defers_both_halves_to_the_store(): void
    {
        $choice = NotifyChoice::follow();

        $this->assertNull($choice->email);
        $this->assertNull($choice->channels);
    }

    public function test_an_explicit_choice_states_both_halves(): void
    {
        $choice = NotifyChoice::explicit(true, ['sms']);

        $this->assertTrue($choice->email);
        $this->assertSame(['sms'], $choice->channels);
    }

    public function test_the_halves_are_independent(): void
    {
        $this->assertTrue(NotifyChoice::emailAlways()->email);
        $this->assertNull(NotifyChoice::emailAlways()->channels, 'SMS still follows the store.');

        $this->assertFalse(NotifyChoice::emailHandledElsewhere()->email);
        $this->assertNull(NotifyChoice::emailHandledElsewhere()->channels);

        $this->assertFalse(NotifyChoice::silent()->email);
        $this->assertSame([], NotifyChoice::silent()->channels);
    }

    /**
     * emailHandledElsewhere() suppresses the status email because the
     * caller sends its own (checkout's order confirmation, HitPay's payment
     * confirmation). That caller still told the customer, and apply() needs
     * to know that to stamp notify_customer correctly on the history row.
     */
    public function test_only_email_handled_elsewhere_says_the_customer_was_told_some_other_way(): void
    {
        $this->assertTrue(NotifyChoice::emailHandledElsewhere()->toldCustomerElsewhere());

        $this->assertFalse(NotifyChoice::follow()->toldCustomerElsewhere());
        $this->assertFalse(NotifyChoice::explicit(true, ['sms'])->toldCustomerElsewhere());
        $this->assertFalse(NotifyChoice::emailAlways()->toldCustomerElsewhere());
        $this->assertFalse(NotifyChoice::silent()->toldCustomerElsewhere());
    }
}
