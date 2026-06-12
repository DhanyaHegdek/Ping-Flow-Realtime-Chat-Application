<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('bio');
            $table->unsignedBigInteger('storage_used')->default(0)->after('avatar');
            $table->unsignedBigInteger('storage_quota')->default(1073741824)->after('storage_used'); // 1GB default
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'avatar', 'storage_used', 'storage_quota']);
        });
    }
};
