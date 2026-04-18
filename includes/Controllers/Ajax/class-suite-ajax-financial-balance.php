<?php
/**
 * Controlador AJAX: Balance Financiero y Nómina (Módulo Accounts Payable)
 *
 * @package SuiteEmpleados\Controllers\Ajax
 */

// Seguridad: Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

class Suite_Ajax_Financial_Balance extends Suite_AJAX_Controller {
    
    protected $action_name = 'suite_get_financial_balance';
    
    // 🛡️ REGLA ZERO-TRUST: Solo los administradores pueden ver la nómina global
    protected $required_capability = 'manage_options'; 

    protected function process() {
        
        // 1. Recibir parámetros del frontend (Mes y Año), por defecto los actuales
        $mes  = isset($_POST['mes']) ? intval($_POST['mes']) : date('m');
        $anio = isset($_POST['anio']) ? intval($_POST['anio']) : date('Y');

        // 2. Instanciar el modelo que contiene la matemática compleja
        $model = new Suite_Model_Commission();

        // 3. Obtener los bolsillos contables
        $balances = $model->get_global_balances( $mes, $anio );

        // 4. Regla 5: Cálculo de KPIs Globales de Tesorería
        $total_nomina = 0;
        $total_recuperado = 0;
        $participantes_activos = count( $balances );

        foreach ( $balances as $b ) {
            // A. Sumar al Total de Nómina solo si el saldo final es positivo a favor del vendedor
            if ( $b['neto'] > 0 ) {
                $total_nomina += $b['neto'];
            }
            
            // B. Sumar al Total Recuperado buscando los egresos/deducciones en los detalles
            foreach ( $b['detalles'] as $d ) {
                if ( floatval($d['monto']) < 0 ) {
                    $total_recuperado += abs( floatval($d['monto']) );
                }
            }
        }

        // 5. Enviar la bóveda estructurada al Frontend para pintar el Acordeón
        $this->send_success( [
            'kpis' => [
                'total_nomina'     => $total_nomina,
                'total_recuperado' => $total_recuperado,
                'participantes'    => $participantes_activos
            ],
            'vendedores' => $balances
        ] );
    }
}