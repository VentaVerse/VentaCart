<?php

namespace App\Http\Controllers\Admin\Layout;

/**
 * The editor sent placements and not one of them could be stored.
 *
 * Thrown inside the save transaction so the whole thing rolls back. Reporting
 * "nothing could be placed" while the sweep has already emptied the page is
 * how a merchant learns to distrust every message this screen shows, which is
 * exactly what the old code did.
 */
class RefusedSave extends \RuntimeException
{
}
