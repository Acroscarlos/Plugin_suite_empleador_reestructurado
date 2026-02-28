<?php
/**
 * Vista: Pestaña de Clientes (CRM) y Modales
 * 
 * Espera recibir las variables:
 * @var array $clientes Array de objetos con los datos de los clientes.
 * @var bool  $es_admin Booleano que indica si el usuario tiene privilegios de administrador.
 *
 * @package SuiteEmpleados\Views\App
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<!-- ========================================================= -->
<!-- 1. PESTAÑA PRINCIPAL (TABLA DE CLIENTES)                  -->
<!-- ========================================================= -->
<div id="TabCli" class="suite-tab-content active"> <!-- Le añadimos 'active' temporalmente para la prueba -->
    
    <!-- Barra de Herramientas Superior -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; position:relative;">
        
        <!-- Buscador DataTables -->
        <div style="width: 320px; position:relative;">
            <span class="dashicons dashicons-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></span>
            <input type="text" id="clients-table-search" placeholder="Buscar cliente por RIF o Nombre..." style="width:100%; padding: 10px 15px 10px 45px; border-radius: 8px; border: 1px solid #cbd5e1;">
        </div>
        
        <!-- Botones de Acción (Solo Admin) -->
        <?php if ( $es_admin ) : ?>
            <div>
                <!-- Nota: openModal asumimos que vive en main.js o lo dejamos como helper global en UI -->
                <button class="btn-modern-action" onclick="jQuery('#modal-add-client').fadeIn();">➕ Nuevo Cliente</button>
                <button class="btn-modern-action" onclick="jQuery('#modal-import-clients').fadeIn();">📥 Importar CSV</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Contenedor de la Tabla -->
    <div class="suite-table-responsive">
        <table id="clientsTable" class="suite-modern-table display nowrap">
            <thead>
                <tr>
                    <th>RIF</th>
                    <th>Nombre / Razón Social</th>
                    <th>Contacto</th>
                    <th>Ubicación</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( isset( $clientes ) && ! empty( $clientes ) ) : ?>
					<?php foreach ( $clientes as $c ) : ?>
						<tr>
                            <td class="font-mono"><?php echo esc_html( $c->rif_ci ); ?></td>
                            <td><strong><?php echo esc_html( $c->nombre_razon ); ?></strong></td>
                            
                            <td>
                                <?php echo esc_html( $c->telefono ); ?><br>
                                <small style="color: #64748b;"><?php echo esc_html( $c->email ); ?></small>
                            </td>
                            
                            <td><?php echo esc_html( ucwords( strtolower( $c->ciudad ) ) ); ?></td>
                            
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn-modern-action small" onclick="SuiteCRM.openProfile(<?php echo intval( $c->id ); ?>)">📂 Expediente</button>
                                    
                                    <?php 
                                    $wa_phone = preg_replace('/[^0-9]/', '', $c->telefono);
                                    if ( ! empty( $wa_phone ) ) : 
                                        if ( strlen( $wa_phone ) === 11 && strpos( $wa_phone, '0' ) === 0 ) {
                                            $wa_phone = '58' . substr( $wa_phone, 1 );
                                        } elseif ( strlen( $wa_phone ) === 10 ) {
                                            $wa_phone = '58' . $wa_phone;
                                        }
                                    ?>
                                        <a href="https://api.whatsapp.com/send?phone=<?php echo esc_attr( $wa_phone ); ?>" target="_blank" class="btn-modern-action small" style="color: #059669; border-color: #a7f3d0; background: #ecfdf5; text-decoration: none;">📱 WA</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================= -->
<!-- 2. MODALES ESPECÍFICOS DEL CRM                            -->
<!-- ========================================================= -->

<!-- Modal: Crear Nuevo Cliente -->
<div id="modal-add-client" class="suite-modal" style="display: none;">
    <div class="suite-modal-content">
        <span class="close-modal" onclick="jQuery('.suite-modal').fadeOut();">×</span>
        <h3 style="margin-top:0; color:#0f172a;">➕ Nuevo Cliente</h3>
        
        <div class="form-group-row">
            <div style="flex:1">
                <label>RIF/CI *</label>
                <input type="text" id="new-cli-rif" class="widefat" placeholder="Ej: J123456789">
            </div>
            <div style="flex:2">
                <label>Nombre/Razón Social *</label>
                <input type="text" id="new-cli-nombre" class="widefat">
            </div>
        </div>
        
        <div class="form-group-row">
            <div style="flex:1"><label>Teléfono</label><input type="text" id="new-cli-tel" class="widefat"></div>
            <div style="flex:1"><label>Email</label><input type="email" id="new-cli-email" class="widefat"></div>
        </div>
        
        <label>Dirección</label>
        <input type="text" id="new-cli-dir" class="widefat">
        
        <div class="form-group-row">
            <div style="flex:1"><label>Ciudad</label><input type="text" id="new-cli-ciudad" class="widefat"></div>
            <div style="flex:1"><label>Estado</label><input type="text" id="new-cli-estado" class="widefat"></div>
        </div>
        
        <button class="btn-save-big" id="btn-create-client" style="margin-top:15px;">Guardar Cliente</button>
    </div>
</div>

<!-- Modal: Importar Clientes Masivamente -->
<div id="modal-import-clients" class="suite-modal" style="display: none;">
    <div class="suite-modal-content">
        <span class="close-modal" onclick="jQuery('.suite-modal').fadeOut();">×</span>
        <h3 style="margin-top:0; color:#0f172a;">📥 Importar Clientes</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:20px;">
            Orden exacto del CSV: <strong>Nombre, RIF, Teléfono, Email, Dirección</strong>
        </p>
        <input type="file" id="csv-clients-file" accept=".csv" class="widefat" style="padding:10px; background:#f8fafc;">
        <button class="btn-save-big" id="btn-run-import-cli" style="margin-top:10px;">Procesar Importación</button>
    </div>
</div>

<!-- Modal: Expediente / Perfil de Cliente -->
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

#modal-client-profile .modal-body-scroll {
    display: flex;
    flex: 1;
    overflow-y: auto;
    padding: 30px;
    gap: 30px;
}

/* Columna Izquierda */
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

