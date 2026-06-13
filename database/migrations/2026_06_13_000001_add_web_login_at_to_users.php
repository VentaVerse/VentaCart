<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timestamp of the admin's last *full* login. Drives the absolute session cap:
 * the EnforceAdminSessionCap middleware forces re-login once
 * now - web_login_at > auth.session_policy.admin.absolute_cap_minutes.
 *
 * It lives on the user (not the session) so the cap is anchored to the original
 * login and survives a remember-cookie re-authentication — making the 24h cap
 * truly absolute, not slidable by returning before the session idles out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('web_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('web_login_at');
        });
    }
};
