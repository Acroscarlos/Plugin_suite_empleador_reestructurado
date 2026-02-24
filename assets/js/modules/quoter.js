/**
 * SuiteQuoter - Módulo UI del Cotizador y Punto de Venta (POS)
 * 
 * Conecta el DOM del HTML con el Manejador de Estado (SuiteState).
 */
const SuiteQuoter = (function($) {
    'use strict';

    let searchTimeout = null;

    // ==========================================
    // MÉTODOS VISUALES (Render)
    // ==========================================

    /**
     * Lee el Estado Inmutable y repinta la tabla del carrito.
     */
    const renderCart = function() {
        const cart = SuiteState.getCart();
        const totals = SuiteState.getTotals();
        const tbody = $('#pos-cart-body');
        let html = '';

        if (cart.length === 0) {
            html = '<tr><td colspan="6" class="text-center text-gray-500 py-4">El carrito está vacío. Busque un producto para comenzar.</td></tr>';
        } else {
            cart.forEach((item, index) => {
                let subtotal = (item.qty * item.price).toFixed(2);
                html += `
                <tr class="cart-item-row">
                    <td class="font-mono text-sm">${item.sku}</td>
                    <td class="font-bold">${item.name}</td>
                    <td class="text-center">
                        <input type="number" min="1" class="cart-input-qty" data-index="${index}" value="${item.qty}" style="width: 70px; text-align: center;">
                    </td>
                    <td class="text-center">
                        $<input type="number" step="0.01" min="0" class="cart-input-price" data-index="${index}" value="${item.price.toFixed(2)}" style="width: 90px; text-align: right;">
                    </td>
                    <td class="text-right font-bold text-green-700">$${subtotal}</td>
                    <td class="text-center">
                        <button type="button" class="btn-remove-item" data-index="${index}" style="color: #dc2626; background: none; border: none; cursor: pointer; font-size: 16px;">❌</button>
                    </td>
                </tr>`;
            });
        }

        tbody.html(html);

        // Actualizar UI de Totales
        $('#pos-total-usd').text(`$${totals.usd}`);
        $('#pos-total-bs').text(`Bs ${totals.bs}`);
    };

    // ==========================================
    // EVENT LISTENERS (Delegación de eventos)
    // ==========================================

    const bindEvents = function() {
        
        // 1. Delegación para Inputs Dinámicos (Cantidades y Precios en el carrito)
        $('#pos-cart-body').on('change input', '.cart-input-qty', function() {
            let index = $(this).data('index');
            let val = $(this).val();
            SuiteState.updateItem(index, 'qty', val);
            renderCart(); // Repintar tras el cambio
        });

        $('#pos-cart-body').on('change', '.cart-input-price', function() {
            let index = $(this).data('index');
            let val = $(this).val();
            SuiteState.updateItem(index, 'price', val);
            renderCart();
        });

        // 2. Delegación para Botón de Eliminar Item
        $('#pos-cart-body').on('click', '.btn-remove-item', function(e) {
            e.preventDefault();
            let index = $(this).data('index');
            SuiteState.removeItem(index);
            renderCart();
        });

        // 3. Búsqueda de Productos (Predictivo con Debounce)
        $('#pos-product-search').on('keyup', function() {
            clearTimeout(searchTimeout);
            let term = $(this).val().trim();
            let resultsBox = $('#pos-search-results');

            if (term.length < 3) {
                resultsBox.hide().empty();
                return;
            }

            searchTimeout = setTimeout(() => {
                resultsBox.show().html('<div class="p-2 text-gray-500">Buscando...</div>');
                
                // Asumiendo que migraremos este endpoint en la próxima sesión (Inventory)
                SuiteAPI.post('suite_search_inventory', { term: term }).then(res => {
                    if (res.success && res.data.length > 0) {
                        let listHTML = '';
                        res.data.forEach(prod => {
                            // Usamos data-attributes para almacenar la data pura y evitar errores de parseo
                            listHTML += `
                            <div class="search-result-item p-2 hover:bg-gray-100 cursor-pointer border-b" 
                                 data-sku="${prod.sku}" 
                                 data-name="${prod.nombre}" 
                                 data-price="${prod.precio}">
                                <strong>${prod.sku}</strong> - ${prod.nombre} <span class="float-right text-green-600">$${prod.precio}</span>
                            </div>`;
                        });
                        resultsBox.html(listHTML);
                    } else {
                        resultsBox.html('<div class="p-2 text-red-500">No se encontraron productos.</div>');
                    }
                }).catch(() => {
                    resultsBox.html('<div class="p-2 text-red-500">❌ Error de conexión al buscar. Intente nuevamente.</div>');
                });
            }, 400); // 400ms delay para no saturar el servidor
        });

        // 4. Seleccionar Producto del Predictivo
        $(document).on('click', '.search-result-item', function() {
            let el = $(this);
            SuiteState.addItem({
                sku: el.data('sku'),
                name: el.data('name'),
                price: parseFloat(el.data('price')),
                qty: 1
            });
            
            // Limpiar buscador UI
            $('#pos-product-search').val('');
            $('#pos-search-results').hide().empty();
            renderCart();
        });

        // 5. Agregar Ítem Manualmente (Productos no catalogados)
        $('#btn-add-manual-item').on('click', function(e) {
            e.preventDefault();
            let desc = $('#manual-item-desc').val().trim();
            let price = $('#manual-item-price').val();
            let qty = $('#manual-item-qty').val() || 1;

            if (!desc || !price) return alert('Descripción y Precio son obligatorios.');

            SuiteState.addItem({
                sku: 'GENERICO',
                name: desc,
                price: parseFloat(price),
                qty: parseInt(qty)
            });

            // Limpiar UI manual
            $('#manual-item-desc, #manual-item-price').val('');
            $('#manual-item-qty').val('1');
            renderCart();
        });

        // 6. Guardar Cotización (Punto Crítico Financiero)
        $('#btn-save-quote').on('click', function(e) {
            e.preventDefault();
            
            const cart = SuiteState.getCart();
            if (cart.length === 0) {
                return alert('⚠️ No puede guardar una cotización vacía.');
            }

            const clientData = {
                rif: $('#cli-rif').val().trim(),
                nombre: $('#cli-nombre').val().trim(),
                direccion: $('#cli-dir').val().trim(),
                telefono: $('#cli-tel').val().trim(),
                email: $('#cli-email').val().trim(),
                ciudad: $('#cli-ciudad').val().trim(),
                estado: $('#cli-estado').val().trim(),
                contacto: $('#cli-contacto').val().trim(),
                notas: $('#cli-notas').val().trim()
            };

            // Validar que se seleccionó un cliente (o se escribió)
            if (!clientData.rif || !clientData.nombre) {
                return alert('⚠️ Debe ingresar o seleccionar los datos del cliente (Razón Social y RIF).');
            }

            const btn = $(this);
            btn.prop('disabled', true).text('Procesando...');

            const payload = {
                rif: clientData.rif,
                nombre: clientData.nombre,
                direccion: clientData.direccion,
                telefono: clientData.telefono,
                email: clientData.email,
                ciudad: clientData.ciudad,
                estado: clientData.estado,
                contacto: clientData.contacto,
                notas: clientData.notas,
                items: cart, // El servidor recibirá el array estructurado
                moneda: $('#pos-moneda').val(),
                tasa: SuiteState.getTotals().tasa,
                validez: $('#pos-validez').val() || 15
            };

            // USO DE LA NUEVA API
            SuiteAPI.post('suite_save_quote_crm', payload).then(res => {
                if (res.success) {
                    alert('✅ Cotización generada con éxito. Código: ' + res.data.id);
                    
                    // Abrir PDF en nueva pestaña (usamos el ID interno provisto por el backend)
                    window.open(suite_vars.ajax_url + '?action=suite_print_quote&id=' + res.data.internal_id, '_blank');
                    
                    // Resetear App Visual
                    SuiteState.clearCart();
                    renderCart();
                    $('#cli-form-wrapper input, #cli-form-wrapper textarea').val(''); // Limpiar form cliente
                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                }
            }).catch(() => {
                alert('❌ Error crítico de conexión al generar la cotización.');
            }).finally(() => {
                btn.prop('disabled', false).text('Guardar Cotización / Pedido');
            });
        });
    };

    // ==========================================
    // API PÚBLICA (Métodos Revelados)
    // ==========================================
    return {
        init: function() {
            // Leer tasa inicial desde un input oculto en el HTML o variable global
            let initialTasa = $('#hidden-tasa-bcv').val();
            if (initialTasa) {
                SuiteState.setTasa(initialTasa);
            }
            
            bindEvents();
            renderCart(); // Render inicial (vacío)
        },
        renderCart: renderCart // Se expone por si otros módulos necesitan forzar un repintado
    };

})(jQuery);