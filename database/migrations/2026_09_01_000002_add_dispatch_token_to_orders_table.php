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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('dispatch_token', 10)->nullable()->unique()->index()->after('folio');
        });

        // Generar tokens de despacho para órdenes existentes si las hay
        $orders = DB::table('orders')->whereNull('dispatch_token')->get(['id']);
        $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $charLen = strlen($chars);

        foreach ($orders as $order) {
            $token = '';
            do {
                $token = '';
                for ($i = 0; $i < 6; $i++) {
                    $token .= $chars[random_int(0, $charLen - 1)];
                }
            } while (DB::table('orders')->where('dispatch_token', $token)->exists());

            DB::table('orders')->where('id', $order->id)->update(['dispatch_token' => $token]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('dispatch_token');
        });
    }
};
