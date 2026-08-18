<?php

namespace Extensions\ErpSync;

use Illuminate\Support\ServiceProvider;

/**
 * The ERP push ENDPOINTS as an extension. The API framework stays core
 * (erp.auth middleware, the token setting, sync logging, the shared
 * image-fetch and logging traits) because other extensions' endpoints
 * ride it too — the reviews extension's push endpoint among them.
 * Disabling this extension closes the product/variant/order/catalog
 * endpoints; the framework and the logs page stay.
 */
class ErpSyncServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'erp-sync');
    }
}
