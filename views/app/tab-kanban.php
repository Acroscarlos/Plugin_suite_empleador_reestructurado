<?php
/**
 * Vista: Tablero Kanban de Pedidos (Módulo 1)
 * 
 * Contiene la interfaz interactiva Drag & Drop para la gestión visual de cotizaciones y pedidos.
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<style>
    /* Estilos específicos del Kanban (Actualizado a Ruta A: Estilo Trello) */
    .kanban-board {
        display: flex;
        flex-wrap: nowrap;          /* Evita que la 5ta columna caiga abajo */
        gap: 20px;
        align-items: start;
        margin-top: 15px;
        overflow-x: auto;           /* Habilita el scroll horizontal elegante */
        padding-bottom: 15px;       /* Espacio extra para que la barra de scroll no pise las tarjetas */
    }
    .kanban-column-wrapper {
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        max-height: 80vh;
        min-width: 300px; /* Forzamos un ancho mínimo para cada columna */
        flex: 0 0 300px;  /* Evita que Flexbox las aplaste */
    }
    .kanban-column-header {
        padding: 15px;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    /* Acentos de color por columna */
    .col-emitida .kanban-column-header { border-bottom-color: #f59e0b; }
    .col-pagado .kanban-column-header { border-bottom-color: #10b981; } /* Pagado ahora es el paso 2 */
	.col-por_enviar .kanban-column-header { border-bottom-color: #f97316; }
    .col-despachado .kanban-column-header { border-bottom-color: #8b5cf6; }

    .kanban-column-body {
        padding: 15px;
        overflow-y: auto;
        flex-grow: 1;
        min-height: 150px;
    }
    .kanban-card {
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
        cursor: grab;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .kanban-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .kanban-card:active {
        cursor: grabbing;
    }
    .kanban-ghost {
        opacity: 0.4;
        background: #e2e8f0;
    }
    .kanban-card-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 5px;
    }
    .kanban-card-client {
        font-size: 13px;
        color: #475569;
        margin-bottom: 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kanban-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px dashed #e2e8f0;
        padding-top: 10px;
        margin-top: 10px;
    }
    .kanban-wa-btn {
        background: #25d366;
        color: #fff !important;
        font-size: 11px;
        font-weight: bold;
        padding: 4px 8px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: background 0.2s;
    }
    .kanban-wa-btn:hover { background: #1ebc59; }
</style>

<div id="TabKanban" class="suite-tab-content" style="display: none;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
        <h2>📦 Portal de Pedidos</h2>
        <button class="btn-modern-action" onclick="SuiteKanban.loadBoard()">🔄 Refrescar Tablero</button>
    </div>

    <div class="kanban-board">
        
        <!-- Columna 1: Pendientes -->
        <div class="kanban-column-wrapper col-emitida">
            <div class="kanban-column-header">
                <span>🟡 Pendiente</span>
                <span class="count-badge pill-neutral" id="count-emitida">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-emitida" data-status="emitida">
                <!-- JS insertará tarjetas aquí -->
            </div>
        </div>

        <!-- Columna 2: En Proceso -->
        <div class="kanban-column-wrapper col-pagado">
            <div class="kanban-column-header">
                <span>💰 Pagado</span>
                <span class="count-badge pill-neutral" id="count-pagado">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-pagado" data-status="pagado"></div>
        </div>
		
        <!-- Columna Nueva: Facturado / Pagado -->
		<div class="kanban-column-wrapper col-por_enviar">
            <div class="kanban-column-header">
                <span>🟠 Por Enviar</span>
                <span class="count-badge pill-neutral" id="count-por_enviar">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-por_enviar" data-status="por_enviar"></div>
        </div>
		
        <!-- Columna 4: Despachado -->
        <div class="kanban-column-wrapper col-despachado">
            <div class="kanban-column-header">
                <span>🟣 Enviado</span>
                <span class="count-badge pill-neutral" id="count-despachado">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-despachado" data-status="despachado"></div>
        </div>

    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL: CIERRE DE VENTA (MÓDULO 4 - COMISIONES Y LOGÍSTICA)-->
<!-- ========================================================= -->
<div id="modal-cierre-venta" class="suite-modal" style="display: none;">
    <div class="suite-modal-content">
        <!-- El ID del botón de cierre es clave para el JS -->
        <span class="close-modal" id="close-modal-cierre">×</span>
        
        <h3 style="margin-top:0; color:#0f172a;">💰 Procesar Pago y Comisión</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:20px;">
            Complete los datos operativos para registrar su comisión y autorizar el despacho.
        </p>

        <!-- Guardamos el ID del pedido arrastrado -->
        <input type="hidden" id="cierre-quote-id">

        <div class="form-group-row">
            <div style="flex:1">
                <label>Canal de Venta *</label>
                <select id="cierre-canal" class="widefat">
                    <option value="">Seleccione...</option>
                    <option value="WhatsApp">WhatsApp</option>
                    <option value="Instagram">Instagram</option>
                    <option value="Tienda Fisica">Tienda Física</option>
                    <option value="Llamada">Llamada Telefónica</option>
                    <option value="Referido">Referido Comercial</option>
                </select>
            </div>
            <div style="flex:1">
                <label>Método de Pago *</label>
                <select id="cierre-pago" class="widefat">
                    <option value="">Seleccione...</option>
                    <option value="Zelle">Zelle</option>
                    <option value="Efectivo USD">Efectivo USD</option>
                    <option value="Punto de Venta">Punto de Venta (Bs)</option>
                    <option value="Pago Movil">Pago Móvil</option>
                    <option value="Cashea">Cashea</option>
                    <option value="Transferencia">Transferencia USD/Bs</option>
                </select>
            </div>
        </div>

        <label>Método de Entrega *</label>
        <select id="cierre-entrega" class="widefat">
            <option value="">Seleccione...</option>
            <option value="Retiro en Tienda">Retiro en Tienda</option>
            <option value="Delivery">Delivery (Caracas)</option>
            <option value="Encomienda MRW">Encomienda Nacional (MRW)</option>
            <option value="Encomienda Tealca">Encomienda Nacional (Tealca)</option>
            <option value="Encomienda Zoom">Encomienda Nacional (Zoom)</option>
        </select>
		
		

	
		<label>N° de Factura / Nota de Entrega *</label>
		<div style="display: flex; gap: 8px; margin-bottom: 10px;">
			<select id="cierre-recibo-prefijo" class="widefat" style="width: 80px; margin-bottom: 0;">
				<option value="F">F</option>
				<option value="NE">NE</option>
			</select>
			<input type="text" id="cierre-loyverse" class="widefat" placeholder="Ej: 1005 (Solo números)" style="flex: 1; margin-bottom: 0;" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
		</div>		

        <label>Link de Captura de Pago / Soporte</label>
        <input type="url" id="cierre-captura" class="widefat" placeholder="https://drive.google.com/... o Imgur">

		<!-- INICIO: Selector de Venta Compartida -->
		<label style="margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 10px;">
			🤝 ¿Venta Compartida? (Colaboradores)
		</label>
		<select id="cierre-compartido" class="widefat" multiple="multiple" data-placeholder="Seleccione otros vendedores...">
			<?php
			// Poblar dinámicamente aislando al usuario actual
			$vendedores = get_users( ['role__not_in' => ['subscriber', 'customer']] );
			$current_user_id = get_current_user_id();
			foreach ( $vendedores as $vend ) {
				if ( $vend->ID != $current_user_id ) {
					echo '<option value="' . esc_attr( $vend->ID ) . '">' . esc_html( $vend->display_name ) . '</option>';
				}
			}
			?>
		</select>
		<small style="color:#64748b; font-size: 11px; display:block; margin-bottom: 15px;">
			Deje en blanco si la venta es individual.
		</small>
		<!-- FIN: Selector de Venta Compartida -->		
		
		
		<div id="container-b2b-percent" style="display: none; margin-top: 15px; background: #f0f9ff; padding: 15px; border-left: 4px solid #0ea5e9; border-radius: 8px;">
            <label style="font-weight:bold; color:#0369a1; display:block; margin-bottom:8px;">🤝 Porcentaje de Comisión B2B (%)</label>
            <div style="display:flex; align-items:center; gap:10px;">
                <input type="number" id="cierre-b2b-percent" class="widefat" step="0.1" min="0" max="100" placeholder="Ej: 15" style="border-color:#bae6fd; margin-bottom:0;">
                <span style="font-weight:bold; color:#0369a1;">%</span>
            </div>
            <small style="color:#0284c7; display:block; margin-top:5px; font-size:11px;">
                Detectado: Aliado Comercial. Ingrese el porcentaje acordado para este pedido.
            </small>
        </div>		
		
        <button class="btn-save-big" id="btn-confirmar-pago" style="margin-top:15px; background-color: #10b981;">
            Confirmar y Procesar Pago
        </button>
    </div>
</div>

<div id="modal-super-pago" class="suite-modal" style="display: none;">
    <div class="suite-modal-content" style="max-width: 650px; background: #f8fafc;">
        <span class="close-modal" id="close-super-pago">&times;</span>
        <h2 style="margin-top:0; color:#0f172a; display:flex; align-items:center; gap:10px;">
            💸 <span>Cierre de Venta y Logística</span>
        </h2>
        <p style="color:#64748b; font-size:13px; margin-top:-10px; margin-bottom:20px;">Complete los datos financieros y de entrega para validar el pago.</p>

        <form id="form-super-pago">
            <input type="hidden" id="sp-quote-id" value="">

            <div style="background:#ffffff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:15px;">
                <h3 style="border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; color:#1e293b; margin-top:0; font-size:15px;">Sección A: El Pago</h3>

                <div class="form-group-row">
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Forma de Pago *</label>
                        <select id="sp-forma-pago" class="widefat" required>
                            <option value="">Seleccione...</option>
                            <option value="Transferencia">Transferencia Bancaria</option>
                            <option value="Pago Movil">Pago Móvil</option>
                            <option value="Zelle">Zelle</option>
                            <option value="Efectivo">Efectivo (Divisas)</option>
                            <option value="Binance">Binance (USDT)</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Fecha de Pago *</label>
                        <input type="date" id="sp-fecha-pago" class="widefat" required>
                    </div>
                </div>

                <div class="form-group-row">
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Monto Pagado *</label>
                        <input type="number" step="0.01" id="sp-monto-pagado" class="widefat" placeholder="Ej: 150.00" required>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Comprobante (Imagen o PDF) *</label>
                        <input type="file" id="sp-comprobante" class="widefat" accept=".jpg,.jpeg,.png,.pdf" style="padding: 5px; font-size: 12px; cursor: pointer;" required>
                    </div>
                </div>

                <div style="display:flex; gap: 20px; margin-top: 10px; padding-top:10px; border-top:1px dashed #e2e8f0;">
                    <label style="font-size:13px; color:#334155; cursor:pointer;"><input type="checkbox" id="sp-factura"> Requiere Factura Fiscal</label>
                    <label style="font-size:13px; color:#334155; cursor:pointer;"><input type="checkbox" id="sp-retencion"> Agente de Retención</label>
                </div>
            </div>

			<div style="background:#ffffff; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                <h3 style="border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; color:#1e293b; margin-top:0; font-size:15px;">Sección B: Logística y Envío</h3>

                <div style="margin-bottom: 15px;">
                    <label style="font-size:12px; font-weight:bold; color:#475569;">Tipo de Despacho *</label>
                    <select id="sp-tipo-envio" class="widefat" required>
                        <option value="">Seleccione el método...</option>
                        <option value="Retiro">🏢 Retiro en Tienda</option>
                        <option value="Motorizado">🛵 Delivery / Motorizado (Local)</option>
                        <option value="Nacional">📦 Envío Nacional (Encomienda)</option>
                    </select>
                </div>

                <div id="sp-datos-envio-container" style="display:none; background:#f8fafc; padding:15px; border-radius:8px; border: 1px solid #cbd5e1;">
                    
                    <div class="form-group-row">
                        <div style="flex:1; display:none;" id="box-agencia">
                            <label style="font-size:12px; font-weight:bold; color:#475569;">Agencia *</label>
                            <select id="sp-agencia-envio" class="widefat">
                                <option value="">Seleccione...</option>
                                <option value="MRW">MRW</option>
                                <option value="Zoom">Zoom</option>
                                <option value="Tealca">Tealca</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:12px; font-weight:bold; color:#475569;">Nombre y Apellido (Receptor) *</label>
                            <input type="text" id="sp-nombre-receptor" class="widefat">
                        </div>
                    </div>

                    <div class="form-group-row">
                        <div style="flex:1;">
                            <label style="font-size:12px; font-weight:bold; color:#475569;">RIF / Cédula *</label>
                            <input type="text" id="sp-rif-receptor" class="widefat">
                        </div>
                        <div style="flex:1;">
                            <label style="font-size:12px; font-weight:bold; color:#475569;">Teléfono *</label>
                            <input type="text" id="sp-telefono-receptor" class="widefat">
                        </div>
                    </div>

                    <div style="margin-top: 10px; display:none;" id="box-direccion">
                        <label style="font-size:12px; font-weight:bold; color:#475569;">Dirección Exacta de Destino *</label>
                        <textarea id="sp-direccion-envio" class="widefat" rows="2"></textarea>
                    </div>
                </div>
            </div>
			

            <div style="background:#fee2e2; padding:15px; border-radius:8px; border:2px solid #fca5a5; margin-bottom:20px;">
                <label style="color:#dc2626; font-weight:900; font-size:14px; display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" id="sp-prioridad" style="width:20px; height:20px; accent-color: #dc2626;">
                    🚨 MARCAR ESTA ORDEN COMO PRIORIDAD URGENTE
                </label>
            </div>

            <div style="display:flex; justify-content: flex-end; gap:10px;">
                <button type="button" class="btn-modern-action" id="btn-cancel-sp" style="background:#64748b; color:white; padding:12px 20px;">Cancelar</button>
                <button type="submit" class="btn-save-big" style="background:#059669; padding:12px 20px;">✅ Confirmar Datos</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-ver-pago" class="suite-modal" style="display: none;">
    <div class="suite-modal-content" style="max-width: 500px; background: #f8fafc;">
        <span class="close-modal" id="close-ver-pago">&times;</span>
        <h2 style="margin-top:0; color:#0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
            👀 Verificación de Pago
        </h2>
        <div id="vp-content" style="margin-top: 15px;">
            </div>
        <div style="text-align: right; margin-top: 15px;">
            <button class="btn-modern-action" id="btn-cerrar-ver-pago" style="background:#64748b; color:white; padding:8px 15px;">Cerrar Vista</button>
        </div>
    </div>
</div>