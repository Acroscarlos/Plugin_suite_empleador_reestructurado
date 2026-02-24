/**
 * SuiteState - Manejador de Estado Inmutable (Store)
 * 
 * Centraliza los datos financieros y el carrito de compras.
 * Previene la manipulación directa desde el objeto global window.
 */
const SuiteState = (function() {
    'use strict';

    // ==========================================
    // VARIABLES PRIVADAS (El "Estado")
    // ==========================================
    let cart = [];
    let totalUSD = 0.00;
    let totalBS = 0.00;
    let tasaBCV = 1.00; // Se actualizará al inicializar la app

    // ==========================================
    // MÉTODOS PRIVADOS
    // ==========================================
    
    /**
     * Recalcula los totales matemáticos cada vez que el carrito cambia.
     * Mantiene la lógica financiera blindada.
     */
    const calculateTotals = function() {
        totalUSD = cart.reduce((sum, item) => {
            let qty = parseInt(item.qty) || 0;
            let price = parseFloat(item.price) || 0;
            return sum + (qty * price);
        }, 0);

        totalBS = totalUSD * tasaBCV;
    };

    // ==========================================
    // API PÚBLICA (Métodos Revelados)
    // ==========================================
    return {
        /**
         * Retorna una COPIA INMUTABLE del carrito.
         * Si alguien muta este array externamente, no afectará al original.
         * @returns {Array}
         */
        getCart: function() {
            return [...cart]; 
        },

        /**
         * Añade un producto al carrito y recalcula.
         * Si el SKU ya existe, suma la cantidad en lugar de duplicar la fila.
         * @param {Object} item 
         */
        addItem: function(item) {
            // Normalizar datos: Prohibir cantidades negativas o cero
            item.qty = Math.max(1, parseInt(item.qty) || 1);
            item.price = Math.max(0, parseFloat(item.price) || 0.00);

            const existingIndex = cart.findIndex(i => i.sku === item.sku);
            if (existingIndex > -1) {
                cart[existingIndex].qty += item.qty;
            } else {
                cart.push(item);
            }
            calculateTotals();
        },

        /**
         * Actualiza un campo específico de una fila (ej. cantidad o precio editado).
         * @param {number} index - Índice en el array
         * @param {string} field - 'qty' o 'price'
         * @param {number|string} value - Nuevo valor
         */
        updateItem: function(index, field, value) {
            if (cart[index]) {
                if (field === 'qty') {
                    cart[index][field] = Math.max(1, parseInt(value) || 1);
                } else {
                    cart[index][field] = Math.max(0, parseFloat(value) || 0);
                }
                calculateTotals();
            }
        },

        /**
         * Elimina un producto del carrito.
         * @param {number} index 
         */
        removeItem: function(index) {
            if (cart[index]) {
                cart.splice(index, 1);
                calculateTotals();
            }
        },

        /**
         * Vacía el carrito por completo (útil al guardar con éxito).
         */
        clearCart: function() {
            cart = [];
            calculateTotals();
        },

        /**
         * Actualiza la tasa BCV del día.
         * @param {number} tasa 
         */
        setTasa: function(tasa) {
            tasaBCV = parseFloat(tasa) || 1;
            calculateTotals();
        },

        /**
         * Devuelve un snapshot de los totales financieros formateados.
         * @returns {Object}
         */
        getTotals: function() {
            return {
                usd: totalUSD.toFixed(2),
                bs: totalBS.toFixed(2),
                tasa: tasaBCV.toFixed(2)
            };
        }
    };
})();
