<?php
/**
 * Modelo de Base de Datos: Comisiones, Metas y Gamificación (Módulo 4)
 *
 * Maneja el cálculo inmutable del 1.5% de comisión y determina a los 
 * ganadores de los premios mensuales ("Pez Gordo", "Deja pa' los demás").
 *
 * @package SuiteEmpleados\Models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Model_Commission extends Suite_Model_Base {

    /**
     * Define la tabla principal del modelo (Ledger de Comisiones)
     */
    protected function set_table_name() {
        return 'suite_comisiones_ledger';
    }

    /**
     * Calcula y registra la comisión de una venta cerrada.
     * Esta función debe ser llamada desde update_order_status() cuando un pedido pase a 'pagado'.
     *
     * @param int   $quote_id    ID de la cotización
     * @param int   $vendedor_id ID del vendedor
     * @param float $total_usd   Monto total de la venta en dólares
     * @return int|false         ID del registro o false si falla
     */
    public function calculate_and_save_commission( $quote_id, $vendedor_id, $total_usd ) {
        // Regla de Negocio: Comisión Fija del 1.5%
        $porcentaje = 0.015;
        $comision   = floatval( $total_usd ) * $porcentaje;

        // Evitar registrar comisiones en $0
        if ( $comision <= 0 ) {
            return false; 
        }

        // Registrar en el Ledger como pendiente de liquidación
        return $this->insert( [
            'quote_id'            => intval( $quote_id ),
            'vendedor_id'         => intval( $vendedor_id ),
            'monto_base_usd'      => floatval( $total_usd ),
            'comision_ganada_usd' => $comision,
            'estado_pago'         => 'pendiente'
        ] );
    }

    /**
     * Analiza la base de datos para determinar los ganadores de la Gamificación.
     * Solo cuenta ventas efectivamente cerradas ('pagado', 'despachado').
     *
     * @param int $mes  Mes a evaluar (1-12)
     * @param int $anio Año a evaluar (ej. 2024)
     * @return array    Array asociativo con los datos de los ganadores
     */
    public function get_gamification_winners( $mes, $anio ) {
        $tabla_cot  = $this->wpdb->prefix . 'suite_cotizaciones';
        $tabla_user = $this->wpdb->prefix . 'users';

        // 1. Ganador "Pez Gordo" ($20): Mayor volumen acumulado en dólares
        $pez_gordo = $this->wpdb->get_row( $this->wpdb->prepare( "
            SELECT c.vendedor_id, u.display_name, SUM(c.total_usd) as total_vendido
            FROM {$tabla_cot} c
            INNER JOIN {$tabla_user} u ON c.vendedor_id = u.ID
            WHERE MONTH(c.fecha_emision) = %d AND YEAR(c.fecha_emision) = %d
            AND c.estado IN ('pagado', 'despachado')
            GROUP BY c.vendedor_id
            ORDER BY total_vendido DESC
            LIMIT 1
        ", intval( $mes ), intval( $anio ) ) );

        // 2. Ganador "Deja pa' los demás" ($20): Mayor cantidad de ventas cerradas
        $deja_pa_los_demas = $this->wpdb->get_row( $this->wpdb->prepare( "
            SELECT c.vendedor_id, u.display_name, COUNT(c.id) as cantidad_ventas
            FROM {$tabla_cot} c
            INNER JOIN {$tabla_user} u ON c.vendedor_id = u.ID
            WHERE MONTH(c.fecha_emision) = %d AND YEAR(c.fecha_emision) = %d
            AND c.estado IN ('pagado', 'despachado')
            GROUP BY c.vendedor_id
            ORDER BY cantidad_ventas DESC
            LIMIT 1
        ", intval( $mes ), intval( $anio ) ) );

        return [
            'pez_gordo'         => $pez_gordo,
            'deja_pa_los_demas' => $deja_pa_los_demas
        ];
    }

    /**
     * Asignación manual de un premio por parte del Administrador (Ej: "Dale play").
     *
     * @param int    $vendedor_id   ID del vendedor premiado
     * @param string $premio_nombre Nombre del premio (Ej: "Dale play")
     * @param float  $monto         Monto en dólares a premiar (Ej: 10.00)
     * @param int    $mes           Mes correspondiente
     * @param int    $anio          Año correspondiente
     * @return int|false
     */
    public function assign_manual_prize( $vendedor_id, $premio_nombre, $monto, $mes, $anio ) {
        $tabla_premios = $this->wpdb->prefix . 'suite_premios_mensuales';

        $inserted = $this->wpdb->insert( $tabla_premios, [
            'vendedor_id'          => intval( $vendedor_id ),
            'mes'                  => intval( $mes ),
            'anio'                 => intval( $anio ),
            'premio_nombre'        => sanitize_text_field( $premio_nombre ),
            'monto_premio'         => floatval( $monto ),
            'asignado_manualmente' => 1
        ] );

        return $inserted ? $this->wpdb->insert_id : false;
    }
}