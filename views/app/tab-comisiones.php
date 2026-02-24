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
?>

<div id="TabComisiones" class="suite-tab-content" style="display: none;">
    
    <!-- 1. CABECERA: RENDIMIENTO PERSONAL -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 40px 20px; text-align: center; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        <h2 style="color: #0f172a; margin-top: 0; margin-bottom: 5px; font-size: 24px;">Mi Rendimiento</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 0;">Comisión acumulada del mes (<span id="dash-mes-actual">Cargando...</span>)</p>
        
        <div id="dash-comision-actual" style="font-size: 56px; font-weight: 900; color: #10b981; margin: 15px 0; letter-spacing: -2px;">
            $0.00
        </div>
        
        <div style="display: inline-block; background: #f8fafc; padding: 6px 12px; border-radius: 20px; font-size: 12px; color: #475569; border: 1px solid #e2e8f0;">
            ℹ️ Tasa base: <strong>1.5%</strong> calculado sobre ventas facturadas y despachadas.
        </div>
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
