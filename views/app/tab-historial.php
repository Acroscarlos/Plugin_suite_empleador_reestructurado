<?php
/**
 * Vista: Historial de Cotizaciones
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div id="TabHistorial" class="suite-tab-content" style="display: none;">
	
	
	
    <div class="suite-header-modern" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin:0; font-size: 22px; color: #0f172a;">📜 Historial de Cotizaciones</h2>
            <p style="color:#64748b; font-size:14px; margin-top:5px;">Revise, imprima o clone sus cotizaciones y pedidos anteriores.</p>
        </div>
        <button type="button" id="btn-upload-manual-fiscal" class="btn-save-big" style="background: #4f46e5; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);">
            📤 Subir Doc. Fiscal Externo
        </button>
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