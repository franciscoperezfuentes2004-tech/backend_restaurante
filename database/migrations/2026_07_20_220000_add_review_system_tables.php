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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('folio')->nullable()->unique()->after('id');
        });

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('is_approved');
            
            $table->enum('status', ['pendiente', 'aprobada', 'oculta'])->default('pendiente');
            $table->enum('tipo_experiencia', ['restaurante', 'pedido'])->nullable();
            $table->enum('insignia', ['reserva_verificada', 'pedido_verificado', 'visita_restaurante', 'pedido_llevar'])->nullable();
            $table->string('platillo_texto')->nullable();
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('hidden_reason')->nullable();
            $table->unsignedInteger('useful_count')->default(0);
            $table->date('visit_date')->nullable();
            
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('testimonial_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('testimonial_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->enum('status', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonial_images');

        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropForeign(['order_id']);
            $table->dropColumn([
                'status', 'tipo_experiencia', 'insignia', 'platillo_texto', 
                'reply_text', 'replied_at', 'hidden_reason', 'useful_count', 
                'visit_date', 'reservation_id', 'order_id'
            ]);
            $table->boolean('is_approved')->default(false);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('folio');
        });
    }
};