/* Columna Derecha */
#modal-client-profile .profile-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 25px;
}

/* Tarjetas KPI */
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

/* Historial */
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

@media (max-width: 900px) {
    #modal-client-profile .modal-body-scroll { flex-direction: column; }
    #modal-client-profile .profile-left { flex: auto; }
    #modal-client-profile .suite-modal-content.large { height: 100%; width: 100%; border-radius: 0; max-height: 100vh; }
}
</style>

<div id="modal-client-profile" class="suite-modal" style="display: none;">
    <div class="suite-modal-content large">

        <div class="modal-header-fixed">
            <h3 id="cli-profile-name" style="margin:0; color:#0f172a; font-size: 22px;">📂 Expediente del Cliente</h3>
            <span class="close-modal-float" onclick="jQuery('#modal-client-profile').fadeOut();">&times;</span>
        </div>

        <div class="modal-body-scroll">
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
                    <?php if ( $es_admin ) : ?>
                    <button id="btn-delete-profile" class="btn-modern-action" style="flex:1; color:#dc2626; border-color:#fca5a5; justify-content: center; background:#fef2f2;">🗑️ Eliminar</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-right">
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

                <div class="history-container">
                    <div class="history-header">
                        📜 Historial de Cotizaciones y Pedidos
                    </div>
                    <div class="history-scroll">
                        <table class="suite-modern-table" id="cli-history-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc;">Fecha</th>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc;">Código</th>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc;">Total</th>
                                    <th style="position: sticky; top: 0; z-index: 2; background: #f8fafc;">Acción</th>
                                </tr>
                            </thead>
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