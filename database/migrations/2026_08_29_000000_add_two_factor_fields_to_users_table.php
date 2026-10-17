<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google2fa_secret')->nullable()->after('password');
            $table->boolean('two_factor_enabled')->default(false)->after('google2fa_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google2fa_secret', 'two_factor_enabled', 'two_factor_confirmed_at']);
        });
    }
};