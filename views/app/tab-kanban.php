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
    /* Estilos específicos del Kanban */
    .kanban-board {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        align-items: start;
        margin-top: 15px;
    }
    .kanban-column-wrapper {
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        max-height: 80vh;
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
    .col-proceso .kanban-column-header { border-bottom-color: #3b82f6; }
    .col-pagado .kanban-column-header { border-bottom-color: #10b981; }
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
        <div class="kanban-column-wrapper col-proceso">
            <div class="kanban-column-header">
                <span>🔵 En Proceso</span>
                <span class="count-badge pill-neutral" id="count-proceso">0</span>
            </div>
            <div class="kanban-column-body" id="kb-col-proceso" data-status="proceso"></div>
        </div>

        <!-- Columna 3: Facturado / Pagado -->
        <div class="kanban-column-wrapper col-pagado">
            <div class="kanban-column-header">
                <span>🟢 Facturado</span>
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