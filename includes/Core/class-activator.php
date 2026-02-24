<?php
// includes/db-install.php V7

// SEGURIDAD: Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

/**
 * Función de Instalación de Base de Datos
 * Crea las 4 tablas críticas: Inventario, Clientes, Cotizaciones, Items.
 */
if ( ! function_exists( 'suite_install_db' ) ) {
    function suite_install_db() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. Tabla Inventario (Cache)
        $sql_inventario = "CREATE TABLE {$wpdb->prefix}suite_inventario_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sku VARCHAR(100) NOT NULL,
            nombre_producto VARCHAR(255),
            stock_gale INT DEFAULT 0,
            stock_mille INT DEFAULT 0,
            stock_transito INT DEFAULT 0,
            status_alerta VARCHAR(50),
            velocidad_ventas FLOAT DEFAULT 0,
            precio DECIMAL(10,2) DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_sku (sku)
        ) $charset_collate;";

        // 2. Tabla Clientes (CRM)
        $sql_clientes = "CREATE TABLE {$wpdb->prefix}suite_clientes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            nombre_razon VARCHAR(255) NOT NULL,
            rif_ci VARCHAR(50) NOT NULL,
            direccion TEXT,
            ciudad VARCHAR(100),
            estado VARCHAR(100),
            telefono VARCHAR(50),
            email VARCHAR(100),
            contacto_persona VARCHAR(150),
			notas TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_rif (rif_ci)
        ) $charset_collate;";

        // MODIFICACIÓN: Tabla Cotizaciones (Se añade pod_url al final)
        $sql_cotizaciones = "CREATE TABLE {$wpdb->prefix}suite_cotizaciones (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            codigo_cotizacion VARCHAR(20) NOT NULL,
            cliente_nombre VARCHAR(255),
            cliente_rif VARCHAR(50),
            cliente_id bigint(20) NOT NULL DEFAULT 0,
            direccion_entrega TEXT,
            fecha_emision DATETIME DEFAULT CURRENT_TIMESTAMP,
            validez_dias INT DEFAULT 10,
            moneda VARCHAR(5) DEFAULT 'USD',
            vendedor_id bigint(20),
            total_bs DECIMAL(15,2),
            total_usd DECIMAL(15,2),
            tasa_bcv DECIMAL(10,2),
            estado VARCHAR(20) DEFAULT 'emitida',
            canal_venta VARCHAR(100),
            metodo_pago VARCHAR(100),
            metodo_entrega VARCHAR(100),
            url_captura_pago TEXT,
            recibo_loyverse VARCHAR(100),
            pod_url TEXT, -- NUEVO CAMPO: Proof of Delivery (Fase 7)
            PRIMARY KEY (id)
        ) $charset_collate;";

        // 2. NUEVA TABLA: Libro Mayor de Comisiones (Ledger)
        $sql_comisiones = "CREATE TABLE {$wpdb->prefix}suite_comisiones_ledger (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quote_id bigint(20) NOT NULL,
            vendedor_id bigint(20) NOT NULL,
            monto_base_usd DECIMAL(15,2) DEFAULT 0,
            comision_ganada_usd DECIMAL(15,2) DEFAULT 0,
            estado_pago VARCHAR(20) DEFAULT 'pendiente',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vendedor (vendedor_id)
        ) $charset_collate;";

        // 3. NUEVA TABLA: Gamificación y Premios
        $sql_premios = "CREATE TABLE {$wpdb->prefix}suite_premios_mensuales (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            vendedor_id bigint(20) NOT NULL,
            mes INT NOT NULL,
            anio INT NOT NULL,
            premio_nombre VARCHAR(100) NOT NULL,
            monto_premio DECIMAL(10,2) DEFAULT 0,
            asignado_manualmente TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_mes_anio (mes, anio)
        ) $charset_collate;";

        // 4. Tabla Items (Detalle)
        $sql_items = "CREATE TABLE {$wpdb->prefix}suite_cotizaciones_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cotizacion_id bigint(20) NOT NULL,
            sku VARCHAR(100),
            producto_nombre VARCHAR(255),
            cantidad INT,
            precio_unitario_usd DECIMAL(15,2),
            tiempo_entrega VARCHAR(100),
            subtotal_usd DECIMAL(15,2),
            PRIMARY KEY (id),
            KEY idx_cotizacion (cotizacion_id)
        ) $charset_collate;";
		
		// 5. Tabla de Auditoría (Logs)
		$sql_logs = "CREATE TABLE {$wpdb->prefix}suite_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			usuario_id bigint(20) NOT NULL,
			accion VARCHAR(50) NOT NULL,
			detalle TEXT,
			ip VARCHAR(45),
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_usuario (usuario_id)
		) $charset_collate;";


        // NUEVA TABLA: Data Lake / Inventario Histórico (Módulo 5 - IA)
        $sql_historico = "CREATE TABLE {$wpdb->prefix}suite_inventario_historico (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            fecha_snapshot DATE NOT NULL,
            sku VARCHAR(100) NOT NULL,
            stock_disponible INT DEFAULT 0,
            precio DECIMAL(10,2) DEFAULT 0,
            categoria VARCHAR(100) DEFAULT 'General',
            PRIMARY KEY  (id),
            KEY idx_fecha (fecha_snapshot),
            KEY idx_sku (sku)
        ) $charset_collate;";

		dbDelta($sql_logs);

		// Crear Roles Personalizados (Ejecutar una vez)
		add_role('suite_vendedor', 'Vendedor Suite', array('read' => true, 'suite_access' => true));
		add_role('suite_logistica', 'Logística Suite', array('read' => true, 'suite_access' => true));
		$admin = get_role('administrator');
		$admin->add_cap('suite_access'); // Asegurar que admin tenga acceso
		
		

        // Ejecutar dbDelta
        dbDelta( $sql_inventario );
        dbDelta( $sql_clientes );
        dbDelta( $sql_cotizaciones );
        dbDelta( $sql_items );

        // Ajuste de Auto-Increment para Cotizaciones (Inicio en 15000)
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}suite_cotizaciones" );
        if ( $count == 0 ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}suite_cotizaciones AUTO_INCREMENT = 15000" );
        }

        // Ejecutar dbDelta para aplicar cambios sin destruir data
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_cotizaciones );
        dbDelta( $sql_comisiones );
        dbDelta( $sql_premios );
        
        // Recuerda que dbDelta actualizará la tabla sin borrar datos
        dbDelta( $sql_cotizaciones );


    }
}
