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

## Asistente de declaraciones

`/declaraciones` permite preparar los modelos 130 y 303: revisión de registros,
ajustes del período, guía por casillas con copia de importes en formato español,
exportación de la guía a CSV y registro de declaraciones ya presentadas.

- El 130 calcula hasta la casilla 19. La casilla 05 y los negativos pendientes se
  obtienen de las declaraciones presentadas del mismo ejercicio, no de estimaciones
  de facturas. El rendimiento neto del ejercicio anterior se recuerda por año.
- El 303 usa las casillas verificadas del formulario de 2026 para el régimen general,
  con devengo y tributación al 100 % en territorio común. Las operaciones especiales
  requieren clasificación y ajustes explícitos. Los demás ejercicios se pueden
  consultar como borrador, pero no marcar como revisados/presentados con esta guía.
- Un saldo anterior desconocido queda pendiente hasta registrar el historial,
  introducirlo o confirmar que no existe. La compensación del 303 cruza ejercicios
  y distingue el saldo anterior (87) del nuevo importe a compensar (72).
- Borradores, revisiones y presentaciones se guardan mediante `SettingsRepository`,
  bajo el usuario autenticado. No hace falta migración. Las presentaciones conservan
  sus importes aunque cambien los registros; la última presentación por fecha del
  período es la que se utiliza para los cálculos siguientes.
- El CSV es una guía de casillas; no es un fichero de presentación ni de importación
  de libros registro. La firma, presentación y pago se realizan en la AEAT. Los modelos
  111 y 115 siguen como recordatorios, y el 390 mantiene su resumen anual.

Comprobación de cálculos y validación (sin base de datos):

```bash
php tests/tax_declarations.php
```

Las reglas se contrastaron con las
[instrucciones del 130](https://sede.agenciatributaria.gob.es/Sede/impuestos-tasas/impuesto-sobre-renta-personas-fisicas/modelo-130-irpf______esionales-estimacion-directa-fraccionado_/instrucciones.html)
y las
[instrucciones del 303 de 2026](https://sede.agenciatributaria.gob.es/Sede/todas-gestiones/impuestos-tasas/iva/modelo-303-iva-autoliquidacion_/instrucciones-2026/instrucciones-02-12-2t-4t-2026.html).
