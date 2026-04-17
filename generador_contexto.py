#!/usr/bin/env python
# coding: utf-8

import os
import shutil

def generar_contexto_arquitectura_avanzada(directorio_raiz):
    extensiones_validas = ['.php', '.css', '.js', '.json', '.txt', '.html', '.csv']
    carpetas_ignoradas = ['vendor', '.git', 'assets/img', 'contexto_notebooklm']
    
    dir_salida = os.path.join(directorio_raiz, 'contexto_notebooklm')
    if os.path.exists(dir_salida):
        shutil.rmtree(dir_salida)
    os.makedirs(dir_salida)

    Version = 80 # ERP V8

    # =================================================================
    # 🏛️ 1. ARCHIVOS CORE COMPARTIDOS (Base Context)
    # Estos archivos se inyectarán al inicio de TODOS los módulos
    # =================================================================
    core_keywords = [
        "suite-empleados.php", 
        "class-activator.php", 
        "class-suite-model-base.php", 
        "class-suite-ajax-controller.php", # Añadido por recomendación de arquitectura
        "api.js", 
        "state.js", 
        "suite-styles.css"
    ]

    # =================================================================
    # 🧩 2. MÓDULOS LÓGICOS (Vertical Slices)
    # Mapeo basado en el análisis de dependencias de NotebookLM
    # =================================================================
    vertical_slices = {
        "Slice_A_CRM": ["tab-clientes", "modal-cliente", "crm.js", "class-suite-ajax-client", "model-client"],
        "Slice_B_Cotizador_Historial": ["tab-cotizador", "tab-historial", "layout-cotizacion", "quoter.js", "historial.js", "class-suite-ajax-quotes"],
        "Slice_C_Kanban_Pagos": ["tab-kanban", "kanban.js", "class-suite-ajax-quotes", "telegram", "webhook", "pago"],
        "Slice_D_Logistica": ["tab-logistica", "logistics.js", "class-suite-ajax-logistics"],
        "Slice_E_Comisiones_Ledger": ["tab-comisiones", "commissions.js", "commission", "rendimiento", "gamificacion"],
        "Slice_F_Marketing_BI": ["tab-marketing", "marketing.js", "class-api-stats"],
        "Slice_G_Inventario": ["tab-inventario", "inventory.js", "class-suite-ajax-products", "matriz_unificada"],
        "Slice_H_Empleados_RBAC": ["tab-equipo", "employees.js", "class-suite-ajax-roles", "rol"]
    }

    print("⏳ Escaneando archivos y extrayendo memoria...")

    # Paso 1: Leer todos los archivos válidos y guardarlos en memoria
    archivos_en_memoria = {}
    for root, dirs, files in os.walk(directorio_raiz):
        dirs[:] = [d for d in dirs if d not in carpetas_ignoradas]
        for file in files:
            ext = os.path.splitext(file)[1].lower()
            ruta_completa = os.path.join(root, file)
            ruta_relativa = os.path.relpath(ruta_completa, directorio_raiz).replace('\\', '/')
            nombre_lower = file.lower()
            ruta_lower = ruta_relativa.lower()

            if nombre_lower == "index.php" or "generador_contexto" in nombre_lower: continue
            if ext not in extensiones_validas: continue

            try:
                with open(ruta_completa, 'r', encoding='utf-8') as f_in:
                    if ext == '.csv':
                        lineas = f_in.readlines()
                        texto_archivo = lineas[0] + lineas[1] + "\n// [NOTA: CSV truncado para el LLM]\n" if len(lineas) >= 2 else "".join(lineas)
                    else:
                        texto_archivo = f_in.read()
            except Exception as e:
                texto_archivo = f"// [Error al leer: {str(e)}]\n"

            if not texto_archivo.strip(): texto_archivo = "// [NOTA: Archivo vacío]\n"

            bloque = f"--- INICIO DEL ARCHIVO: {ruta_relativa} ---\n{texto_archivo}\n--- FIN DEL ARCHIVO: {file} ---\n\n"
            archivos_en_memoria[ruta_lower] = bloque

    # Paso 2: Separar el CORE del resto
    core_context = "=================================================================\n"
    core_context += "🏛️ ARCHIVOS CORE (COMPARTIDOS)\n"
    core_context += "Clases base, singletons, API handlers y estilos globales.\n"
    core_context += "=================================================================\n\n"
    
    archivos_usados = set()

    for ruta, bloque in archivos_en_memoria.items():
        if any(keyword in ruta for keyword in core_keywords):
            core_context += bloque
            archivos_usados.add(ruta)

    # Paso 3: Construir cada Slice (Core + Específicos)
    print("🧩 Ensamblando Vertical Slices...")
    for slice_name, keywords in vertical_slices.items():
        slice_content = ""
        archivos_en_este_slice = 0

        for ruta, bloque in archivos_en_memoria.items():
            # Si el archivo NO es core y coincide con las palabras clave del slice
            if ruta not in archivos_usados and any(kw in ruta for kw in keywords):
                slice_content += bloque
                archivos_en_este_slice += 1

        if archivos_en_este_slice > 0:
            # Guardar el archivo final
            ruta_txt = os.path.join(dir_salida, f"{slice_name}_v{Version}.txt")
            with open(ruta_txt, 'w', encoding='utf-8') as f_out:
                f_out.write(f"=================================================================\n")
                f_out.write(f"🧩 MÓDULO LÓGICO: {slice_name}\n")
                f_out.write("Contiene el Stack completo: Base Core + Vistas + JS + AJAX + Models\n")
                f_out.write(f"=================================================================\n\n")
                f_out.write(core_context) # <--- INYECCIÓN DEL CORE EN CADA SLICE
                f_out.write("=================================================================\n")
                f_out.write(f"📂 ARCHIVOS ESPECÍFICOS DEL MÓDULO\n")
                f_out.write("=================================================================\n\n")
                f_out.write(slice_content)

    print(f"✅ ¡Éxito! Arquitectura empaquetada. Revisa la carpeta '/contexto_notebooklm/'.")
    print(f"🎯 Siguiente paso: Sube 'Slice_E_Comisiones_Ledger_v80.txt' al LLM para afinar la tabla de registros y gamificación.")

if __name__ == "__main__":
    generar_contexto_arquitectura_avanzada(".")