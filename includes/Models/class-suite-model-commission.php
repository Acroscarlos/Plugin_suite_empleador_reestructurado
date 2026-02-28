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
     * Calcula y registra la comisión dividiendo equitativamente entre los beneficiarios.
     * ARCHIVO: includes/Models/class-suite-model-commission.php
     * 
     * @param int $quote_id ID de la cotización
     * @param int $vendedor_id ID del vendedor titular
     * @param float $total_usd Monto total de la venta
     * @param array $colaboradores Array con los IDs de los vendedores secundarios
     * @return true|false
     */
    public function calculate_and_save_commission( $quote_id, $vendedor_id, $total_usd, $colaboradores = [] ) {
        // Regla de Negocio: Comisión Fija del 1.5%
        $porcentaje = 0.015;
        $comision_total = floatval( $total_usd ) * $porcentaje;

        if ( $comision_total <= 0 ) return false;

        // 1. Armar el pool unificado de beneficiarios (Titular + Colaboradores)
        $beneficiarios = [ intval( $vendedor_id ) ];
        
        if ( is_array( $colaboradores ) && !empty( $colaboradores ) ) {
            foreach ( $colaboradores as $colab_id ) {
                $colab_id = intval( $colab_id );
                // Evitar IDs inválidos o duplicar al titular
                if ( $colab_id > 0 && ! in_array( $colab_id, $beneficiarios ) ) {
                    $beneficiarios[] = $colab_id;
                }
            }
        }

        // 2. Ejecutar división financiera
        $cantidad_vendedores = count( $beneficiarios );
        $base_dividida       = round( floatval( $total_usd ) / $cantidad_vendedores, 2 );
        $comision_dividida   = round( $comision_total / $cantidad_vendedores, 2 );

        // 3. Insertar registro en el Ledger por cada vendedor
        foreach ( $beneficiarios as $ben_id ) {
            $this->insert( [
                'quote_id'            => intval( $quote_id ),
                'vendedor_id'         => $ben_id,
                'monto_base_usd'      => $base_dividida,
                'comision_ganada_usd' => $comision_dividida,
                'estado_pago'         => 'pendiente' // Se liquida a fin de mes
            ] );
        }

        return true;
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
	

	
	
	/**
     * Obtiene las estadísticas financieras (Billetera) de un vendedor.
     * 
     * @param int $vendedor_id ID del vendedor.
     * @param int|null $mes Mes a consultar.
     * @param int|null $anio Año a consultar.
     * @return array Billetera estructurada (Totales y Últimas 10 Transacciones)
     */
    public function get_vendedor_stats( $vendedor_id, $mes = null, $anio = null ) {
        $mes = $mes ? intval( $mes ) : intval( date( 'm' ) );
        $anio = $anio ? intval( $anio ) : intval( date( 'Y' ) );

        $tabla_ledger = $this->table_name;
        $tabla_cot    = $this->wpdb->prefix . 'suite_cotizaciones';

        // 1. Agrupación Contable (Totales por Estado)
        $sql_totales = $this->wpdb->prepare("
            SELECT estado_pago, SUM(comision_ganada_usd) as total
            FROM {$tabla_ledger}
            WHERE vendedor_id = %d AND MONTH(created_at) = %d AND YEAR(created_at) = %d
            GROUP BY estado_pago
        ", $vendedor_id, $mes, $anio);

        $resultados_totales = $this->wpdb->get_results( $sql_totales );

        $totales = [ 'pendiente' => 0.00, 'pagado' => 0.00 ];
        foreach ( $resultados_totales as $row ) {
            if ( isset( $totales[ $row->estado_pago ] ) ) {
                $totales[ $row->estado_pago ] = floatval( $row->total );
            }
        }

        // 2. Historial de Transacciones (Últimas 10 del mes)
        $sql_historial = $this->wpdb->prepare("
            SELECT l.comision_ganada_usd, l.estado_pago, l.created_at, c.codigo_cotizacion, c.total_usd
            FROM {$tabla_ledger} l
            LEFT JOIN {$tabla_cot} c ON l.quote_id = c.id
            WHERE l.vendedor_id = %d AND MONTH(l.created_at) = %d AND YEAR(l.created_at) = %d
            ORDER BY l.id DESC
            LIMIT 10
        ", $vendedor_id, $mes, $anio);

        $historial = $this->wpdb->get_results( $sql_historial );

        return [
            'totales'   => $totales,
            'historial' => $historial
        ];
    }	
	
	
	
	
	
}