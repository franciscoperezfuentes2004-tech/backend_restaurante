<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('folio')->nullable()->unique()->index()->after('id');
            $table->string('status')->default('pending')->change();
        });

        // Iterate over all existing reservations and update their folio to RES-XXXX format
        DB::table('reservations')->orderBy('id')->chunkById(100, function ($reservations) {
            foreach ($reservations as $reservation) {
                $folio = 'RES-' . str_pad($reservation->id, 4, '0', STR_PAD_LEFT);
                DB::table('reservations')
                    ->where('id', $reservation->id)
                    ->update(['folio' => $folio]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('folio');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending')->change();
        });
    }
};
