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
        Schema::create('usuarios_localizacao', function (Blueprint $table) {
            $table->id();

            // Compatível com usuarios.id (int unsigned)
            $table->unsignedInteger('id_usuario');

            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('velocidade', 5, 2)->default(0);
            $table->string('endereco', 500)->nullable();
            $table->decimal('precisao', 8, 2)->nullable();
            $table->enum('tipo_atividade', [
                'EM_DESLOCAMENTO',
                'NO_LOCAL',
                'PARADO',
                'OFFLINE'
            ])->default('OFFLINE');

            // Compatível com unidades.id (int - sem unsigned)
            $table->integer('id_unidade_atual')->nullable();

            $table->timestamp('data_atualizacao')->useCurrent();
            $table->boolean('ativo')->default(true);

            // Foreign keys
            $table->foreign('id_usuario')
                  ->references('id')
                  ->on('usuarios')
                  ->onDelete('cascade');

            $table->foreign('id_unidade_atual')
                  ->references('id')
                  ->on('unidades')
                  ->onDelete('set null');

            // Indexes
            $table->index('id_usuario');
            $table->index('id_unidade_atual');
            $table->index('data_atualizacao');
            $table->index('ativo');
            $table->index(['id_usuario', 'ativo']); // Índice composto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios_localizacao');
    }
};
