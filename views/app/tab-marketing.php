<?php
/**
 * Vista: Cerebro de Demanda y Marketing (Módulo 5)
 * * Muestra visualizaciones de BI (Business Intelligence) utilizando Chart.js 
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
            <p style="color:#64748b; margin-top:5px; font-size: 14px;">Inteligencia Predictiva, Velocidad de Ventas y Rendimiento Publicitario.</p>
        </div>
        <button class="btn-modern-action" onclick="SuiteMarketing.loadDashboard()">🔄 Refrescar Datos</button>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 4px solid #f59e0b; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <h4 style="margin:0 0 5px 0; color:#475569; font-size:12px; text-transform:uppercase;">🔥 Gancho Publicitario Ideal</h4>
            <div id="bi-estrella-nombre" style="font-size:15px; font-weight:bold; color:#0f172a; margin-bottom:5px;">Cargando...</div>
            <div style="font-size:12px; color:#64748b;">Producto con mayores ingresos en los últimos 30 días.</div>
        </div>

        <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 4px solid #ef4444; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <h4 style="margin:0 0 5px 0; color:#475569; font-size:12px; text-transform:uppercase;">⚠️ Urge Crear Oferta</h4>
            <div id="bi-sobrestock-nombre" style="font-size:15px; font-weight:bold; color:#0f172a; margin-bottom:5px;">Cargando...</div>
            <div style="font-size:12px; color:#64748b;">En riesgo de obsolescencia. <span id="bi-sobrestock-qty" style="font-weight:bold; color:#ef4444;">0</span> unidades paradas.</div>
        </div>
        
        <div style="background: #fff; padding: 20px; border-radius: 12px; border-left: 4px solid #8b5cf6; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <h4 style="margin:0 0 5px 0; color:#475569; font-size:12px; text-transform:uppercase;">💡 Combo Sugerido (IA)</h4>
            <div id="bi-combo-sugerido" style="font-size:14px; font-weight:bold; color:#0f172a; line-height:1.4; margin-top: 10px;">Cargando estrategia...</div>
        </div>
    </div>
    
    <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
        <h3 style="margin-top: 0; color: #1e293b; font-size: 15px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
            🎯 Top 5: Enfoque Promociones Publicitarias (Ads)
        </h3>
        <div class="suite-table-responsive" style="margin-top: 15px;">
            <table class="suite-modern-table" style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 13px;">
                        <th style="padding: 10px;">Producto / Modelo</th>
                        <th style="padding: 10px; text-align: center;">Unidades Vendidas</th>
                        <th style="padding: 10px; text-align: right;">Ingresos (USD)</th>
                        <th style="padding: 10px; text-align: center; border-left: 1px dashed #cbd5e1;">Velocidad Diaria</th>
                        <th style="padding: 10px; text-align: center;">Autonomía</th>
                    </tr>
                </thead>
                <tbody id="bi-top5-body">
                    <tr><td colspan="5" style="text-align: center; padding: 15px; color: #94a3b8;">Cargando métricas y proyecciones...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
        
        <div style="background: #1e293b; padding: 25px; border-radius: 12px; border: 1px solid #334155; box-shadow: inset 0 2px 10px rgba(0,0,0,0.5); color: white;">
            <h3 style="margin-top: 0; color: #94a3b8; font-size: 13px; text-transform: uppercase; border-bottom: 1px solid #334155; padding-bottom: 10px; letter-spacing: 1px;">
                <i class="dashicons dashicons-dashboard" style="vertical-align: middle;"></i> Estado Global del Inventario Activo
            </h3>
            
            <div style="display: flex; justify-content: center; align-items: center; flex-direction: column; height: 260px; margin-top: 15px;">
                <div style="font-size: 14px; color: #cbd5e1; margin-bottom: 5px;">PROMEDIO DE AUTONOMÍA GLOBAL</div>
                
                <div id="bi-global-runway" style="font-size: 72px; font-weight: 900; font-family: monospace; color: #10b981; text-shadow: 0 0 10px rgba(16, 185, 129, 0.4); line-height: 1;">--</div>
                
                <div style="font-size: 16px; color: #94a3b8; margin-top: -5px; margin-bottom: 20px;">DÍAS DE STOCK RESTANTE</div>
                
                <div style="width: 80%; height: 8px; background: #334155; border-radius: 4px; overflow: hidden; position: relative;">
                    <div id="bi-runway-bar" style="height: 100%; width: 50%; background: #10b981; border-radius: 4px; transition: width 0.5s ease, background 0.5s ease;"></div>
                </div>
                
                <div id="bi-runway-advice" style="margin-top: 20px; font-size: 14px; font-weight: bold; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 8px 15px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.2);">
                    Cargando directriz del sistema...
                </div>
            </div>
        </div>

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