/**
 * SuiteHistorial - Módulo de Historial de Cotizaciones
 * Permite buscar, imprimir, enviar por WA y CLONAR pedidos hacia el Cotizador.
 */
const SuiteHistorial = (function($) {
    'use strict';
    let table = null;

    const initTable = function() {
        if ($('#historyTable').length && !$.fn.DataTable.isDataTable('#historyTable')) {

			
			table = $('#historyTable').DataTable({
                responsive: true,
                language: { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                order: [[0, 'desc']],
                columnDefs: [
                    {
                        targets: 0,
                        render: function(data, type, row) {
                            // Si DataTables está ordenando internamente, usa el número matemático
                            if (type === 'sort' || type === 'type') {
                                return data.sort; 
                            }
                            // Si DataTables está pintando la pantalla, muestra el texto bonito
                            return data.display; 
                        }
                    }
                ]
            });
        }
    };

    const loadHistory = function() {
        if (!table) return;
        
        SuiteAPI.post('suite_get_history_ajax').then(res => {
            if (res.success && res.data) {
                table.clear();
                
                res.data.forEach(r => {
                    let badgeClass = (r.estado === 'pagado' || r.estado === 'despachado') ? 'pill-critico' : 'pill-neutral';
                    
                    // Botón WhatsApp
                    let waBtn = '';
                    if (r.wa_phone) {
                        let msg = encodeURIComponent(`Hola ${r.cliente_nombre}, le enviamos su cotización ${r.codigo_cotizacion}: ${suite_vars.ajax_url}?action=suite_print_quote&id=${r.id}`);
                        waBtn = `<a href="https://api.whatsapp.com/send?phone=${r.wa_phone}&text=${msg}" target="_blank" class="btn-modern-action small" style="color:#10b981; border-color:#a7f3d0;" title="WhatsApp">📱 WA</a>`;
                    }

                    // Botón Imprimir PDF
                    let printBtn = `<a href="${suite_vars.ajax_url}?action=suite_print_quote&id=${r.id}&nonce=${suite_vars.nonce}" target="_blank" class="btn-modern-action small" style="color:#475569;" title="Imprimir PDF">🖨️</a>`;

					
					
					
					
                    // Botón Clonar
                    let cloneBtn = `<button class="btn-modern-action small btn-clone-quote" data-id="${r.id}" style="color:#0073aa; border-color:#bae6fd;" title="Clonar al Cotizador">🔄 Clonar</button>`;

                    // NUEVO: Botón Retención y Color de Alerta
                    let retencionBtn = '';
                    let rowBgColor = '';
                    if (r.is_pending_retention) {
                        retencionBtn = `<button class="btn-modern-action small btn-upload-retencion" data-id="${r.id}" style="background:#fef08a; color:#854d0e; border-color:#fde047; font-weight:bold;" title="Subir Retención Fiscal">📥 Adjuntar Retención</button>`;
                        rowBgColor = '#fef9c3'; // Amarillo tenue
                    }

                    let rowNode = table.row.add([
                        { display: r.fecha_fmt, sort: r.fecha_cruda }, 
                        `<strong>#${r.codigo_cotizacion}</strong>`,
                        r.cliente_nombre,
                        `$${r.total_fmt}`,
                        `<span class="status-pill ${badgeClass}">${r.estado.toUpperCase()}</span>`,
                        `<div style="display:flex; gap:5px;">${printBtn} ${waBtn} ${cloneBtn} ${retencionBtn}</div>`
                    ]).node();

                    // Aplicar el color de fondo a la fila si está pendiente
                    if (rowBgColor) {
                        $(rowNode).css('background-color', rowBgColor);
                    }
					
					
					
                });
                
                table.draw();
            }
        });
    };

    const bindEvents = function() {
        // Evento Delegado: CLONAR COTIZACIÓN
        $('#historyTable').on('click', '.btn-clone-quote', function(e) {
            e.preventDefault();
            const quoteId = $(this).data('id');
            const btn = $(this);
            
            if (!confirm('¿Desea clonar esta cotización? Los datos actuales del cotizador se sobrescribirán.')) return;
            
            btn.prop('disabled', true).text('⏳...');

            SuiteAPI.post('suite_get_quote_details_ajax', { id: quoteId }).then(res => {
                if (res.success) {
                    const data = res.data;
                    
                    // 1. Inyectar Cliente en el formulario del Cotizador
                    if (data.cliente) {
                        const c = data.cliente;
                        const prefix = c.rif_ci.charAt(0).toUpperCase();
                        const number = c.rif_ci.substring(1);
                        
                        $('#cli-rif-prefix').val(["V","E","J","G","P","C"].includes(prefix) ? prefix : 'V');
                        $('#cli-rif-number').val(number);
                        $('#cli-nombre').val(c.nombre_razon);
                        $('#cli-tel').val(c.telefono);
                        $('#cli-email').val(c.email);
                        $('#cli-dir').val(c.direccion);
                        $('#cli-ciudad').val(c.ciudad);
                        $('#cli-estado').val(c.estado);
                        $('#cli-contacto').val(c.contacto_persona);
                        
                        // NOTA: No bloqueamos el form (lockClientForm) para permitirle modificarlo si gusta
                    }
                    
                    // 2. Mapear y setear el Carrito (Se usa la API pública de SuiteQuoter)
                    if (data.items) {
                        const newCart = data.items.map(item => ({
                            sku: item.sku,
                            name: item.producto_nombre,
                            price: parseFloat(item.precio_unitario_usd),
                            qty: parseInt(item.cantidad),
                            time: item.tiempo_entrega || 'Inmediata'
                        }));
                        SuiteQuoter.setCart(newCart);
                    }
                    
                    // 3. Redirección visual automática a la Pestaña del Cotizador
                    const cotizadorBtn = $('.tab-btn:contains("Cotizador")');
                    if (cotizadorBtn && typeof window.openSuiteTab === 'function') {
                        window.openSuiteTab('TabPos', cotizadorBtn);
                    }

                } else {
                    alert('❌ Error al clonar: ' + (res.data.message || res.data));
                }
            }).catch(() => {
                alert('❌ Ocurrió un error de red al intentar clonar.');
            }).finally(() => {
                btn.prop('disabled', false).html('🔄 Clonar');
            });
        });
		
		
		// Disparador Súper-Modal de Retención Fiscal
        $('#historyTable').on('click', '.btn-upload-retencion', async function(e) {
            e.preventDefault();
            const quoteId = $(this).data('id');

            const { value: file } = await Swal.fire({
                title: 'Adjuntar Retención',
                text: 'Suba la planilla de retención del cliente para liberar este pedido de las alertas.',
                input: 'file',
                inputAttributes: { 'accept': 'application/pdf, image/*' },
                showCancelButton: true,
                confirmButtonText: 'Subir Archivo',
                cancelButtonText: 'Cancelar'
            });

            if (file) {
                // --- 🛡️ NUEVO: BARRERA DE PESO (5MB MAX) ---
                const maxSizeBytes = 5 * 1024 * 1024; // 5 Megabytes en bytes
                if (file.size > maxSizeBytes) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archivo muy pesado',
                        text: 'El comprobante de retención no debe superar los 5MB para no saturar el servidor. Por favor, comprima el PDF o reduzca la calidad de la imagen e intente de nuevo.',
                        confirmButtonColor: '#d97706'
                    });
                    return; // 🛑 Detenemos la subida inmediatamente
                }
                // -------------------------------------------

                Swal.fire({ title: 'Subiendo documento...', allowOutsideClick: false });
                Swal.showLoading();

                let formData = new FormData();
                formData.append('action', 'suite_upload_retention');
				
				
				
                formData.append('nonce', suite_vars.nonce);
                formData.append('quote_id', quoteId);
                formData.append('retencion_file', file);

                SuiteAPI.postForm('suite_upload_retention', formData).then(res => {
                    if (res.success) {
                        Swal.fire('¡Éxito!', 'Retención adjuntada. El expediente ha sido actualizado.', 'success');
                        loadHistory(); // Recarga la tabla de inmediato
                    } else {
                        Swal.fire('Error', res.data.message || 'Error del servidor.', 'error');
                    }
                }).catch(() => Swal.fire('Error', 'Falla de conexión al subir el archivo.', 'error'));
            }
        });
		
		// NUEVO: Modal Asíncrono para Buzón Fiscal Manual
        $('#btn-upload-manual-fiscal').on('click', function(e) {
            e.preventDefault();

            Swal.fire({
                title: 'Buzón Fiscal Externo',
                html: `
                    <div style="text-align: left; margin-top: 15px;">
                        <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Usa este buzón SOLO para documentos rezagados o de meses contables cerrados.</p>
                        <input type="text" id="manual-cliente" class="suite-input" placeholder="Nombre completo del Cliente *" style="width: 100%; margin-bottom: 10px; padding: 10px;" required>
                        <input type="text" id="manual-rif" class="suite-input" placeholder="RIF / CI (Ej: J-12345678) *" style="width: 100%; margin-bottom: 10px; padding: 10px;" required>

                        <select id="manual-tipo" class="suite-input" style="width: 100%; margin-bottom: 15px; padding: 10px;">
                            <option value="Factura Fiscal">Factura Fiscal</option>
                            <option value="Retención Fiscal">Retención Fiscal</option>
                        </select>

                        <label style="font-size: 12px; font-weight: bold; color: #475569; display: block; margin-bottom: 5px;">Adjuntar Archivo (Max 3.5MB)</label>
                        <input type="file" id="manual-file" class="suite-input" accept="application/pdf, image/*" style="width: 100%; padding: 8px;">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '📤 Enviar a Contabilidad',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4f46e5',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const cliente = document.getElementById('manual-cliente').value.trim();
                    const rif = document.getElementById('manual-rif').value.trim();
                    const tipo = document.getElementById('manual-tipo').value;
                    const fileInput = document.getElementById('manual-file');

                    if (!cliente || !rif || fileInput.files.length === 0) {
                        Swal.showValidationMessage('Por favor completa todos los campos obligatorios.');
                        return false;
                    }

                    const file = fileInput.files[0]; // <-- CORRECCIÓN CRÍTICA DE LA IA
                    
                    // Validación JS estricta (3.5MB = 3670016 bytes)
                    if (file.size > 3670016) {
                        Swal.showValidationMessage('El archivo supera el límite de 3.5MB permitido.');
                        return false;
                    }

                    let formData = new FormData();
                    formData.append('cliente', cliente);
                    formData.append('rif', rif);
                    formData.append('tipo', tipo);
                    formData.append('fiscal_file', file);

                    // <-- CORRECCIÓN: Usar nuestra arquitectura V8 nativa
                    return SuiteAPI.postForm('suite_upload_manual_document', formData).then(res => {
                        if (!res.success) throw new Error(res.data.message || 'Error del servidor');
                        return res;
                    }).catch(error => {
                        Swal.showValidationMessage(`Error: ${error.message}`);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('¡Enviado!', 'El documento ha sido sellado y enviado al canal de contabilidad.', 'success');
                }
            });
        });
		
		
    };

    return {
        init: function() {
            initTable();
            bindEvents();
            loadHistory();
        },
        refresh: loadHistory
    };
})(jQuery);