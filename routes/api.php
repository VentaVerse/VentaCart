<?php

// The ERP push endpoints live in their extensions now: products,
// variants, orders, and catalog lookups in erp-sync
// (extensions/erp-sync/routes/api.php); the reviews push in reviews
// (extensions/reviews/routes/api.php). The API FRAMEWORK stays core:
// the erp.auth middleware, the token setting, sync logging, and the
// shared controller traits in App\Http\Controllers\Api.
