<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rpr_ciclos', function (Blueprint $table) {
            $table->id();
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->enum('status', ['ATIVO', 'CONCLUIDO', 'EXPIRADO'])->default('ATIVO');
            $table->integer('total_veiculos')->default(0);
            $table->integer('inspecionados')->default(0);
            $table->integer('aprovados')->default(0);
            $table->integer('com_problema')->default(0);
            $table->timestamp('data_criacao')->useCurrent();
            $table->integer('id_user_criacao')->nullable(); // INT (compatível com users)

            $table->index(['data_inicio', 'status']);
            $table->index('id_user_criacao');
        });

        Schema::create('rpr_ciclo_veiculos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_ciclo');
            $table->integer('id'); // INT (mesmo tipo da tabela unidades)
            $table->enum('status_inspecao', ['PENDENTE', 'OK', 'COM_PROBLEMA', 'NAO_INSPECIONADO'])->default('PENDENTE');
            $table->unsignedBigInteger('id_rpr')->nullable();
            $table->timestamp('data_inspecao')->nullable();
            $table->integer('id_user_inspecao')->nullable(); // INT
            $table->text('observacao')->nullable();

            $table->foreign('id_ciclo')->references('id')->on('rpr_ciclos')->onDelete('cascade');

            // Índices sem foreign keys para tabelas externas
            $table->index('id');
            $table->index('id_rpr');
            $table->index('id_user_inspecao');

            $table->unique(['id_ciclo', 'id']);
            $table->index(['id_ciclo', 'status_inspecao']);
        });

        // Adiciona campos na tabela RPR
        if (Schema::hasTable('rpr') && !Schema::hasColumn('rpr', 'id_ciclo')) {
            Schema::table('rpr', function (Blueprint $table) {
                $table->unsignedBigInteger('id_ciclo')->nullable()->after('id');
                $table->boolean('aprovado_rpr')->default(false);
                $table->index('id_ciclo');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('rpr')) {
            Schema::table('rpr', function (Blueprint $table) {
                if (Schema::hasColumn('rpr', 'id_ciclo')) {
                    $table->dropIndex(['id_ciclo']);
                    $table->dropColumn(['id_ciclo', 'aprovado_rpr']);
                }
            });
        }

        Schema::dropIfExists('rpr_ciclo_veiculos');
        Schema::dropIfExists('rpr_ciclos');
    }
};
