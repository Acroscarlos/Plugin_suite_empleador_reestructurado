<?php
/**
 * Vista: Cerebro de Demanda y Marketing (Módulo 5)
 * 
 * Muestra visualizaciones de BI (Business Intelligence) utilizando Chart.js 
 * y alimentándose del Data Lake (REST API).
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="TabMarketing" class="suite-tab-content" style="display: none;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
        <div>
            <h2 style="margin:0; color:#0f172a; font-size: 24px; font-weight: 800;">📈 Cerebro de Demanda (BI & Marketing)</h2>
            <p style="color:#64748b; margin-top:5px; font-size: 14px;">Análisis de ventas efectivas e impacto publicitario (Últimos 30 días).</p>
        </div>
        <button class="btn-modern-action" onclick="SuiteMarketing.loadDashboard()">🔄 Refrescar Datos</button>
    </div>

    <!-- Grid de Gráficos -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        
        <!-- Tarjeta 1: Gráfico Doughnut (Canales de Venta) -->
        <div style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
            <h3 style="margin-top: 0; color: #1e293b; font-size: 15px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                Distribución por Canal de Venta (Volumen USD)
            </h3>
            <div style="position: relative; height: 320px; width: 100%; margin-top: 15px;">
                <canvas id="chart-canales-venta"></canvas>
            </div>
        </div>

        <!-- Tarjeta 2: Gráfico Lineal (Tendencia de Operaciones) -->
        <div style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
            <h3 style="margin-top: 0; color: #1e293b; font-size: 15px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
                Tendencia de Cierres de Venta en el Tiempo
            </h3>
            <div style="position: relative; height: 320px; width: 100%; margin-top: 15px;">
                <canvas id="chart-tendencia-ventas"></canvas>
            </div>
        </div>

    </div>
</div>
