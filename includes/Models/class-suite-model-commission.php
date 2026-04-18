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
     * Analiza la base de datos para determinar los ganadores de la Gamificación.
     * Solo cuenta ventas efectivamente cerradas ('pagado', 'despachado').
     *
     * @param int $mes  Mes a evaluar (1-12)
     * @param int $anio Año a evaluar (ej. 2024)
     * @return array    Array asociativo con los datos de los ganadores
     */
	public function get_gamification_winners( $mes, $anio ) {
        $tabla_ledger   = $this->wpdb->prefix . 'suite_comisiones_ledger';
        $tabla_user     = $this->wpdb->prefix . 'users';
        $tabla_usermeta = $this->wpdb->usermeta;

        // 1. Ganador "Pez Gordo" ($20): Mayor volumen acumulado (Ledger + Filtro Elegibilidad)
        $pez_gordo = $this->wpdb->get_row( $this->wpdb->prepare( "
		
		
            SELECT l.vendedor_id, u.display_name, SUM(l.monto_base_usd) as total_vendido
            FROM {$tabla_ledger} l
            INNER JOIN {$tabla_user} u ON l.vendedor_id = u.ID
            INNER JOIN {$tabla_usermeta} um ON l.vendedor_id = um.user_id
            WHERE l.estado_pago IN ('pendiente', 'pagado') 
              AND MONTH(l.created_at) = %d 
              AND YEAR(l.created_at) = %d 
              AND l.monto_base_usd > 0
			  
			  
			  
              AND um.meta_key = 'suite_participa_comisiones' AND um.meta_value = '1'
            GROUP BY l.vendedor_id
            ORDER BY total_vendido DESC
            LIMIT 1
        ", intval( $mes ), intval( $anio ) ) );

        // 2. Ganador "Deja pa' los demás" ($20): Mayor cantidad de ventas (Ledger + Filtro Elegibilidad)
        $deja_pa_los_demas = $this->wpdb->get_row( $this->wpdb->prepare( "
            SELECT l.vendedor_id, u.display_name, COUNT(l.id) as cantidad_ventas
            FROM {$tabla_ledger} l
            INNER JOIN {$tabla_user} u ON l.vendedor_id = u.ID
            INNER JOIN {$tabla_usermeta} um ON l.vendedor_id = um.user_id
            WHERE l.estado_pago IN ('pendiente', 'pagado')
              AND MONTH(l.created_at) = %d 
              AND YEAR(l.created_at) = %d 
              AND l.monto_base_usd > 0
              AND um.meta_key = 'suite_participa_comisiones' AND um.meta_value = '1'
            GROUP BY l.vendedor_id
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

	
	
	/**
     * LOGÍSTICA INVERSA UNIVERSAL (FASE 5.4)
     * Anula o deduce la comisión de un pedido devuelto (B2B o Interno).
     */
    public function reverse_commission( $quote_id ) {
        if ( empty( $quote_id ) ) return;

        // Buscamos todas las líneas contables asociadas a esta orden
        $registros = $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE quote_id = %d AND estado_pago != 'anulado'",
            intval( $quote_id )
        ) );

        if ( empty( $registros ) ) return;

        foreach ( $registros as $row ) {
            // Protección de Idempotencia: Omitir si ya es una deducción negativa
            if ( floatval( $row->comision_ganada_usd ) < 0 ) {
                continue;
            }

            if ( $row->estado_pago === 'pendiente' ) {
                // ESCENARIO A: Comisión Pendiente -> Soft Delete (Anulado)
                // Usamos el método update de tu modelo para mantener la integridad
                $this->wpdb->update(
                    $this->table_name,
                    [ 'estado_pago' => 'anulado' ],
                    [ 'id' => $row->id ],
                    [ '%s' ],
                    [ '%d' ]
                );

            } elseif ( $row->estado_pago === 'pagado' ) {
                // ESCENARIO B: Comisión Pagada -> Inyección de Contra-asiento
                $nota_deduccion = "Deducción por Logística Inversa - Orden #{$row->quote_id}";
                
                // Detectamos si era B2B leyendo la nota original
                if ( ! empty($row->notas) && strpos( $row->notas, 'B2B' ) !== false ) {
                    $nota_deduccion .= " (Reverso Aliado B2B)";
                }

                $this->insert([
                    'quote_id'            => $row->quote_id,
                    'vendedor_id'         => $row->vendedor_id,
                    'monto_base_usd'      => -abs(floatval( $row->monto_base_usd )),
                    'comision_ganada_usd' => -abs(floatval( $row->comision_ganada_usd )),
                    'estado_pago'         => 'pendiente',
                    'notas'               => $nota_deduccion
                ]);
            }
        }
    }

    /**
     * ADJUDICACIÓN DE PREMIOS (Dinero Real)
     * Liquida los premios de Gamificación inyectándolos en el Ledger como dólares reales.
     * 
     * @param int $mes Mes que se acaba de cerrar
     * @param int $anio Año del cierre
     */
    public function award_monthly_prizes( $mes, $anio ) {
        $winners = $this->get_gamification_winners( $mes, $anio );

        // 1. Premio: Pez Gordo ($20)
        if ( ! empty( $winners['pez_gordo'] ) ) {
            $this->insert([
                'quote_id'            => 0, // 0 indica que no proviene de una venta, es un bono
                'vendedor_id'         => $winners['pez_gordo']->vendedor_id,
                'monto_base_usd'      => 0,
                'comision_ganada_usd' => 20.00,
                'estado_pago'         => 'pendiente'
            ]);
        }

        // 2. Premio: Deja pa' los demás ($20)
        if ( ! empty( $winners['deja_pa_los_demas'] ) ) {
            $this->insert([
                'quote_id'            => 0,
                'vendedor_id'         => $winners['deja_pa_los_demas']->vendedor_id,
                'monto_base_usd'      => 0,
                'comision_ganada_usd' => 20.00,
                'estado_pago'         => 'pendiente'
            ]);
        }
    }
	
	
	/**
     * FASE 5 (ARMONIZADA): Registra la comisión al momento del despacho, 
     * validando fraude por recibo duplicado y soportando ventas compartidas al 1.5%.
     */
    public function registrar_comision_despacho( $quote_id, $vendedor_id, $monto_base_usd, $recibo_loyverse, $colaboradores = [] ) {
        global $wpdb;
        $tabla_ledger = $wpdb->prefix . 'suite_comisiones_ledger';

        $recibo_limpio = sanitize_text_field( trim( $recibo_loyverse ) );

        if ( empty( $recibo_limpio ) ) {
            return new WP_Error( 'fraude_loyverse', 'El número de recibo Loyverse es obligatorio para comisionar.' );
        }

        // 1. BLINDAJE ANTI-DUPLICIDAD: ¿Este recibo ya entró al Ledger en un despacho anterior?
        // Nota: Si es una venta compartida, entrarán 2 registros a la vez al final de esta función, 
        // por lo que revisar una sola vez al principio es 100% seguro y no bloquea a los colaboradores.
        $existe_recibo = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tabla_ledger} WHERE recibo_loyverse = %s LIMIT 1",
            $recibo_limpio
        ) );

        if ( $existe_recibo ) {
            if ( function_exists('suite_record_log') ) {
                suite_record_log( 'alerta_fraude', "Intento de duplicidad de comisión. Recibo #{$recibo_limpio} ya existe (ID: {$existe_recibo})." );
            }
            return new WP_Error( 'fraude_loyverse', "FRAUDE DETECTADO: El recibo Loyverse #{$recibo_limpio} ya fue registrado." );
        }

        // 2. REGLA DE NEGOCIO: Comisión Fija del 1.5%
        $porcentaje = 0.015;
        $comision_total = floatval( $monto_base_usd ) * $porcentaje;

        if ( $comision_total <= 0 ) return false;

        // 3. ARMAR EL POOL DE BENEFICIARIOS (Titular + Colaboradores)
        $beneficiarios = [ intval( $vendedor_id ) ];
        
        if ( is_array( $colaboradores ) && !empty( $colaboradores ) ) {
            foreach ( $colaboradores as $colab_id ) {
                $colab_id = intval( $colab_id );
                if ( $colab_id > 0 && ! in_array( $colab_id, $beneficiarios ) ) {
                    $beneficiarios[] = $colab_id;
                }
            }
        }

        // 4. EJECUTAR DIVISIÓN FINANCIERA
        $cantidad_vendedores = count( $beneficiarios );
        $base_dividida       = round( floatval( $monto_base_usd ) / $cantidad_vendedores, 2 );
        $comision_dividida   = round( $comision_total / $cantidad_vendedores, 2 );

        // 5. INSERCIÓN MÚLTIPLE EN EL LEDGER
        $inserted_ids = [];
        foreach ( $beneficiarios as $ben_id ) {
            $insertado = $wpdb->insert( $tabla_ledger, [
                'quote_id'            => intval( $quote_id ),
                'vendedor_id'         => $ben_id,
                'monto_base_usd'      => $base_dividida,
                'comision_ganada_usd' => $comision_dividida,
                'recibo_loyverse'     => $recibo_limpio,
                'estado_pago'         => 'pendiente',
                'estado_auditoria'    => 'pendiente' // Nace pendiente para Fase 5.2
            ] );

            if ( $insertado ) {
                $inserted_ids[] = $wpdb->insert_id;
            }
        }

        if ( empty( $inserted_ids ) ) {
            return new WP_Error( 'db_error', 'Error interno al registrar la comisión en el Ledger.' );
        }

        return $inserted_ids;
    }
	
	
	public function get_global_balances( $mes, $anio ) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'suite_comisiones_ledger'; // Nombre correcto 

        $start_date = sprintf( "%04d-%02d-01 00:00:00", $anio, $mes );
        $end_date   = date( "Y-m-t 23:59:59", strtotime( $start_date ) );

		
		
		
        // CORRECCIÓN FORENSE: Solo extraemos el dinero que legítimamente está "pendiente" de pago o descuento.
        // Ignoramos por completo los registros "pagados" y "anulados".
        $transacciones = $wpdb->get_results( $wpdb->prepare( "
            SELECT id, vendedor_id, quote_id, comision_ganada_usd, estado_auditoria, created_at
            FROM {$tabla}
            WHERE created_at BETWEEN %s AND %s
            AND estado_pago = 'pendiente'
            ORDER BY created_at ASC
        ", $start_date, $end_date ) );
		
		
		
		

        $balances = array();
        foreach ( $transacciones as $tx ) {
            $v_id = $tx->vendedor_id;
            if ( ! isset( $balances[$v_id] ) ) {
                $balances[$v_id] = [
                    'vendedor_nombre' => get_userdata($v_id)->display_name,
                    'neto' => 0,
                    'advertencia_auditoria' => false,
                    'detalles' => []
                ];
            }

            $monto = floatval( $tx->comision_ganada_usd );
            $balances[$v_id]['neto'] += $monto;

            if ( in_array( $tx->estado_auditoria, ['incongruente', 'pendiente'] ) ) {
                $balances[$v_id]['advertencia_auditoria'] = true;
            }

            $concepto = ($tx->quote_id > 0) ? "Comisión Orden #{$tx->quote_id}" : "Bono / Ajuste Manual";
            
            $balances[$v_id]['detalles'][] = [
                'fecha' => date( 'd/m/Y', strtotime($tx->created_at) ),
                'concepto' => $concepto,
                'monto' => $monto,
                'estado_auditoria' => $tx->estado_auditoria
            ];
        }
        return array_values( $balances );
    }
	
}