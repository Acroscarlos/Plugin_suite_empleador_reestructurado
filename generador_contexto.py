#!/usr/bin/env python
# coding: utf-8

# In[ ]:


import os
import shutil

def consolidar_plugin_por_carpetas(directorio_raiz):
    # Extensiones que queremos leer (Agregamos .csv para la nueva regla)
    extensiones_validas = ['.php', '.css', '.js', '.json', '.txt', '.html', '.csv']
    
    # Excluimos carpetas pesadas o que no aportan
    carpetas_ignoradas = ['vendor', '.git', 'assets/img', 'contexto_notebooklm']

    # Carpeta donde guardaremos todos los archivos .txt generados
    dir_salida = os.path.join(directorio_raiz, 'contexto_notebooklm')
    
    # Si la carpeta de salida ya existe, la limpiamos para no acumular basura de corridas anteriores
    if os.path.exists(dir_salida):
        shutil.rmtree(dir_salida)
    os.makedirs(dir_salida)

    contador_archivos = 1

    # Recorremos el árbol de directorios
    for root, dirs, files in os.walk(directorio_raiz):
        # Modificamos la lista 'dirs' in-place para saltarnos las ignoradas
        dirs[:] = [d for d in dirs if d not in carpetas_ignoradas]

        # Calculamos la ruta relativa de la carpeta actual respecto a la raíz
        ruta_relativa_carpeta = os.path.relpath(root, directorio_raiz)
        
        # Filtramos los archivos válidos DENTRO de esta carpeta específica (Aislamiento)
        archivos_a_procesar = []
        for file in files:
            ext = os.path.splitext(file)[1].lower()
            
            # REGLAS DE EXCLUSIÓN
            if file.lower() == "index.php":
                continue # Ignorar protección de directorios
            if file == "GENERADOR_CONTEXTO.ipynb" or file == "GENERADOR_CONTEXTO.py":
                continue # Ignorar este mismo script
            if ext not in extensiones_validas:
                continue # Ignorar extensiones no mapeadas
                
            archivos_a_procesar.append(file)

        # Si no hay archivos válidos en esta carpeta, pasamos a la siguiente sin crear un .txt vacío
        if not archivos_a_procesar:
            continue

        # Nomenclatura del archivo de salida (Ej: 1_includes_Controllers_Ajax.txt)
        if ruta_relativa_carpeta == ".":
            nombre_base = "raiz_del_plugin"
        else:
            # Reemplazamos slashes y backslashes por guiones bajos
            nombre_base = ruta_relativa_carpeta.replace('\\', '_').replace('/', '_')
        
        nombre_archivo_txt = f"{contador_archivos}_{nombre_base}.txt"
        ruta_txt_salida = os.path.join(dir_salida, nombre_archivo_txt)

        # Creamos el archivo txt para esta carpeta
        with open(ruta_txt_salida, 'w', encoding='utf-8') as f_out:
            f_out.write("=================================================================\n")
            f_out.write(f"CONTEXTO DE CARPETA: {ruta_relativa_carpeta}\n")
            f_out.write("Proyecto: ERP Vendedores (MVC y POO)\n")
            f_out.write("=================================================================\n\n")

            for file in archivos_a_procesar:
                ruta_completa = os.path.join(root, file)
                ext = os.path.splitext(file)[1].lower()
                
                f_out.write(f"--- INICIO DEL ARCHIVO: {file} ---\n")
                
                try:
                    with open(ruta_completa, 'r', encoding='utf-8') as f_in:
                        # REGLA ESPECIAL PARA CSV (Solo cabecera y 1ra fila)
                        if ext == '.csv':
                            lineas = f_in.readlines()
                            if len(lineas) >= 2:
                                contenido = lineas[0] + lineas[1]
                                f_out.write("// [NOTA PARA IA: Archivo CSV truncado. Solo se muestra cabecera y fila 1 de ejemplo]\n")
                            else:
                                contenido = "".join(lineas)
                        else:
                            # Leer archivo normal
                            contenido = f_in.read()
                        
                        # Si está vacío
                        if not contenido.strip():
                            f_out.write("// [NOTA PARA LA IA: Este archivo existe pero actualmente está vacío]\n")
                        else:
                            f_out.write(contenido)
                            if not contenido.endswith('\n'):
                                f_out.write('\n')
                except Exception as e:
                    f_out.write(f"// [Error al leer el archivo con UTF-8: {str(e)}]\n")
                
                f_out.write(f"--- FIN DEL ARCHIVO: {file} ---\n\n")
        
        # Incrementamos el contador para la siguiente carpeta
        contador_archivos += 1

if __name__ == "__main__":
    carpeta_plugin = "." # El punto indica que lea la carpeta actual
    
    print(f"⏳ Escaneando carpetas y aislando archivos...")
    consolidar_plugin_por_carpetas(carpeta_plugin)
    print(f"✅ ¡Éxito! Se han generado los archivos fragmentados.")
    print(f"📁 Búscalos dentro de la nueva carpeta: /contexto_notebooklm/")
    print("Sube esos archivos a NotebookLM para tener un contexto súper preciso.")


# In[ ]:




