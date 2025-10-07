<?php
// database/migrations/2025_10_03_162306_create_ordem_servico_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ordem_servico', function (Blueprint $table) {
            $table->id();
            $table->string('numero_os', 50)->unique();
            $table->enum('status', ['ABERTA', 'EM_ANDAMENTO', 'CONCLUIDA', 'CANCELADA'])->default('ABERTA');
            $table->enum('prioridade', ['BAIXA', 'MEDIA', 'ALTA', 'URGENTE'])->default('MEDIA');

            $table->text('descricao')->nullable();
            $table->text('observacoes')->nullable();

            $table->integer('id_tecnico_responsavel')->unsigned()->nullable();
            $table->integer('id_user_abertura')->unsigned();
            $table->integer('id_user_conclusao')->unsigned()->nullable();

            $table->timestamp('data_abertura')->useCurrent();
            $table->date('data_prevista_conclusao')->nullable();
            $table->timestamp('data_conclusao')->nullable();
            $table->timestamp('data_cancelamento')->nullable();

            $table->index(['status', 'data_abertura']);
            $table->index('numero_os');
        });

        Schema::create('ordem_servico_veiculos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_ordem_servico');
            $table->integer('id')->unsigned();
            $table->unsignedBigInteger('id_rpr')->nullable();

            $table->enum('status_veiculo', ['PENDENTE', 'EM_MANUTENCAO', 'CONCLUIDO', 'PROBLEMA_PERSISTENTE'])->default('PENDENTE');

            $table->text('problemas_identificados')->nullable();
            $table->text('servicos_realizados')->nullable();
            $table->text('observacoes_tecnico')->nullable();

            $table->timestamp('data_inicio_manutencao')->nullable();
            $table->timestamp('data_conclusao_manutencao')->nullable();

            // Foreign key apenas para ordem_servico
            $table->foreign('id_ordem_servico')->references('id')->on('ordem_servico')->onDelete('cascade');

            $table->unique(['id_ordem_servico', 'id']);
            $table->index('status_veiculo');
            $table->index('id');
            $table->index('id_rpr');
        });

        Schema::create('ordem_servico_historico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_ordem_servico');
            $table->integer('id_user')->unsigned();
            $table->string('acao', 100);
            $table->text('detalhes')->nullable();
            $table->timestamp('data_acao')->useCurrent();

            $table->foreign('id_ordem_servico')->references('id')->on('ordem_servico')->onDelete('cascade');

            $table->index(['id_ordem_servico', 'data_acao']);
            $table->index('id_user');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ordem_servico_historico');
        Schema::dropIfExists('ordem_servico_veiculos');
        Schema::dropIfExists('ordem_servico');
    }
};
