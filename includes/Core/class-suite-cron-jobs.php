<?php
/**
 * Clase Gestora de Tareas Programadas (Cron Jobs)
 * 
 * Módulo 5: Data Lake y Cerebro de Demanda.
 * Recopila series de tiempo (Time Series) para modelos predictivos.
 *
 * @package SuiteEmpleados\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Suite_Cron_Jobs {

    public function __construct() {
        add_action( 'suite_daily_inventory_snapshot', [ $this, 'take_inventory_snapshot' ] );
        // NUEVO: Hook para el archivo semanal
        add_action( 'suite_weekly_archive_orders', [ $this, 'archive_dispatched_orders' ] );
    }

    public function schedule_events() {
        if ( ! wp_next_scheduled( 'suite_daily_inventory_snapshot' ) ) {
            wp_schedule_event( strtotime( 'midnight' ), 'daily', 'suite_daily_inventory_snapshot' );
        }
        
        // NUEVO: Programación para todos los domingos a las 23:00:00 (Usa el intervalo 'weekly' nativo de WP 5.4+)
        if ( ! wp_next_scheduled( 'suite_weekly_archive_orders' ) ) {
            wp_schedule_event( strtotime( 'next sunday 23:00:00', current_time( 'timestamp' ) ), 'weekly', 'suite_weekly_archive_orders' );
        }
    }

    public function clear_events() {
        $timestamp = wp_next_scheduled( 'suite_daily_inventory_snapshot' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'suite_daily_inventory_snapshot' );
        }

        // NUEVO: Limpieza del evento semanal al desactivar el plugin
        $timestamp_weekly = wp_next_scheduled( 'suite_weekly_archive_orders' );
        if ( $timestamp_weekly ) {
            wp_unschedule_event( $timestamp_weekly, 'suite_weekly_archive_orders' );
        }
    }

    /**
     * NUEVO MÉTODO: Limpieza Semanal del Kanban (Domingos 11:00 PM)
     * Pasa todas las tarjetas "Despachadas" al archivo histórico.
     */
    public function archive_dispatched_orders() {
        global $wpdb;
        $tabla_cot = $wpdb->prefix . 'suite_cotizaciones';

        // Actualización atómica en base de datos
        $afectados = $wpdb->query( "UPDATE {$tabla_cot} SET estado = 'archivado' WHERE estado = 'despachado'" );

        // Registrar en la tabla de Logs de Seguridad/Auditoría
        if ( function_exists( 'suite_record_log' ) && $afectados > 0 ) {
            suite_record_log( 'archivo_semanal', "Limpieza Kanban: {$afectados} pedidos despachados fueron movidos al archivo histórico." );
        }
    }

    /**
     * ETL (Extract, Transform, Load) Diario.
     * Lee el stock actual e inserta el snapshot en el Data Lake.
     */
    public function take_inventory_snapshot() {
        global $wpdb;

        $tabla_cache     = $wpdb->prefix . 'suite_inventario_cache';
        $tabla_historico = $wpdb->prefix . 'suite_inventario_historico';
        $hoy             = date( 'Y-m-d' );

        // 1. Control de Idempotencia: Evitar snapshots duplicados si el cron se dispara doble
        $existe_hoy = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tabla_historico} WHERE fecha_snapshot = %s",
            $hoy
        ) );

        if ( $existe_hoy > 0 ) {
            return; 
        }

        // 2. Extraer el inventario por lotes (Chunking) para evitar Memory Leaks
        $limit = 500;
        $offset = 0;

        while ( true ) {
            $inventario = $wpdb->get_results( $wpdb->prepare(
                "SELECT sku, stock_gale, precio FROM {$tabla_cache} LIMIT %d OFFSET %d",
                $limit, $offset
            ) );

            if ( empty( $inventario ) ) {
                break; // Fin de los registros
            }

            // 3. Transformación y Carga en Base de Datos
            foreach ( $inventario as $item ) {
                $sku = strtoupper( sanitize_text_field( $item->sku ) );

                $categoria = 'General';
                if ( strpos( $sku, 'UT' ) === 0 ) {
                    $categoria = 'UNI-T';
                } elseif ( strpos( $sku, 'HM' ) === 0 || strpos( $sku, 'HIK' ) === 0 || strpos( $sku, 'DS' ) === 0 ) {
                    $categoria = 'HIKMICRO';
                } elseif ( strpos( $sku, 'RV' ) === 0 ) {
                    $categoria = 'RV TECH';
                }

                $wpdb->insert(
                    $tabla_historico,
                    [
                        'fecha_snapshot'   => $hoy,
                        'sku'              => $sku,
                        'stock_disponible' => intval( $item->stock_gale ),
                        'precio'           => floatval( $item->precio ),
                        'categoria'        => $categoria
                    ],
                    [ '%s', '%s', '%d', '%f', '%s' ]
                );
            }
            // Avanzar al siguiente lote liberando memoria
            $offset += $limit;
        }

        // 4. Auditoría
        if ( function_exists( 'suite_record_log' ) ) {
            suite_record_log( 'cron_snapshot_ia', "Time Series generada exitosamente para la fecha: {$hoy}" );
        }
    }
}