<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('checklist_veicular', function (Blueprint $table) {
            // Adicionar técnico responsável
            $table->integer('id_tecnico_responsavel')->nullable()->after('id_user_analise');

            // Adicionar campo de fotos (JSON array com paths)
            $table->json('fotos')->nullable()->after('observacoes_gerais');

            // Remover campos antigos que não serão mais usados
            $table->dropColumn([
                'sensor_velocidade',
                'sensor_velocidade_obs',
                'sensor_rpm',
                'sensor_rpm_obs',
                'antena_gps',
                'antena_gps_obs',
                'antena_gprs',
                'antena_gprs_obs',
                'instalacao_eletrica',
                'instalacao_eletrica_obs',
                'fixacao_equipamento',
                'fixacao_equipamento_obs',
            ]);
        });
    }

    public function down()
    {
        Schema::table('checklist_veicular', function (Blueprint $table) {
            $table->dropColumn(['id_tecnico_responsavel', 'fotos']);

            // Recriar campos removidos (caso precise reverter)
            $table->enum('sensor_velocidade', ['OK', 'PROBLEMA', 'NAO_INSTALADO', 'NAO_VERIFICADO'])->default('NAO_VERIFICADO');
            $table->text('sensor_velocidade_obs')->nullable();
            // ... (outros campos)
        });
    }
};
