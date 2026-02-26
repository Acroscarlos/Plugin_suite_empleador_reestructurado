<?php
/**
 * Archivo: views/components/modal-cliente.php
 * Proposito: Modal inmersivo a pantalla completa (Legacy Layout)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<style>
/* =========================================================
   RECUPERACIÓN: MODAL INMERSIVO DEL PERFIL DEL CLIENTE 
   ========================================================= */
#modal-client-profile .suite-modal-content.large {
    max-width: 1200px;
    width: 95%;
    height: 90vh; /* Pantalla completa */
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
    background: #f8fafc;
    border-radius: 16px;
}

#modal-client-profile .modal-header-fixed {
    padding: 20px 30px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #ffffff;
    position: relative;
    border-radius: 16px 16px 0 0;
}

/* Botón flotante y visible de cerrar */
#modal-client-profile .close-modal-float {
    position: absolute;
    top: 20px;
    right: 25px;
    font-size: 32px;
    font-weight: bold;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1;
}

#modal-client-profile .close-modal-float:hover {
    color: #dc2626;
    transform: scale(1.1);
}

/* Layout a 2 columnas con Flexbox */
#modal-client-profile .modal-body-scroll {
    display: flex;
    flex: 1;
    overflow-y: auto;
    padding: 30px;
    gap: 30px;
}

/* Columna Izquierda: Datos y Acciones */
#modal-client-profile .profile-left {
    flex: 0 0 380px;
    background: #ffffff;
    padding: 25px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
}

/* Columna Derecha: KPIs e Historial */
#modal-client-profile .profile-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* Tarjetas KPI (Estilo Legacy Recuperado) */
#modal-client-profile .kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

#modal-client-profile .kpi-card {
    padding: 25px;
    border-radius: 16px;
    color: white;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

/* Fondos Degradados */
#modal-client-profile .kpi-card.kpi-total { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
#modal-client-profile .kpi-card.kpi-count { background: linear-gradient(135deg, #0284c7 0%, #3b82f6 100%); }
#modal-client-profile .kpi-card.kpi-last  { background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%); }

#modal-client-profile .kpi-card small { 
    font-size: 13px; 
    text-transform: uppercase; 
    font-weight: 600; 
    opacity: 0.9; 
    margin-bottom: 5px; 
    display: block;
}

#modal-client-profile .kpi-card strong { 
    font-size: 34px; 
    font-weight: 800; 
    letter-spacing: -1px; 
    line-height: 1.2;
}

/* Contenedor del Historial */
#modal-client-profile .history-container {
    flex: 1;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#modal-client-profile .history-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    font-weight: 700;
    color: #0f172a;
    font-size: 15px;
}

#modal-client-profile .history-scroll {
    flex: 1;
    overflow-y: auto;
}

/* Mobile Responsive */
@media (max-width: 900px) {
    #modal-client-profile .modal-body-scroll { flex-direction: column; }
    #modal-client-profile .profile-left { flex: auto; }
    #modal-client-profile .suite-modal-content.large { height: 100%; width: 100%; border-radius: 0; max-height: 100vh; }
}
</style>

<!-- El ID corresponde estrictamente con el archivo crm.js de la V12 -->
<div id="modal-client-profile" class="suite-modal" style="display: none;">
    <div class="suite-modal-content large">
        
        <!-- CABECERA -->
        <div class="modal-header-fixed">
            <h3 id="cli-profile-name" style="margin:0; color:#0f172a; font-size: 22px;">📂 Expediente del Cliente</h3>
            <span class="close-modal-float" onclick="jQuery('#modal-client-profile').fadeOut();">&times;</span>
        </div>
        
        <div class="modal-body-scroll">
            <!-- COLUMNA IZQUIERDA: DATOS DE CONTACTO (Formulario Mapeado a crm.js) -->
            <div class="profile-left">
                <h4 style="margin:0 0 15px 0; color:#475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-size: 14px;">📋 Información de Contacto</h4>
                
                <input type="hidden" id="prof-id">
                
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>RIF/CI</label>
                        <input type="text" id="prof-rif" class="widefat" readonly style="background-color: #f1f5f9; cursor: not-allowed;" title="El RIF no puede ser modificado">
                    </div>
                    <div style="flex: 2;">
                        <label>Razón Social</label>
                        <input type="text" id="prof-nombre" class="widefat">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>Teléfono</label>
                        <input type="text" id="prof-tel" class="widefat">
                    </div>
                    <div style="flex: 1;">
                        <label>Email</label>
                        <input type="email" id="prof-email" class="widefat">
                    </div>
                </div>
                
                <label>Dirección Física</label>
                <input type="text" id="prof-dir" class="widefat">
                
                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>Ciudad</label>
                        <input type="text" id="prof-ciudad" class="widefat">
                    </div>
                    <div style="flex: 1;">
                        <label>Estado</label>
                        <input type="text" id="prof-estado" class="widefat">
                    </div>
                </div>
                
                <label>Persona de Contacto</label>
                <input type="text" id="prof-contacto" class="widefat">
                
                <label>📝 Notas Internas</label>
                <textarea id="prof-notas" class="widefat" rows="3" placeholder="Añadir observaciones sobre este cliente..."></textarea>
                
                <div style="display:flex; gap:10px; margin-top: auto; padding-top: 15px;">
                    <button id="btn-update-profile" class="btn-save-big" style="flex:2;">💾 Guardar Cambios</button>
                    <button id="btn-delete-profile" class="btn-modern-action" style="flex:1; color:#dc2626; border-color:#fca5a5; justify-content: center;">🗑️ Eliminar</button>
                </div>
            </div>

            <!-- COLUMNA DERECHA: KPIs Y TABLA -->
            <div class="profile-right">
                
                <!-- Tarjetas KPI Gigantes -->
                <div class="kpi-row" id="cli-profile-kpi-compras">
                    <div class="kpi-card kpi-total">
                        <small>💰 Total Gastado</small>
                        <strong id="kpi-total">$0.00</strong>
                    </div>
                    <div class="kpi-card kpi-count">
                        <small>🛒 Total Operaciones</small>
                        <strong id="kpi-count">0</strong>
                    </div>
                    <div class="kpi-card kpi-last">
                        <small>📅 Última Compra</small>
                        <strong id="kpi-last">-</strong>
                    </div>
                </div>
                
                <!-- Pestaña de Historial -->
                <div class="history-container">
                    <div class="history-header">
                        📜 Historial de Cotizaciones y Pedidos
                    </div>
                    <div class="history-scroll">
                        <table class="suite-modern-table" id="cli-history-table">
                            <thead>
                                <tr>
                                    <th style="position: sticky; top: 0; z-index: 2;">Fecha</th>
                                    <th style="position: sticky; top: 0; z-index: 2;">Código</th>
                                    <th style="position: sticky; top: 0; z-index: 2;">Total</th>
                                    <th style="position: sticky; top: 0; z-index: 2;">Acción</th>
                                </tr>
                            </thead>
                            <!-- El tbody coincide estrictamente con la inyección de crm.js -->
                            <tbody id="prof-history-body">
                                <tr><td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">Cargando datos del cliente...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>