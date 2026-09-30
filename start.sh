#!/bin/bash
# Cachear configuración, rutas y vistas para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Iniciar Apache en primer plano
apache2-foreground