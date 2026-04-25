#!/usr/bin/env python
# coding: utf-8

import os
import shutil

def generar_contexto_arquitectura_avanzada(directorio_raiz):
    extensiones_validas = ['.php', '.css', '.js', '.json', '.html', '.csv', '.txt']
    carpetas_ignoradas = ['vendor', '.git', 'assets/img', 'contexto_notebooklm']
    
    dir_salida = os.path.join(directorio_raiz, 'contexto_notebooklm')
    if os.path.exists(dir_salida):
        shutil.rmtree(dir_salida)
    os.makedirs(dir_salida)

    Version = 90 # ERP V8

    # =================================================================
    # 🏛️ 1. ARCHIVOS CORE COMPARTIDOS (Se generará como un Slice Maestro)
    # =================================================================
    core_keywords = [
        "suite-empleados.php", 
        "class-activator.php", 
        "class-suite-model-base.php", 
        "class-suite-ajax-controller.php", 
        "api.js", 
        "state.js", 
        "suite-styles.css"
    ]

    # =================================================================
    # 🧩 2. MÓDULOS LÓGICOS (Ajuste de palabras clave precisas)
    # =================================================================
    vertical_slices = {
        "Slice_A_CRM": ["tab-clientes", "modal-cliente", "crm.js", "class-suite-ajax-client", "model-client"],
        "Slice_B_Cotizador_Historial": ["tab-cotizador", "tab-historial", "layout-cotizacion", "quoter.js", "historial.js", "class-suite-ajax-quotes"],
        "Slice_C_Kanban_Pagos": ["tab-kanban", "kanban.js", "class-suite-ajax-quotes", "telegram", "webhook", "pago"],
        "Slice_D_Logistica": ["tab-logistica", "logistics.js", "class-suite-ajax-logistics"],
        "Slice_E_Comisiones_Ledger": ["tab-comisiones", "commissions.js", "commission", "rendimiento", "gamificacion"],
        # Ajuste de nombre para marketing
        "Slice_F_Marketing_BI": ["tab-marketing", "marketing.js", "class-suite-api-stats"], 
        # Ajuste de nombre para inventario
        "Slice_G_Inventario": ["tab-inventario", "inventory.js", "class-suite-ajax-inventory", "matriz_unificada"],
        "Slice_H_Empleados_RBAC": ["tab-equipo", "employees.js", "class-suite-ajax-roles", "model-roles"]
    }

    print("⏳ Escaneando archivos y extrayendo memoria...")

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
                # El errors='replace' evita que caracteres no válidos rompan el texto
                with open(ruta_completa, 'r', encoding='utf-8', errors='replace') as f_in:
                    if ext == '.csv':
                        lineas = f_in.readlines()
                        texto_archivo = lineas[0] + lineas[1] + "\n// [NOTA: CSV truncado]\n" if len(lineas) >= 2 else "".join(lineas)
                    else:
                        texto_archivo = f_in.read()
            except Exception as e:
                texto_archivo = f"// [Error al leer: {str(e)}]\n"

            if not texto_archivo.strip(): texto_archivo = "// [NOTA: Archivo vacío]\n"

            # Formateo en MARKDOWN (Amigable para LLMs)
            lenguaje = ext.replace('.', '')
            bloque = f"### ARCHIVO: `{ruta_relativa}`\n```{lenguaje}\n{texto_archivo}\n```\n\n"
            archivos_en_memoria[ruta_lower] = bloque

    archivos_usados = set()

    # --- PASO A: EXPORTAR EL CORE COMO SU PROPIO SLICE ---
    core_content = "# 🏛️ MÓDULO MAESTRO: Core Architecture\n\n"
    for ruta, bloque in archivos_en_memoria.items():
        if any(keyword in ruta for keyword in core_keywords):
            core_content += bloque
            archivos_usados.add(ruta)

    ruta_core = os.path.join(dir_salida, f"00_Slice_Core_Base_v{Version}.md")
    with open(ruta_core, 'w', encoding='utf-8') as f_out:
        f_out.write(core_content)

    # --- PASO B: EXPORTAR LOS SLICES ESPECÍFICOS ---
    print("🧩 Ensamblando Vertical Slices...")
    for slice_name, keywords in vertical_slices.items():
        slice_content = f"# 🧩 MÓDULO LÓGICO: {slice_name}\n\n"
        archivos_en_este_slice = 0

        for ruta, bloque in archivos_en_memoria.items():
            if ruta not in archivos_usados and any(kw in ruta for kw in keywords):
                slice_content += bloque
                archivos_en_este_slice += 1

        if archivos_en_este_slice > 0:
            # Ahora guardamos como .md en lugar de .txt
            ruta_md = os.path.join(dir_salida, f"{slice_name}_v{Version}.md")
            with open(ruta_md, 'w', encoding='utf-8') as f_out:
                f_out.write(slice_content)

    print(f"✅ ¡Éxito! Arquitectura empaquetada. Revisa la carpeta '/contexto_notebooklm/'.")
    print(f"💡 Sube los archivos .md generados a NotebookLM. Las IAs los leerán perfectamente.")

if __name__ == "__main__":
    generar_contexto_arquitectura_avanzada(".")