<?php

namespace Extensions\EmailCampaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;

/**
 * One click, one column. Both actions write customers.newsletter, the same
 * field the registration form, the account profile and the admin customer
 * form already write, so a customer who unsubscribes from a mail sees the box
 * unticked in their account and vice versa.
 *
 * Signed routes carry the customer id and a signature, so nothing has to be
 * stored and a link cannot be edited to act on somebody else.
 *
 * The customer id is resolved manually rather than via implicit route-model
 * binding: Laravel's SubstituteBindings middleware runs ahead of the `signed`
 * route middleware in the framework's priority order, so a tampered id for a
 * non-existent customer would otherwise 404 before the signature is even
 * checked, leaking the fact that the tampered id doesn't exist instead of
 * rejecting the request outright with 403.
 */
class NewsletterSubscriptionController extends Controller
{
    public function unsubscribe(int $customer)
    {
        $customer = Customer::findOrFail($customer);
        $customer->update(['newsletter' => false]);

        return view('email-campaigns::newsletter.result', [
            'customer' => $customer,
            'subscribed' => false,
        ]);
    }

    public function resubscribe(int $customer)
    {
        $customer = Customer::findOrFail($customer);
        $customer->update(['newsletter' => true]);

        return view('email-campaigns::newsletter.result', [
            'customer' => $customer,
            'subscribed' => true,
        ]);
    }
}
