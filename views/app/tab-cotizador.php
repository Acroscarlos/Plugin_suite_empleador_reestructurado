<?php
/**
 * Vista: Pestaña del Cotizador (Punto de Venta)
 * 
 * Contiene la interfaz de búsqueda, carrito de compras y datos de facturación.
 * El estado (State) es manejado 100% por assets/js/core/state.js
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Opcional: Obtener la tasa BCV guardada en la base de datos (Options)
$tasa_bcv_actual = get_option( 'suite_tasa_bcv', 1.00 );
?>

<div id="TabPos" class="suite-tab-content" style="display: none;">
    
    <!-- Input oculto para inicializar la tasa en el JS (State) -->
    <input type="hidden" id="hidden-tasa-bcv" value="<?php echo esc_attr( $tasa_bcv_actual ); ?>">

    <div style="display: flex; flex-wrap: wrap; gap: 30px;">
        
        <!-- ========================================================= -->
        <!-- COLUMNA IZQUIERDA: BÚSQUEDA Y CARRITO                     -->
        <!-- ========================================================= -->
        <div style="flex: 1; min-width: 60%;">
            
            <!-- Barra de Búsqueda de Productos -->
            <div class="pos-search-box spotlight-wrapper" style="margin-bottom: 20px; position: relative;">
                <span class="dashicons dashicons-search search-icon"></span>
                <input type="text" id="pos-product-search" placeholder="Buscar producto por SKU o Nombre..." autocomplete="off">
                <!-- Dropdown de resultados predictivos -->
                <div id="pos-search-results" class="pos-dropdown" style="display: none; position: absolute; width: 100%;"></div>
            </div>

            <!-- Botonera y Agregar Manual -->
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                <input type="text" id="manual-item-desc" placeholder="Descripción producto manual" style="flex: 2; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <input type="number" id="manual-item-price" placeholder="Precio ($)" step="0.01" style="width: 100px; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <input type="number" id="manual-item-qty" placeholder="Cant." value="1" min="1" style="width: 70px; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <button id="btn-add-manual-item" class="btn-modern-action" style="white-space: nowrap;">➕ Manual</button>
            </div>

            <!-- Tabla del Carrito -->
            <div class="suite-table-responsive">
                <table class="suite-modern-table" style="min-width: 100%;">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Precio Unitario</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="pos-cart-body">
                        <!-- JS inyectará las filas del carrito aquí -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- COLUMNA DERECHA: DATOS DEL CLIENTE Y TOTALES              -->
        <!-- ========================================================= -->
        <div class="pos-right" style="width: 350px; flex-shrink: 0;">
            
            <div id="cli-form-wrapper" style="background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                
                <h3 style="margin-top: 0 !important;">Datos del Cliente</h3>
                
                <div class="spotlight-wrapper" style="position: relative;">
                    <span class="dashicons dashicons-search search-icon"></span>
                    <input type="text" id="cli-rif" class="widefat" placeholder="RIF (Ej: J123456789)" style="padding-left: 45px !important;" autocomplete="off">
                </div>
                
                <input type="text" id="cli-nombre" class="widefat" placeholder="Nombre o Razón Social *">
                <input type="text" id="cli-tel" class="widefat" placeholder="Teléfono de Contacto">
                <input type="email" id="cli-email" class="widefat" placeholder="Correo Electrónico">
                
                <div class="form-group-row">
                    <div style="flex:1"><input type="text" id="cli-ciudad" class="widefat" placeholder="Ciudad"></div>
                    <div style="flex:1"><input type="text" id="cli-estado" class="widefat" placeholder="Estado"></div>
                </div>

                <textarea id="cli-dir" class="widefat" placeholder="Dirección Fiscal o de Entrega" rows="2"></textarea>
                
                <!-- Campos ocultos/opcionales para estructura completa -->
                <input type="hidden" id="cli-contacto">
                <input type="hidden" id="cli-notas">

                <h3 style="margin-top: 20px !important;">Configuración</h3>
                
                <div class="form-group-row">
                    <div style="flex:1">
                        <label>Moneda Impresión</label>
                        <select id="pos-moneda" class="widefat">
                            <option value="USD">USD ($)</option>
                            <option value="BS">Bolívares (Bs)</option>
                        </select>
                    </div>
                    <div style="flex:1">
                        <label>Validez (Días)</label>
                        <input type="number" id="pos-validez" class="widefat" value="15" min="1">
                    </div>
                </div>

                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 15px; border: 1px solid #cbd5e1;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="color: #64748b; font-weight: 600;">Subtotal / Total:</span>
                        <strong id="pos-total-usd" style="font-size: 20px; color: #0f172a;">$0.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11px; color: #94a3b8;">Ref. BCV</span>
                        <strong id="pos-total-bs" style="font-size: 14px; color: #475569;">Bs 0.00</strong>
                    </div>
                </div>

                <button id="btn-save-quote" class="btn-save-big" style="margin-top: 20px;">
                    💾 Guardar Cotización
                </button>
            </div>

        </div>
    </div>
</div>