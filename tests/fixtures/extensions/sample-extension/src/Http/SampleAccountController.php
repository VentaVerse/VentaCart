<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Http;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** A page of its own in the customer's account. */
class SampleAccountController extends Controller
{
    public function index(): View
    {
        return view('sample-extension::account');
    }
}
