<?php
/**
 * Vista: Inventario Global
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<style>
/* =========================================================
   ESTILOS: CONTROLES SUPERIORES DATATABLES INVENTARIO
   ========================================================= */
.dt-top-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
    background: #f8fafc;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

/* Forzar el selector de registros a una sola línea horizontal */
.dt-top-controls .dataTables_length label {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 10px;
    margin: 0;
    color: #475569;
    font-size: 14px;
    white-space: nowrap;
}

.dt-top-controls .dataTables_length select {
    margin: 0;
    min-width: 70px;
    padding: 4px 8px;
}

/* Ajustes para el texto de información ("Mostrando 1 a 25...") */
.dt-top-controls .dataTables_info {
    padding-top: 0 !important;
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
}
</style>

<div id="TabInventario" class="suite-tab-content" style="display: none;">
    <div class="suite-header-modern" style="padding-bottom: 0; border-bottom: none; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin:0; font-size: 22px; color: #0f172a;">📦 Inventario</h2>
            <p style="color:#64748b; font-size:14px; margin-top:5px;">Consulta de disponibilidad, precios en tiempo real y predicciones de IA.</p>
        </div>
    </div>
    
    <div style="padding: 25px;">
        <div class="suite-table-responsive">
            <table class="suite-modern-table" id="inventoryTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 12%;">SKU</th>
                        <th style="width: 28%;">Nombre</th>
                        <th style="width: 10%;">Precio</th>
                        <th style="width: 10%;">Precio Divisas</th>
                        <th style="width: 10%; text-align: center;">Status</th>
                        <th style="width: 7%; text-align: center;">Stock</th>
                        <th style="width: 7%; text-align: center;">Galerías</th>
                        <th style="width: 7%; text-align: center;">Millennium</th>
                        <th style="width: 9%; text-align: center;">Tránsito</th>
                    </tr>
                </thead>
                <tbody id="inventory-tbody">
                    </tbody>
            </table>
        </div>
    </div>
</div>