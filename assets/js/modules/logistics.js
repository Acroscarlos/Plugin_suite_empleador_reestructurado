/**
 * SuiteLogistics - Módulo del Panel de Almacén y Despacho
 * 
 * Gestiona la apertura de modales de logística y la subida segura
 * de comprobantes de entrega (POD) mediante FormData.
 */
const SuiteLogistics = (function($) {
    'use strict';

    let currentQuoteId = null;

    // ==========================================
    // MÉTODOS PRIVADOS Y EVENT LISTENERS
    // ==========================================

    const bindEvents = function() {
        
        // Procesar subida del comprobante
		$('#btn-procesar-despacho').on('click', function(e) {
            e.preventDefault();

            const fileInput = $('#log-pod-file').prop('files');
            if (!fileInput || fileInput.length === 0) {
                return alert('⚠️ Por favor, selecciona una imagen o archivo PDF como comprobante.');
            }

            const btn = $(this);
            btn.prop('disabled', true).text('Subiendo Archivo...');

            // Instanciar FormData para envío de archivos (Multipart)
            const fd = new FormData();
            fd.append('pod_file', fileInput[0]); // <--- ÚNICA CORRECCIÓN: Agregar [0] aquí
            fd.append('quote_id', currentQuoteId);

            SuiteAPI.postForm('suite_upload_pod', fd).then(res => {
                if (res.success) {
                    alert('✅ ' + (res.data.message || 'Pedido marcado como despachado.'));
                    
                    // Cerrar Modal
                    $('#modal-confirmar-despacho').fadeOut();
                    
                    // Efecto visual: Eliminar la fila de la tabla suavemente
                    $('#log-row-' + currentQuoteId).fadeOut('slow', function() { 
                        $(this).remove(); 
                        
                        // Si la tabla queda vacía, podríamos mostrar un mensaje, 
                        // pero con recargar la vista del Kanban en background es suficiente.
                        if (typeof SuiteKanban !== 'undefined') {
                            SuiteKanban.loadBoard();
                        }
                    });

                } else {
                    alert('❌ Error: ' + (res.data.message || res.data));
                }
            }).catch(err => {
                alert('❌ Error de conexión al subir el archivo.');
            }).finally(() => {
                btn.prop('disabled', false).text('Subir y Marcar Despachado');
            });
        });
    };

    // ==========================================
    // API PÚBLICA (Métodos Revelados)
    // ==========================================
    return {
        /**
         * Abre el modal y guarda el ID del pedido en memoria
         * @param {number} id - ID del pedido
         */
        openModal: function(id) {
            currentQuoteId = id;
            $('#log-pod-file').val(''); // Limpiar input residual
            $('#modal-confirmar-despacho').fadeIn();
        },

        init: function() {
            bindEvents();
        }
    };

})(jQuery);