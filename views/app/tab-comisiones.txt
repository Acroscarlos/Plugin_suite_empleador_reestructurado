<?php
/**
 * Vista: Dashboard de Comisiones y Gamificación (Módulo 4)
 * 
 * Muestra las ganancias en tiempo real del vendedor y el ranking 
 * de los premios mensuales para fomentar la competencia.
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Instanciar Modelo y solicitar datos del usuario actual
$vendedor_id = get_current_user_id();
$commission_model = new Suite_Model_Commission();
$stats_billetera = $commission_model->get_vendedor_stats( $vendedor_id );

// Validar si el usuario tiene privilegios gerenciales (Zero-Trust UI)
$user_obj = wp_get_current_user();
$roles = (array) $user_obj->roles;
$is_gerencia = current_user_can('manage_options') || in_array('suite_gerente', $roles) || in_array('gerente', $roles);
?>

<div id="TabComisiones" class="suite-tab-content" style="display: none;">
    <div class="suite-header-modern">
        <h2 style="margin:0; font-size: 22px; color: #0f172a;">🏆 Comisiones y Rendimiento</h2>
    </div>

    <div style="padding: 25px;">
        
        <!-- ==========================================
             PANEL GERENCIAL (Solo visible para Admin/Gerencia)
             ========================================== -->
        <?php if ( $is_gerencia ) : ?>
        <div style="margin-bottom: 30px; padding: 20px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="color: #991b1b; margin: 0 0 5px 0; font-size: 16px;">⚙️ Cierre Contable de Mes</h3>
                <p style="color: #7f1d1d; font-size: 13px; margin: 0;">Liquide las comisiones pendientes. Esta acción pasará el Ledger a estado "pagado" y lo congelará permanentemente.</p>
            </div>
            <button id="btn-cierre-mes" class="btn-save-big" style="background-color: #dc2626; width: auto; padding: 10px 20px;">🔒 Ejecutar Cierre de Mes</button>
        </div>
        <?php endif; ?>

        <!-- ==========================================
             BILLETERA DEL VENDEDOR
             ========================================== -->
        <h3 style="color: #0f172a; margin-bottom: 15px; font-size: 18px;">💼 Mi Billetera (Mes Actual)</h3>
        <div style="display: flex; gap: 20px; margin-bottom: 25px;">
            <div class="kpi-card" style="flex: 1; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 20px; border-radius: 12px; color: white;">
                <small style="display:block; text-transform:uppercase; font-weight:bold; opacity: 0.9;">⏳ Comisiones Pendientes</small>
                <strong style="font-size: 32px;">$<?php echo number_format($stats_billetera['totales']['pendiente'], 2); ?></strong>
            </div>
            <div class="kpi-card" style="flex: 1; background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 20px; border-radius: 12px; color: white;">
                <small style="display:block; text-transform:uppercase; font-weight:bold; opacity: 0.9;">✅ Comisiones Pagadas (Liquidado)</small>
                <strong style="font-size: 32px;">$<?php echo number_format($stats_billetera['totales']['pagado'], 2); ?></strong>
            </div>
        </div>

        <h4 style="color: #475569; margin-bottom: 10px;">📄 Últimas Transacciones</h4>
        <div class="suite-table-responsive" style="margin-bottom: 30px;">
            <table class="suite-modern-table" style="width: 100%; text-align: left;">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Orden</th>
                        <th>Venta Total</th>
                        <th>Mi Comisión</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty($stats_billetera['historial']) ) : ?>
                        <tr><td colspan="5" style="text-align:center; padding: 20px; color:#64748b;">No hay comisiones registradas este mes.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $stats_billetera['historial'] as $tx ) : 
                            $badge_bg = $tx->estado_pago === 'pagado' ? '#d1fae5' : '#fef3c7';
                            $badge_cl = $tx->estado_pago === 'pagado' ? '#065f46' : '#92400e';
                        ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($tx->created_at)); ?></td>
                                <td>#<?php echo esc_html($tx->codigo_cotizacion); ?></td>
                                <td>$<?php echo number_format($tx->total_usd, 2); ?></td>
                                <td style="color:#059669;"><strong>+$<?php echo number_format($tx->comision_ganada_usd, 2); ?></strong></td>
                                <td><span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; background:<?php echo $badge_bg; ?>; color:<?php echo $badge_cl; ?>;"><?php echo strtoupper($tx->estado_pago); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- 2. SECCIÓN: GAMIFICACIÓN Y PREMIOS -->
    <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px; color: #111827; display: flex; align-items: center; gap: 10px;">
        🏆 Premios del Mes
    </h3>
    
    <div class="kpi-row" style="display: flex; flex-wrap: wrap; gap: 20px;">
        
        <!-- Tarjeta 1: Pez Gordo -->
        <div class="kpi-card" style="flex: 1; min-width: 250px; border-top: 4px solid #f59e0b; text-align: left;">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <h4 style="margin: 0; color: #0f172a; font-size: 16px;">🦈 Pez Gordo</h4>
                <span class="pill-warn" style="font-size: 12px; font-weight: bold;">Premio: $20</span>
            </div>
            <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Al vendedor con mayor volumen ($) facturado.</p>
            
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                <small style="color: #94a3b8; text-transform: uppercase; font-weight: bold; font-size: 10px;">Líder Actual</small>
                <strong id="pez-gordo-name" style="font-size: 18px; display: block; color: #1e293b; margin-top: 4px;">Calculando...</strong>
                <span id="pez-gordo-amount" style="color: #059669; font-weight: 800; font-size: 15px;">$0.00</span>
            </div>
        </div>

        <!-- Tarjeta 2: Deja pa' los demás -->
        <div class="kpi-card" style="flex: 1; min-width: 250px; border-top: 4px solid #3b82f6; text-align: left;">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <h4 style="margin: 0; color: #0f172a; font-size: 16px;">🏃 Deja pa' los demás</h4>
                <span class="pill-info" style="font-size: 12px; font-weight: bold; background: #dbeafe; color: #1d4ed8;">Premio: $20</span>
            </div>
            <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Al vendedor con mayor cantidad de ventas (N°).</p>
            
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #e2e8f0;">
                <small style="color: #94a3b8; text-transform: uppercase; font-weight: bold; font-size: 10px;">Líder Actual</small>
                <strong id="deja-pa-name" style="font-size: 18px; display: block; color: #1e293b; margin-top: 4px;">Calculando...</strong>
                <span id="deja-pa-count" style="color: #2563eb; font-weight: 800; font-size: 15px;">0 ventas</span>
            </div>
        </div>

        <!-- Tarjeta 3: Dale Play -->
        <div class="kpi-card" style="flex: 1; min-width: 250px; border-top: 4px solid #8b5cf6; text-align: left; background: #faf5ff;">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <h4 style="margin: 0; color: #0f172a; font-size: 16px;">▶️ Dale Play</h4>
                <span style="font-size: 12px; font-weight: bold; background: #ede9fe; color: #6d28d9; padding: 4px 10px; border-radius: 99px;">Premio: $10</span>
            </div>
            <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Por proactividad y cumplimiento en proyectos.</p>
            
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed #ddd6fe; text-align: center;">
                <span style="font-size: 32px; display: block; margin-bottom: 5px;">🎯</span>
                <strong style="color: #5b21b6; font-size: 13px;">Asignación Manual (Admin)</strong>
                <p style="font-size: 11px; color: #7c3aed; margin-top: 2px;">Evaluado a fin de mes según Kanban de Proyectos.</p>
            </div>
        </div>

    </div>
</div>
</div>
