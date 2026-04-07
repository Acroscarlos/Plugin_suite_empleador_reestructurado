/**
 * SuiteLogistics - Módulo del Panel de Almacén y Despacho
 * * Gestiona la apertura de modales de logística y la subida segura
 * de comprobantes (POD), Facturas y Recibos Loyverse mediante FormData.
 */
const SuiteLogistics = (function($) {
    'use strict';

    // ==========================================
    // MÉTODOS PRIVADOS Y EVENT LISTENERS
    // ==========================================

    const bindEvents = function() {
        
        // 1. ABRIR MODAL Y CONFIGURAR UX FISCAL
        // Usamos delegación de eventos (.on) porque la tabla se genera dinámicamente
        $(document).on('click', '.trigger-dispatch', function(e) {
            e.preventDefault();
            
            // A. Limpiar formulario nativamente
            $('#form-confirm-dispatch')[0].reset();
            
            // B. Extraer metadata de los atributos data-* del HTML
            const quoteId = $(this).data('id');
            const quoteCode = $(this).data('code');
            const isFiscal = $(this).data('fiscal');
            
            // C. Inyectar ID en el hidden y código en la cabecera
            $('#disp-quote-id').val(quoteId);
            $('#dispatch-info-box').html(`<strong>Despachando Orden:</strong> #${quoteCode}`);
            
            // D. Lógica UX: Requisito Fiscal
            const labelFactura = $('#label-factura-fiscal');
            const boxFactura = $('#box-factura-fiscal');
            
            if (isFiscal == 1 || isFiscal === true || isFiscal === '1') {
                boxFactura.css({'border-color': '#dc2626', 'background': '#fef2f2'});
                labelFactura.css('color', '#dc2626').text('📸 Subir Factura Fiscal (¡REQUERIDA!)');
                $('#disp-factura-file').prop('required', true); 
            } else {
                boxFactura.css({'border-color': '#e2e8f0', 'background': '#ffffff'});
                labelFactura.css('color', '#475569').text('📸 Adjuntar Factura Fiscal Física (Opcional)');
                $('#disp-factura-file').prop('required', false);
            }
            
            // E. Mostrar Modal
            $('#modal-confirm-dispatch').fadeIn();
        });

        // 2. CERRAR MODAL
        $('#close-modal-dispatch, #btn-cancel-dispatch').on('click', function(e) {
            e.preventDefault();
            $('#modal-confirm-dispatch').fadeOut();
        });

        // 3. REFRESCAR TABLA MANUALMENTE
        $('#btn-refresh-logistics').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });

        // 4. PROCESAR SUBIDA (Integrando tu SuiteAPI y el UX de desvanecimiento)
        $('#form-confirm-dispatch').on('submit', function(e) {
            e.preventDefault();

            // Bloquear botón para evitar múltiples envíos
            const btnSubmit = $(this).find('button[type="submit"]');
            const originalText = btnSubmit.html();
            btnSubmit.prop('disabled', true).text('⏳ Cifrando y Subiendo...');

            const quoteId = $('#disp-quote-id').val();
            
            // Instanciar FormData para envío de archivos (Multipart)
            const fd = new FormData();
            
            // Agregamos los campos de texto
            fd.append('quote_id', quoteId);
            fd.append('recibo_loyverse', $('#disp-loyverse').val());

            // Agregamos Archivos solo si fueron seleccionados
            const facturaInput = $('#disp-factura-file')[0].files;
            if (facturaInput && facturaInput.length > 0) {
                fd.append('factura_file', facturaInput[0]);
            }

            const podInput = $('#disp-pod-file')[0].files;
            if (podInput && podInput.length > 0) {
                fd.append('pod_file', podInput[0]);
            }

            // Usamos tu API unificada (El action ahora es 'suite_process_dispatch')
            SuiteAPI.postForm('suite_process_dispatch', fd).then(res => {
                if (res.success) {
                    
                    $('#modal-confirm-dispatch').fadeOut();
                    alert('✅ ' + (res.data.message || 'Pedido despachado exitosamente. Comisiones liberadas.'));
                    
                    // UX: Efecto visual de tu código original (Eliminar fila suavemente)
                    $('#log-row-' + quoteId).fadeOut('slow', function() { 
                        $(this).remove(); 
                        
                        // Sincronizar Kanban en background si la función existe
                        if (typeof SuiteKanban !== 'undefined' && typeof SuiteKanban.loadBoard === 'function') {
                            SuiteKanban.loadBoard();
                        }
                    });

                } else {
                    alert('❌ Error: ' + (res.data.message || res.data || 'Error desconocido.'));
                }
            }).catch(err => {
                console.error(err);
                alert('❌ Error de red o conexión al subir los archivos.');
            }).finally(() => {
                btnSubmit.prop('disabled', false).html(originalText);
            });
        });
    };

    // ==========================================
    // API PÚBLICA
    // ==========================================
    return {
        init: function() {
            bindEvents();
        }
    };

})(jQuery);

// Inicializar al cargar el DOM (Modo seguro de WordPress)
jQuery(document).ready(function($) {
    SuiteLogistics.init();
});