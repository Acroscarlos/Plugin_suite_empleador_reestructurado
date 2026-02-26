<?php
/**
 * Vista: Pestaña del Cotizador (Punto de Venta) - V11 Mobile-First
 * @package SuiteEmpleados\Views\App
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$tasa_bcv_actual = get_option( 'suite_tasa_bcv', 1.00 ); 
?>
<div id="TabPos" class="suite-tab-content" style="display: none;">
    <div style="display: flex; flex-wrap: wrap; gap: 25px;">
        
        <!-- COLUMNA IZQUIERDA: PRODUCTOS Y CARRITO -->
        <div style="flex: 2 1 400px; display: flex; flex-direction: column; gap: 20px;">
            <div class="suite-header-modern" style="border-radius: 8px; padding: 15px 20px; background: #fff; border: 1px solid #e2e8f0;">
                <h2 style="margin:0; font-size: 18px; color: #0f172a;">🛒 Búsqueda y Carrito</h2>
            </div>
            
            <div style="display: flex; gap: 10px; position: relative;">
                <input type="text" id="pos-product-search" class="widefat" placeholder="🔍 Buscar producto por SKU o Nombre..." style="margin: 0; flex: 1; height: 48px; border-radius: 8px;">
                <button type="button" id="btn-add-manual-item" class="btn-modern-action" style="height: 48px;">➕ Ítem Manual</button>
                <div id="pos-search-results" class="pos-dropdown" style="display:none; position: absolute; top: 55px; left: 0; width: 100%; z-index: 100; border: 1px solid #cbd5e1; background: #fff; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); max-height: 350px; overflow-y: auto;"></div>
            </div>

            <!-- Contenedor del Carrito Responsivo (Mobile-First Cards) -->
            <div id="pos-cart-container" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; min-height: 300px;">
                <div id="pos-cart-body" style="display: flex; flex-direction: column; gap: 12px;">
                    <!-- Los items se inyectarán aquí vía JS -->
                    <div style="text-align: center; color: #94a3b8; padding: 40px 0;">El carrito está vacío. Busque un producto para comenzar.</div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CLIENTE Y TOTALES -->
        <div style="flex: 1 1 320px; display: flex; flex-direction: column; gap: 20px;">
            
            <!-- Bloque de Cliente -->
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    👤 Datos del Cliente
                    <button type="button" id="btn-clear-client" class="btn-modern-action small" style="display: none; color: #dc2626; border-color: #fca5a5;">Limpiar</button>
                </h3>

                <div id="cli-form-wrapper">
                    <!-- Buscador Predictivo de Clientes -->
                    <div style="position: relative; margin-bottom: 15px;">
                        <input type="text" id="cli-search-predictive" class="widefat" placeholder="🔍 Buscar cliente existente (Nombre o RIF)..." style="background: #f0fdf4; border-color: #a7f3d0; margin-bottom: 5px;">
                        <div id="cli-search-results" class="pos-dropdown" style="display:none; position: absolute; top: 45px; left: 0; width: 100%; z-index: 100; border: 1px solid #cbd5e1; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-height: 250px; overflow-y: auto;"></div>
                    </div>

                    <!-- RIF Atómico -->
                    <label style="font-size: 12px; font-weight: bold; color: #475569;">RIF / CI *</label>
                    <div style="display: flex; gap: 8px; margin-bottom: 15px;">
                        <select id="cli-rif-prefix" class="widefat" style="width: 80px; margin-bottom: 0;">
                            <option value="J">J</option>
                            <option value="V">V</option>
                            <option value="E">E</option>
                            <option value="G">G</option>
                            <option value="P">P</option>
                            <option value="C">C</option>
                        </select>
                        <input type="text" id="cli-rif-number" class="widefat" placeholder="Ej: 12345678" style="flex: 1; margin-bottom: 0;">
                    </div>

                    <label style="font-size: 12px; font-weight: bold; color: #475569;">Nombre / Razón Social *</label>
                    <input type="text" id="cli-nombre" class="widefat">

                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label style="font-size: 12px; font-weight: bold; color: #475569;">Teléfono</label>
                            <input type="text" id="cli-tel" class="widefat">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 12px; font-weight: bold; color: #475569;">Email</label>
                            <input type="email" id="cli-email" class="widefat">
                        </div>
                    </div>

                    <label style="font-size: 12px; font-weight: bold; color: #475569;">Dirección</label>
                    <input type="text" id="cli-dir" class="widefat">

                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label style="font-size: 12px; font-weight: bold; color: #475569;">Ciudad</label>
                            <input type="text" id="cli-ciudad" class="widefat">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 12px; font-weight: bold; color: #475569;">Estado</label>
                            <input type="text" id="cli-estado" class="widefat">
                        </div>
                    </div>
                    
                    <label style="font-size: 12px; font-weight: bold; color: #475569;">Atención a (Contacto)</label>
                    <input type="text" id="cli-contacto" class="widefat">
                </div>
            </div>

            <!-- Bloque de Configuración y Totales -->
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                <h3 style="margin: 0 0 15px 0; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">⚙️ Configuración y Totales</h3>
                
				<div style="display: flex; gap: 15px; margin-bottom: 30px;">
                    
                    <div style="flex: 1;">
                        <label style="font-size: 12px; font-weight: bold; color: #475569;">Moneda PDF</label>
                        <select id="pos-moneda" class="widefat" style="margin-bottom:0; cursor: pointer;">
                            <option value="USD">Dólares ($)</option>
                            <option value="BS">Bolívares (Bs.)</option>
                        </select>
                    </div>

                    <div style="flex: 1;">
                        <label style="font-size: 12px; font-weight: bold; color: #475569;">Validez</label>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <input type="number" id="pos-validez" class="widefat" value="5" min="1" style="margin-bottom:0;">
                            <span style="font-size: 11px; color: #64748b;">Días</span>
                        </div>
                    </div>

                    <div style="flex: 1;">
                        <label style="font-size: 12px; font-weight: bold; color: #475569;">Tasa BCV</label>
                        <div style="position: relative;">
                            <input type="number" step="0.0001" id="pos-tasa-bcv" class="widefat" value="<?php echo esc_attr( $tasa_bcv_actual ); ?>" style="margin-bottom:0;">
                            <small id="bcv-update-date" style="font-size: 10.5px; color: #64748b; position: absolute; bottom: -18px; left: 0; white-space: nowrap; font-weight: bold;">
                                Buscando API...
                            </small>
                        </div>
                    </div>
                    
                </div>

                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                    <div style="font-size: 13px; color: #64748b; font-weight: bold;">TOTAL COTIZACIÓN</div>
                    <div id="pos-total-usd" style="font-size: 32px; font-weight: 800; color: #059669; line-height: 1.2;">$0.00</div>
                    <div id="pos-total-bs" style="font-size: 14px; color: #475569; font-weight: bold;">Ref. Bs: 0.00</div>
                </div>

                <button type="button" id="btn-save-quote" class="btn-save-big">💾 Guardar Cotización</button>
            </div>
        </div>
    </div>
</div>