##### Guía de Despliegue Comercial: Sistema de Restaurantes
### Esta es la documentación oficial estructurada para estandarizar las futuras instalaciones comerciales de tu punto de venta. El objetivo es replicar el despliegue rápidamente para cada nuevo restaurante, separando lo que ya automatizaste en el código de lo que requiere configuración manual.   

1. Configuraciones Automatizadas (Código Fuente)Estas características ya forman parte del ADN del proyecto. Al clonar tu repositorio para iniciar el proyecto de un nuevo cliente, se aplicarán en automático en la nube sin que tengas que intervenir:
## Seguridad y Accesos: CORS (config/cors.php) preparado para inyectar dinámicamente el dominio desde el servidor.
## Capacidad de Carga: Límite de subida de imágenes ampliado a 10MB (uploads.ini) inyectado al contenedor.
## Rendimiento del Backend (Render): Memoria OPcache activada (opcache.ini) y compresión JSON (mod_deflate en Apache) inyectados a través del Dockerfile.
## Arranque de Ultra Baja Latencia: Script start.sh que empaqueta y cachea la configuración, vistas y rutas de Laravel (config:cache, route:cache, view:cache) cada vez que el contenedor enciende.
## Rendimiento del Frontend (Vercel): Caché de navegador estricto y compresión de estáticos (Gzip/Brotli) aplicados nativamente al código de React.

2. Proceso de Configuración Manual (Por Cliente)Estas son las únicas acciones manuales requeridas en los paneles de control cada vez que lances el sistema para un nuevo establecimiento.
### Fase A: Base de Datos (Supabase)
## Crear un nuevo proyecto en Supabase exclusivo para el cliente.
## Extraer las credenciales de conexión (Host, Database, Port 6543 para Supavisor, User, Password).
## (Opcional) Si no se integró en las migraciones de Laravel, ejecutar en el SQL Editor: CREATE EXTENSION IF NOT EXISTS pg_stat_statements;.## Si se importa una base de datos base con datos pre-cargados, ejecutar el script SQL para corregir las rutas de las imágenes locales:SQLUPDATE configuracion_general 

# SET logo_url = REPLACE(logo_url, 'http://127.0.0.1:8000', 'https://api-NUEVO-CLIENTE.onrender.com');

# Fase B: Servidor Backend (Render)Crear un nuevo Web Service desde el repositorio de Laravel, seleccionando el entorno Docker.
## Ir a la pestaña Environment y configurar las siguientes variables obligatorias (nunca subir el archivo .env local):Variable ClaveValor Esperado
### APP_UR 
### LURL de este backend en Render (ej. [https://api-cliente.onrender.com](https://api-cliente.onrender.com))
#### FRONTEND_URL 
#### URL pública del cliente en Vercel (ej. [https://app-cliente.vercel.app](https://app-cliente.vercel.app))
##### SANCTUM_STATEFUL_DOMAINS
##### Dominio exacto de Vercel (sin el https://)
###### SESSION_DOMAIN
###### Dominio exacto de Vercel (sin el https://)
## DB_HOST... DB_PASSWORD
## Las credenciales extraídas de Supabase en la Fase A
### Fase C: Interfaz Frontend (Vercel)
# Crear un nuevo proyecto en Vercel desde el repositorio de React.
# Antes de darle clic en "Deploy", abrir la sección Environment Variables y apuntar el sistema hacia el backend de Render:VITE_API_URL = [https://api-cliente.onrender.com](https://api-cliente.onrender.com)
# Una vez desplegado, ir a Settings > Domains y conectar el dominio web personalizado que el cliente haya comprado para su restaurante.
