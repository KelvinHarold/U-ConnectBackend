<?php
// database/migrations/2024_01_01_000000_add_audience_to_announcements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('audience', ['all', 'buyers', 'sellers'])->default('all')->after('status');
            $table->index('audience');
        });
    }

    public function down()
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('audience');
            $table->dropIndex(['audience']);
        });
    }
};