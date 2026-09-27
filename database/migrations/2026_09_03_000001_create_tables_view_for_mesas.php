<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS tables;');
            DB::statement('CREATE VIEW tables AS SELECT * FROM mesas;');
        } else {
            DB::statement('CREATE OR REPLACE VIEW tables AS SELECT * FROM mesas;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP VIEW IF EXISTS tables;');
        } else {
            DB::statement('DROP VIEW IF EXISTS tables CASCADE;');
        }
    }
};
