<?php
/**
 * Vista: Historial de Cotizaciones
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div id="TabHistorial" class="suite-tab-content" style="display: none;">
    <div class="suite-header-modern">
        <h2 style="margin:0; font-size: 22px; color: #0f172a;">📜 Historial de Cotizaciones</h2>
        <p style="color:#64748b; font-size:14px; margin-top:5px;">Revise, imprima o clone sus cotizaciones y pedidos anteriores.</p>
    </div>
    
    <div style="padding: 25px;">
        <div class="suite-table-responsive">
            <table class="suite-modern-table" id="historyTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Total ($)</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- El JS se encarga del renderizado dinámico -->
                </tbody>
            </table>
        </div>
    </div>
</div>