<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('extractions', function (Blueprint $table) {
            $table->string('truck_plate_number')->nullable()->after('extraction_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extractions', function (Blueprint $table) {
            $table->dropColumn('truck_plate_number');
        });
    }
};
