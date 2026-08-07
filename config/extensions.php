<?php

return [

    /*
    |--------------------------------------------------------------------
    | Extensions Path
    |--------------------------------------------------------------------
    |
    | Where ExtensionManager scans for extension.json manifests
    | (extensions/{name}/extension.json). Overridden in tests to point
    | at tests/fixtures/extensions so kernel tests never touch real
    | extension folders.
    |
    */

    'path' => base_path('extensions'),

];
