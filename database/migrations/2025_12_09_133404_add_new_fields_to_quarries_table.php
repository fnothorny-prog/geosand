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
        Schema::table('quarries', function (Blueprint $table) {
            $table->string('permit_number', 50)->nullable()->after('name');
            $table->string('address', 255)->nullable()->after('longitude');
            $table->date('permit_expiry')->nullable()->after('address');
            $table->decimal('area', 10, 4)->nullable()->after('permit_expiry')->comment('Area in hectares');
            $table->json('polygon')->nullable()->after('area')->comment('Polygon coordinates for quarry boundary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quarries', function (Blueprint $table) {
            $table->dropColumn(['permit_number', 'address', 'permit_expiry', 'area', 'polygon']);
        });
    }
};
