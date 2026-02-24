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
                            <td><?php echo esc_html( $c->ciudad ); ?></td>
                            <td>
                                <!-- USO DE LA NUEVA ARQUITECTURA JS -->
                                <button class="btn-modern-action small" onclick="SuiteCRM.openProfile(<?php echo intval( $c->id ); ?>)">
                                    📂 Expediente
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5" class="text-center">No hay clientes registrados en la base de datos.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================================= -->
<!-- 2. MODALES ESPECÍFICOS DEL CRM                            -->
<!-- ========================================================= -->

<!-- Modal: Crear Nuevo Cliente -->
<div id="modal-add-client" class="suite-modal">
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
<div id="modal-import-clients" class="suite-modal">
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
<div id="modal-client-profile" class="suite-modal">
    <div class="suite-modal-content large">
        <div class="modal-header-fixed">
            <h3 style="margin:0; font-size:20px; color:#0f172a;">📂 Expediente Cliente</h3>
            <span class="close-modal" onclick="jQuery('.suite-modal').fadeOut();">×</span>
        </div>
        <div class="modal-body-scroll">
            
            <!-- Izquierda: Formulario de Datos -->
            <div class="profile-left">
                <input type="hidden" id="prof-id">
                <label>Razón Social</label>
                <input type="text" id="prof-nombre" class="widefat">
                
                <div class="form-group-row">
                    <div style="flex:1"><label>RIF/CI</label><input type="text" id="prof-rif" class="widefat"></div>
                    <div style="flex:1"><label>Teléfono</label><input type="text" id="prof-tel" class="widefat"></div>
                </div>
                
                <label>Email</label>
                <input type="text" id="prof-email" class="widefat">
                
                <label>Dirección</label>
                <input type="text" id="prof-dir" class="widefat">
                
                <div class="form-group-row">
                    <div style="flex:1"><label>Ciudad</label><input type="text" id="prof-ciudad" class="widefat"></div>
                    <div style="flex:1"><label>Estado</label><input type="text" id="prof-estado" class="widefat"></div>
                </div>
                
                <label>Contacto Persona</label>
                <input type="text" id="prof-contacto" class="widefat">
                
                <label>📝 Notas Internas</label>
                <textarea id="prof-notas" class="widefat" rows="3"></textarea>
                
                <button class="btn-save-big" id="btn-update-profile" style="margin-bottom:10px; background-color:#0f172a;">Guardar Cambios</button>
                <?php if ( $es_admin ) : ?>
                <button class="btn-modern-action" id="btn-delete-profile" style="width:100%; justify-content:center; color:#dc2626; border-color:#fca5a5; background:#fef2f2;">🗑️ Eliminar Cliente</button>
                <?php endif; ?>
            </div>
            
            <!-- Derecha: KPIs e Historial -->
            <div class="profile-right">
                <div class="kpi-row">
                    <div class="kpi-card"><small>Total Gastado</small><strong id="kpi-total">$0.00</strong></div>
                    <div class="kpi-card"><small>Cotizaciones</small><strong id="kpi-count">0</strong></div>
                    <div class="kpi-card"><small>Última Compra</small><strong id="kpi-last">-</strong></div>
                </div>
                
                <h4>📜 Historial de Cotizaciones</h4>
                <div class="history-container">
                    <div class="history-scroll suite-table-responsive" style="border:none;">
                        <table class="suite-modern-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Código</th>
                                    <th>Total</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <!-- JS inyectará las filas aquí -->
                            <tbody id="prof-history-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
