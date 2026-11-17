<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Themes\Xelga\Documents\SchemeUsage;
use Themes\Xelga\Http\Requests\ReadRequest;

/** Color schemes as the editor shows them: by their colors and the places that use them. */
class SchemeController extends Controller
{
    use RespondsWithJson;

    /** Each scheme id with every section, block and row of columns that uses it. */
    public function usage(ReadRequest $request): JsonResponse
    {
        return $this->ok(['usage' => (object) SchemeUsage::forStore()], 'Loaded.');
    }
}
