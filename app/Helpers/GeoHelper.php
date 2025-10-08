<?php

namespace App\Helpers;

class GeoHelper
{
    /**
     * Calcular distância entre duas coordenadas usando a fórmula de Haversine
     *
     * @param float $lat1 Latitude do ponto 1
     * @param float $lon1 Longitude do ponto 1
     * @param float $lat2 Latitude do ponto 2
     * @param float $lon2 Longitude do ponto 2
     * @return float Distância em metros
     */
    public static function calcularDistancia($lat1, $lon1, $lat2, $lon2): float
    {
        $raioTerra = 6371000; // Raio da Terra em metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distancia = $raioTerra * $c;

        return round($distancia, 2);
    }

    /**
     * Verificar se duas coordenadas estão próximas
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @param int $raioMaximo Raio máximo em metros (padrão: 500m)
     * @return bool
     */
    public static function estaProximo($lat1, $lon1, $lat2, $lon2, $raioMaximo = 500): bool
    {
        $distancia = self::calcularDistancia($lat1, $lon1, $lat2, $lon2);
        return $distancia <= $raioMaximo;
    }
}
