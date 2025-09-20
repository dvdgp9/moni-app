# Moni

Gestión de finanzas para autónomos en España.

## Requisitos
- PHP 8.3
- Composer
- MySQL
- cPanel (producción)

## Instalación
1. `composer install`
2. Copia `.env.example` a `.env` y configura credenciales de BD y SMTP.
3. Configura tu host local para servir `public/` como document root.
4. Crea la base de datos y ejecuta las migraciones en `database/migrations/` (p.ej. importar `001_init.sql`).

## Desarrollo
- Entrypoint: `public/index.php`
- Rutas: `/?page=dashboard`, `/?page=settings`
- CSS: `assets/css/styles.css`

## Cron en cPanel
Programa una tarea diaria (08:00 Europe/Madrid):
```
php -q /home/USER/moni-app/scripts/run_reminders.php
```

## Próximos pasos
- Implementar guardado de Ajustes en BD.
- Motor de recordatorios con `reminder_logs` e emails reales.
- Módulo de Clientes, Facturas y PDF.
