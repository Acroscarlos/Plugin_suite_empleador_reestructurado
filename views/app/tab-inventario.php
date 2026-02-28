<?php
/**
 * Vista: Inventario Global
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>

<div id="TabInventario" class="suite-tab-content" style="display: none;">
    <div class="suite-header-modern">
        <h2 style="margin:0; font-size: 22px; color: #0f172a;">📦 Inventario</h2>
        <p style="color:#64748b; font-size:14px; margin-top:5px;">Consulta de disponibilidad de productos y stock en tránsito.</p>
    </div>
    
    <div style="padding: 25px;">
        <div class="suite-table-responsive">
            <!-- Las columnas se ocultarán automáticamente vía JavaScript según el Rol -->
            <table class="suite-modern-table" id="inventoryTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Nombre / Descripción</th>
                        <th>Status</th>
                        <th>Stock Total</th>
                        <th>Disp. Galerías</th>
                        <th>Disp. Millennium</th>
                        <th>En Tránsito</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Llenado dinámico por DataTables -->
                </tbody>
            </table>
        </div>
    </div>
</div>