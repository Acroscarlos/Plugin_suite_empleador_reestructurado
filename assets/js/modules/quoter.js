/**
 * SuiteQuoter - Módulo UI del Cotizador y Punto de Venta (POS)
 * Reconstrucción V11: Mobile-First, RIF Atómico y Carrito Independiente.
 */
const SuiteQuoter = (function($) {
    'use strict';

    let searchTimeout = null;
    let clientTimeout = null;
    let localCart = []; // Desacoplado de SuiteState para permitir SKUs duplicados
    let tasaBCV = 1.00;

    // ==========================================
    // MÉTODOS PRIVADOS Y DE RENDERIZADO
    // ==========================================

    const calculateTotals = function() {
        let totalUSD = 0;
        localCart.forEach(item => {
            totalUSD += (parseFloat(item.price) * parseInt(item.qty));
        });
        
        $('#pos-total-usd').text('$' + totalUSD.toFixed(2));
        $('#pos-total-bs').text('Ref. Bs: ' + (totalUSD * tasaBCV).toFixed(2));
    };

    const renderCart = function() {
        const tbody = $('#pos-cart-body');
        let html = '';

        if (localCart.length === 0) {
            html = '<div style="text-align: center; color: #94a3b8; padding: 40px 0;">El carrito está vacío. Busque un producto para comenzar.</div>';
        } else {
            localCart.forEach((item, index) => {
                let subtotal = (item.qty * item.price).toFixed(2);
                
                // Estructura de Tarjeta (Card) Responsive
                html += `
                <div class="pos-cart-item" style="display:flex; flex-wrap:wrap; gap:10px; background:#fff; border:1px solid #cbd5e1; padding:15px; border-radius:8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="flex: 1 1 100%; margin-bottom: 5px;">
                        <strong style="color:#0073aa; font-family:monospace; font-size:14px;">${item.sku}</strong> 
                        <span style="font-size:14px; color:#1e293b;">${item.name}</span>
                    </div>
                    <div style="flex: 1 1 90px;">
                        <label style="font-size:11px; color:#64748b; display:block; margin-bottom:4px;">Precio ($)</label>
                        <input type="number" step="0.01" class="widefat cart-input-price" data-index="${index}" value="${item.price}" style="margin:0;">
                    </div>
                    <div style="flex: 1 1 70px;">
                        <label style="font-size:11px; color:#64748b; display:block; margin-bottom:4px;">Cant.</label>
                        <input type="number" min="1" class="widefat cart-input-qty" data-index="${index}" value="${item.qty}" style="margin:0;">
                    </div>
                    <div style="flex: 1 1 120px;">
                        <label style="font-size:11px; color:#64748b; display:block; margin-bottom:4px;">Tiempo Entrega</label>
                        <input type="text" class="widefat cart-input-time" data-index="${index}" value="${item.time}" style="margin:0;">
                    </div>
                    <div style="flex: 1 1 80px; text-align:right;">
                        <label style="font-size:11px; color:#64748b; display:block; margin-bottom:4px;">Subtotal</label>
                        <div style="font-size:15px; font-weight:bold; color:#059669; margin-top:8px;">$${subtotal}</div>
                    </div>
                    <div style="flex: 0 0 40px; display:flex; align-items:flex-end; justify-content:center;">
                        <button type="button" class="btn-modern-action btn-remove-item" data-index="${index}" style="color:#dc2626; border-color:#fca5a5; padding:8px 10px;">🗑️</button>
                    </div>
                </div>
                `;
            });
        }
        
        tbody.html(html);
        calculateTotals();
    };

    /**
     * Helper para bloquear/desbloquear formulario del cliente
     */
    const lockClientForm = function(lock) {
        if (lock) {
            $('#cli-form-wrapper input:not(#cli-search-predictive)').prop('readonly', true).css('background-color', '#f1f5f9');
            $('#cli-rif-prefix').css('pointer-events', 'none').css('background-color', '#f1f5f9');
            $('#btn-clear-client').fadeIn();
        } else {
            $('#cli-form-wrapper input:not(#cli-search-predictive)').prop('readonly', false).css('background-color', '#fff');
            $('#cli-rif-prefix').css('pointer-events', 'auto').css('background-color', '#fff');
            $('#btn-clear-client').fadeOut();
        }
    };

    // ==========================================
    // EVENT LISTENERS
    // ==========================================
	/**
     * Automatización API Dolar BCV
     */
    const fetchDolarAPI = async function() {
        try {
            const response = await fetch('https://ve.dolarapi.com/v1/dolares/oficial');
            const data = await response.json();
            
            if (data && data.promedio) {
                // 1. Actualizar la variable global del carrito
                tasaBCV = parseFloat(data.promedio);
                
                // 2. Actualizar el input visible para el vendedor
                $('#pos-tasa-bcv').val(tasaBCV.toFixed(4));
                
                // 3. Formatear la fecha (DD/MM/YYYY HH:MM)
                const fecha = new Date(data.fechaActualizacion);
                const fechaFormat = fecha.toLocaleDateString('es-VE') + ' ' + fecha.toLocaleTimeString('es-VE', {hour: '2-digit', minute:'2-digit'});
                
                // 4. Mostrar confirmación visual al vendedor
                $('#bcv-update-date').text('✅ BCV: ' + fechaFormat).css('color', '#059669');
                
                // 5. Recalcular los totales en Bs del carrito
                calculateTotals();
            }
        } catch (error) {
            console.error('Error conectando a DolarAPI:', error);
            $('#bcv-update-date').text('❌ Error API. Use tasa manual.').css('color', '#dc2626');
        }
    };
	
	
	
    const bindEvents = function() {
        
		// 1. CARACTERÍSTICA: RIF ATÓMICO Y ESTRICTO
        $('#cli-rif-number').on('input', function() {
            // Bloquea cualquier carácter que no sea número
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // 1.5 DETECCIÓN AUTOMÁTICA POR RIF (Auto-carga al perder el foco)
        $('#cli-rif-number').on('blur', function() {
            const numeroRif = $(this).val().trim();

            // Validar longitud lógica (mayor a 5 caracteres)
            if (numeroRif.length <= 5) return;

            // Concatenar prefijo y número para formar el RIF completo en mayúsculas
            const prefijo = $('#cli-rif-prefix').val();
            const rifCompleto = (prefijo + numeroRif).toUpperCase();

            // Mostrar un pequeño indicador visual de búsqueda
            const inputField = $(this);
            inputField.css('opacity', '0.6');

            // Consultar a la base de datos (API)
            SuiteAPI.post('suite_search_client_ajax', { term: rifCompleto }).then(res => {
                if (res.success && res.data && res.data.length > 0) {

                    // Buscar coincidencia EXACTA en los resultados devueltos
                    const cliente = res.data.find(c => c.rif_ci.toUpperCase() === rifCompleto);

                    if (cliente) {
                        // Autocompletar campos del formulario
                        $('#cli-nombre').val(cliente.nombre_razon);
                        $('#cli-tel').val(cliente.telefono);
                        $('#cli-email').val(cliente.email);
                        $('#cli-dir').val(cliente.direccion);
                        $('#cli-ciudad').val(cliente.ciudad);
                        $('#cli-estado').val(cliente.estado);
                        $('#cli-contacto').val(cliente.contacto_persona);

                        // Bloquear formulario para prevenir mutaciones indeseadas
                        lockClientForm(true);

                        // Limpiar el buscador predictivo por seguridad
                        $('#cli-search-predictive').val('');
                        $('#cli-search-results').hide().empty();

                        // Mostrar mensaje de éxito temporal (Feedback sutil)
                        $('#rif-auto-feedback').remove(); // Limpiar si ya existe
                        $('<div id="rif-auto-feedback" style="color: #059669; font-size: 12px; margin-top: 5px; font-weight: bold;">✅ Cliente detectado en la base de datos y cargado automáticamente.</div>')
                            .insertAfter(inputField.parent())
                            .delay(4000)
                            .fadeOut(400, function() { $(this).remove(); });
                    }
                }
            }).catch(err => {
                // Fallo silencioso: Si hay error de red, dejamos que el vendedor siga llenando manual
                console.warn('Auto-detección de RIF omitida por error de red.', err);
            }).finally(() => {
                inputField.css('opacity', '1');
            });
        });

        // 2. BUSCADOR PREDICTIVO DE CLIENTES
        $('#cli-search-predictive').on('keyup', function() {
            clearTimeout(clientTimeout);
            let term = $(this).val().trim();
            let resBox = $('#cli-search-results');

            if (term.length < 3) { resBox.hide().empty(); return; }

            clientTimeout = setTimeout(() => {
                resBox.show().html('<div style="padding:10px; color:#64748b;">Buscando cliente...</div>');
                SuiteAPI.post('suite_search_client_ajax', { term: term }).then(res => {
                    if (res.success && res.data.length > 0) {
                        let html = '';
                        res.data.forEach(c => {
                            // Guardamos la data cruda en base64 para evitar rupturas de comillas
                            const safeData = btoa(unescape(encodeURIComponent(JSON.stringify(c))));
                            html += `<div class="pos-item-result cli-result-item" data-client="${safeData}" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;">
                                        <div class="prod-info"><strong>${c.rif_ci}</strong> ${c.nombre_razon}</div>
                                     </div>`;
                        });
                        resBox.html(html);
                    } else {
                        resBox.html('<div style="padding:10px; color:#64748b;">No se encontraron coincidencias.</div>');
                    }
                });
            }, 400);
        });

        // 3. SELECCIONAR CLIENTE (Auto-completar y Bloquear)
        $(document).on('click', '.cli-result-item', function() {
            const rawData = $(this).data('client');
            const c = JSON.parse(decodeURIComponent(escape(atob(rawData))));
            
            // Separar RIF Atómico
            const prefix = c.rif_ci.charAt(0).toUpperCase();
            const number = c.rif_ci.substring(1);
            
            // Validar que el prefijo exista en el select, si no, fallback a 'V'
            if (["V","E","J","G","P","C"].includes(prefix)) {
                $('#cli-rif-prefix').val(prefix);
            } else {
                $('#cli-rif-prefix').val('V');
            }
            
            $('#cli-rif-number').val(number);
            $('#cli-nombre').val(c.nombre_razon);
            $('#cli-tel').val(c.telefono);
            $('#cli-email').val(c.email);
            $('#cli-dir').val(c.direccion);
            $('#cli-ciudad').val(c.ciudad);
            $('#cli-estado').val(c.estado);
            $('#cli-contacto').val(c.contacto_persona);

            // Bloquear formulario y limpiar buscador
            lockClientForm(true);
            $('#cli-search-predictive').val('');
            $('#cli-search-results').hide().empty();
        });

        // 4. LIMPIAR CLIENTE (Desbloquear)
        $('#btn-clear-client').on('click', function() {
            $('#cli-form-wrapper input:not(#cli-search-predictive)').val('');
            $('#cli-rif-prefix').val('J');
            lockClientForm(false);
        });

        // 5. BUSCADOR PREDICTIVO DE PRODUCTOS (Con Placeholders de CSV)
        $('#pos-product-search').on('keyup', function() {
            clearTimeout(searchTimeout);
            let term = $(this).val().trim();
            let resultsBox = $('#pos-search-results');

            if (term.length < 3) { resultsBox.hide().empty(); return; }

            searchTimeout = setTimeout(() => {
                resultsBox.show().html('<div style="padding:10px; color:#64748b;">Buscando catálogo...</div>');
                
                SuiteAPI.post('suite_search_pos', { term: term }).then(res => {
                    if (res.success && res.data.length > 0) {
                        let listHTML = '';
                        res.data.forEach(prod => {
                            // Placeholders requeridos (Se nutren del Data Lake / CSV a futuro)
                            let dispGaleria = prod.stock_gale !== undefined ? prod.stock_gale : 'N/D';
                            let stockTotal = prod.stock_total !== undefined ? prod.stock_total : dispGaleria; 
                            
                            listHTML += `
                            <div class="pos-item-result search-result-item" 
                                 data-sku="${prod.sku}" data-name="${prod.nombre}" data-price="${prod.precio}" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                <div class="prod-info">
                                    <strong>${prod.sku}</strong> ${prod.nombre} 
                                    <div style="font-size:11px; color:#64748b; margin-top:4px;">
                                        📦 Disp. Galerías: <strong>${dispGaleria}</strong> | Total: <strong>${stockTotal}</strong>
                                    </div>
                                </div>
                                <div class="prod-price" style="font-weight:bold; color:#059669;">$${prod.precio}</div>
                            </div>`;
                        });
                        resultsBox.html(listHTML);
                    } else {
                        resultsBox.html('<div style="padding: 10px;">No se encontraron productos.</div>');
                    }
                }).catch(() => {
                    resultsBox.html('<div style="padding: 10px; color: #dc2626;">❌ Error de conexión al buscar. Intente nuevamente.</div>');
                });
            }, 400);
        });

        // 6. AGREGAR AL CARRITO (Permite duplicados directos)
        $(document).on('click', '.search-result-item', function() {
            let el = $(this);
            localCart.push({
                sku: el.data('sku'),
                name: el.data('name'),
                price: parseFloat(el.data('price')),
                qty: 1,
                time: 'Inmediata'
            });

            $('#pos-product-search').val('');
            $('#pos-search-results').hide().empty();
            renderCart();
        });

        // 7. AGREGAR ÍTEM MANUAL
        $('#btn-add-manual-item').on('click', function(e) {
            e.preventDefault();
            let desc = prompt("Ingrese la descripción del producto manual:");
            if (!desc) return;
            let price = prompt("Ingrese el Precio Unitario ($):");
            if (!price || isNaN(parseFloat(price))) return alert("Precio inválido.");

            localCart.push({
                sku: 'MANUAL',
                name: desc.trim(),
                price: parseFloat(price),
                qty: 1,
                time: 'Inmediata'
            });
            renderCart();
        });

        // 8. DELEGACIÓN DE EVENTOS DEL CARRITO (Edición en vivo)
        $('#pos-cart-body').on('change input', '.cart-input-qty', function() {
            let idx = $(this).data('index');
            localCart[idx].qty = Math.max(1, parseInt($(this).val()) || 1);
            renderCart();
        });

        $('#pos-cart-body').on('change', '.cart-input-price', function() {
            let idx = $(this).data('index');
            localCart[idx].price = Math.max(0, parseFloat($(this).val()) || 0);
            renderCart();
        });

        $('#pos-cart-body').on('change input', '.cart-input-time', function() {
            let idx = $(this).data('index');
            localCart[idx].time = $(this).val().trim();
        });

        $('#pos-cart-body').on('click', '.btn-remove-item', function() {
            let idx = $(this).data('index');
            localCart.splice(idx, 1);
            renderCart();
        });

        // 9. EVENTO DE GUARDADO (Punto Crítico Financiero)
        $('#btn-save-quote').on('click', function(e) {
            e.preventDefault();

            if (localCart.length === 0) return alert('⚠️ No puede guardar una cotización vacía.');

            const numRif = $('#cli-rif-number').val().trim();
            if (!numRif) return alert('⚠️ El número de RIF/CI es obligatorio.');

            // Ensamblaje del RIF Atómico
            const finalRif = $('#cli-rif-prefix').val() + numRif;
            const finalName = $('#cli-nombre').val().trim();

            if (!finalName) return alert('⚠️ La Razón Social es obligatoria.');

            const btn = $(this);
            btn.prop('disabled', true).text('Procesando...');

            const payload = {
                rif: finalRif,
                nombre: finalName,
                direccion: $('#cli-dir').val().trim(),
                telefono: $('#cli-tel').val().trim(),
                email: $('#cli-email').val().trim(),
                ciudad: $('#cli-ciudad').val().trim(),
                estado: $('#cli-estado').val().trim(),
                contacto: $('#cli-contacto').val().trim(),
                notas: '', // Si se desea habilitar, agregar input
                items: localCart,
                moneda: $('#pos-moneda').val(),
                tasa: tasaBCV,
                validez: $('#pos-validez').val() || 5
            };

            SuiteAPI.post('suite_save_quote_crm', payload).then(res => {
                if (res.success) {
                    alert('✅ Cotización generada con éxito. Código: ' + res.data.id);
                    // Abrir PDF usando el ID interno + Nonce CSRF estricto
                    window.open(suite_vars.ajax_url + '?action=suite_print_quote&id=' + res.data.internal_id + '&nonce=' + suite_vars.nonce, '_blank');
                    
                    // Resetear Interfaz
                    localCart = [];
                    renderCart();
                    $('#btn-clear-client').click(); // Limpia y desbloquea el formulario
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                }
            }).catch(() => {
                alert('❌ Error crítico de conexión al generar la cotización.');
            }).finally(() => {
                btn.prop('disabled', false).text('💾 Guardar Cotización');
            });
        });
    };

	// ==========================================
    // API PÚBLICA
    // ==========================================
    return {
        init: function() {
            let initialTasa = parseFloat($('#pos-tasa-bcv').val());
            if (initialTasa && !isNaN(initialTasa)) {
                tasaBCV = initialTasa;
            }
            
            // Listener: Recalcular totales si el vendedor cambia la tasa BCV manualmente
            $('#pos-tasa-bcv').on('input change', function() {
                let nuevaTasa = parseFloat($(this).val());
                if (nuevaTasa > 0) {
                    tasaBCV = nuevaTasa;
                    calculateTotals();
                }
            });

            bindEvents();
            renderCart();
            
            // ¡Disparar el robot del BCV al abrir la pantalla!
            fetchDolarAPI();
        },
        // Expuesto por si se requiere clonar o vaciar desde afuera
        setCart: function(newCart) { 
            localCart = newCart; 
            renderCart(); 
        }
    };

})(jQuery);