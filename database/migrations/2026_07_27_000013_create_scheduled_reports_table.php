<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->enum('frequency', ['diario', 'semanal', 'mensual'])->default('diario');
            $table->string('day_of_week')->nullable();
            $table->integer('day_of_month')->nullable();
            $table->string('time')->default('23:30');
            $table->json('emails');
            $table->enum('format', ['pdf', 'excel', 'csv'])->default('pdf');
            $table->boolean('active')->default(true);
            $table->timestamp('next_run')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
