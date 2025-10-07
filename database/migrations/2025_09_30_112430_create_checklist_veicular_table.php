<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_veicular', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id');
            $table->unsignedBigInteger('id_rpr')->nullable();
            $table->unsignedBigInteger('id_user_analise');
            $table->dateTime('data_analise');
            $table->enum('status_geral', ['PENDENTE', 'EM_ANALISE', 'AGUARDANDO_PECAS', 'EM_MANUTENCAO', 'APROVADO', 'REPROVADO'])->default('PENDENTE');

            // Módulo de Rastreamento
            $table->enum('modulo_rastreador', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('modulo_rastreador_obs')->nullable();
            $table->enum('sirene', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('sirene_obs')->nullable();
            $table->enum('leitor_ibutton', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('leitor_ibutton_obs')->nullable();

            // Acessórios
            $table->enum('camera', ['OK', 'PROBLEMA', 'NAO_INSTALADO', 'NAO_VERIFICADO'])->nullable();
            $table->text('camera_obs')->nullable();
            $table->enum('tomada_usb', ['OK', 'PROBLEMA', 'NAO_INSTALADO', 'NAO_VERIFICADO'])->nullable();
            $table->text('tomada_usb_obs')->nullable();
            $table->enum('wifi', ['OK', 'PROBLEMA', 'NAO_INSTALADO', 'NAO_VERIFICADO'])->nullable();
            $table->text('wifi_obs')->nullable();

            // Sensores
            $table->enum('sensor_velocidade', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('sensor_velocidade_obs')->nullable();
            $table->enum('sensor_rpm', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('sensor_rpm_obs')->nullable();
            $table->enum('antena_gps', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('antena_gps_obs')->nullable();
            $table->enum('antena_gprs', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('antena_gprs_obs')->nullable();

            // Instalação
            $table->enum('instalacao_eletrica', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('instalacao_eletrica_obs')->nullable();
            $table->enum('fixacao_equipamento', ['OK', 'PROBLEMA', 'NAO_VERIFICADO'])->nullable();
            $table->text('fixacao_equipamento_obs')->nullable();

            // Conclusão
            $table->text('observacoes_gerais')->nullable();
            $table->date('data_prevista_conclusao')->nullable();
            $table->boolean('finalizado')->default(false);
            $table->dateTime('data_finalizacao')->nullable();
            $table->unsignedBigInteger('id_user_finalizacao')->nullable();

            // Índices
            $table->index('id');
            $table->index('id_rpr');
            $table->index('status_geral');
            $table->index('finalizado');
            $table->index('data_analise');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_veicular');
    }
};
