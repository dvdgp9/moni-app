# Moni - Scratchpad

## Background and Motivation
Moni es una web-app para la gestión de finanzas de autónomos en España. Objetivos clave:
- Creación y gestión de clientes.
- Creación de facturas con IVA/IRPF y numeración configurable; exportación en PDF con branding.
- Asistencia en declaraciones trimestrales: cálculo de bases, IVA, IRPF para el trimestre actual/seleccionado.
- Notificaciones por email cuando abra el plazo de declaración trimestral (1/4, 1/7, 1/10, 1/1) y otras fechas configurables.
- UI moderna (colores azules), dashboard con resumen y próximos eventos. Enfoque desktop-first, usable en móvil.

Stack y despliegue:
- Backend: PHP 8.3, MySQL.
- Frontend: HTML, CSS, JS (sin build tooling complejo inicialmente).
- Dependencias: PHPMailer (email), Dompdf (PDF). Composer para gestión de paquetes.
- Hosting: cPanel en dominio `moni.wthefox.com`.
- Control de versiones: GitHub (repo aún por crear; nombre TBD por el usuario).

Valores por defecto (editables):
- IVA por defecto: 21%.
- IRPF por defecto: 15%.
- Numeración de factura: `YYYY-NNNN` (reinicio anual). 
- SMTP host: `mail.moni.wthefox.com` (credenciales vía .env).
- Branding multi-tenant previsto: logo/colores configurables por usuario/tenant en el futuro; inicialmente, configuración en ajustes.

## Key Challenges and Analysis
- Simplificar arquitectura para cPanel: evitar builds complejos; usar Composer y PHP CLI para cron.
- Email fiable: configuración SMTP por .env, plantillas HTML, registros en BD para evitar duplicados.
- Cálculos fiscales: soportar IVA variable por línea (aunque default 21%) e IRPF por defecto 15% configurable.
- Numeración de facturas: única por año, atómica (evitar colisiones concurrentes): bloquear por transacción o asignación secuencial con tabla contadores.
- PDF consistente: Dompdf con plantilla usando paleta azul; soportar logotipos/colores configurables posteriormente.
- Multi-tenant futuro: diseñar tablas `users`/`organizations`/`settings` que permitan extender a múltiples usuarios; en primera iteración, single-user con posibilidad de migrar.
- Cron y ventanas fiscales: cálculo de próximos eventos con zona horaria Europe/Madrid y logs de envío para idempotencia.
- Seguridad básica: autenticación simple (login), protección CSRF mínima y saneo de entradas.
- TDD pragmático: pruebas unitarias ligeras (servicios de cálculo, lógica de fechas) y pruebas manuales guiadas para UI.

## High-level Task Breakdown (with Success Criteria)

1) Bootstrap técnico y Dashboard + Avisos por email (Hito 1)
- Estructura PHP con Composer: `public/`, `src/`, `templates/`, `assets/`, `config/`, `scripts/`.
- Carga `.env` (dotenv) y `config.php` centralizado.
- Base de datos inicial y migración 001 (settings, reminders, reminder_logs, users [mínimo], clients [placeholder], invoices [placeholder], invoice_items [placeholder]).
- Integración PHPMailer con SMTP configurable.
- Página de Ajustes mínimos: email remitente, SMTP host/puerto/seguridad/usuario, zona horaria, activar/desactivar recordatorios; fechas adicionales.
- Lógica de recordatorios trimestrales (1 Abr, 1 Jul, 1 Oct, 1 Ene) + fechas custom; motor que decide “qué enviar hoy”.
- Script CLI `scripts/run_reminders.php` ejecutable por cron (`php -q`).
- Dashboard con tarjetas placeholder: “Próximos eventos”, “Resumen (placeholder)”, enlaces no funcionales a Clientes/Facturas/Declaraciones.
- Estilo base azul responsive (variables CSS) y layout limpio.
- Success criteria:
  - Se puede guardar configuración SMTP y realizar una prueba de envío desde Ajustes.
  - Cron manual o CLI ejecuta y registra recordatorios sin duplicar envíos el mismo día.
  - Dashboard carga sin errores y muestra próximos eventos calculados en función de hoy.

2) Clientes (CRUD)
- Modelo y migración completa `clients` (NIF, nombre/razón social, dirección, email, IVA/IRPF preferidos).
- Vistas: listar, crear, editar, borrar con validación.
- Success criteria:
  - Alta/edición/baja/listado funcionan y validan NIF/email.
  - Datos disponibles para precargar facturas.

3) Facturas (creación y gestión)
- Tablas `invoices`, `invoice_items` definitivas; servicio de numeración anual `YYYY-NNNN`.
- Formulario con líneas: descripción, cantidad, precio, IVA por línea, IRPF por línea (por defecto heredado). Totales automáticos.
- Listado, edición, estado (borrador/emitida/pagada/cancelada), fecha de factura.
- Success criteria:
  - Crear/editar facturas con totales correctos (base, IVA, IRPF, total).
  - Numeración secuencial y única por año.

4) PDF de facturas
- Integración Dompdf, plantilla cuidada con branding (logo/colores desde ajustes).
- Botón “Exportar PDF” en detalle de factura.
- Success criteria:
  - PDF generado fiel a los totales y datos del cliente/emisor.
  - Compatibilidad visual al imprimir/enviar por email.

5) Asistencia declaraciones trimestrales
- Selección de trimestre/año; cálculo de base imponible, IVA devengado, IRPF.
- Resumen y exportable (CSV/Excel simple o PDF resumen).
- Success criteria:
  - Cálculos verificables usando facturas registradas en el rango del trimestre.
  - UI muestra totales con desglose.

6) Autenticación y multi-usuario (mínimo viable)
- Login simple (usuarios en BD) y zona de ajustes por usuario.
- Success criteria:
  - Sesión segura básica, formularios protegidos, logout.

7) Despliegue y cron en cPanel
- Repo GitHub creado y push inicial.
- Configuración de entorno en cPanel (.env) y dominio apuntando a `public/` como document root.
- Cron diario a las 08:00 Europe/Madrid: `php -q /home/USER/path/scripts/run_reminders.php`.
- Success criteria:
  - App accesible en `https://moni.wthefox.com`.
  - Cron ejecuta y quedan registros en `reminder_logs`.

## Project Status Board (Markdown TODO)
- [ ] H1: Bootstrap + Dashboard + Avisos
  - [x] Estructura PHP con Composer y `.env`
  - [x] Migración inicial BD (001)
  - [x] PHPMailer + prueba de envío
  - [x] Ajustes (SMTP, TZ, preferencias) con persistencia en BD
  - [x] Lógica recordatorios trimestrales + fechas personalizadas
  - [x] Script cron `run_reminders.php` y servicio de recordatorios
  - [x] Dashboard placeholder + estilo
- [ ] H2: Clientes (CRUD)
- [ ] H3: Facturas (CRUD + cálculos + numeración)
- [ ] H4: PDF de facturas (Dompdf)
- [ ] H5: Asistencia declaraciones trimestrales
- [ ] H6: Auth básica
- [ ] H7: Despliegue y cron en cPanel
  - [ ] Crear repo GitHub y primer push (COMPLETADO 2025-09-20)
  - [ ] Configurar hosting (document root a `public/`, clonar repo, `composer install`, `.env` producción)
  - [ ] Configurar cron diario 08:00 Europe/Madrid

## Current Status / Progress Tracking
- 2025-09-20: Planificación inicial completada (Planner). Aprobado pasar a Executor.
- 2025-09-20: Executor ha creado el esqueleto del proyecto:
  - Archivos clave: `composer.json`, `public/index.php`, `src/bootstrap.php`, `src/support/Config.php`, `src/Database.php`, `src/Services/EmailService.php`, `templates/*`, `assets/css/styles.css`, `scripts/run_reminders.php`, `database/migrations/001_init.sql`, `.env.example` y `.gitignore`.
  - Pendiente ejecutar `composer install` para descargar dependencias.
  - Pendiente crear BD e importar `database/migrations/001_init.sql`.
 - 2025-09-20: Git inicializado, primer commit y push a `origin/main` (repo: `dvdgp9/moni-app`).
- 2025-09-20: Ajustes persistentes y motor de recordatorios implementados. Cron configurado en cPanel (08:00 Europe/Madrid).

## Executor's Feedback or Assistance Requests
- Confirmar nombre del repositorio GitHub (p.ej., `moni` o `moni-app`).
- Confirmar si preferimos cron via PHP CLI (recomendado) y un endpoint web protegido como alternativa manual. Elegido: PHP CLI en cPanel.
- Proveer logo y colores cuando lleguemos al H4 (PDF/branding) o añadirlos a Ajustes cuando implementemos la sección de marca.

## Lessons
- Mantener cron idempotente con `reminder_logs` evita duplicados de envío el mismo día/evento.
- Diseñar numeración de facturas desacoplada en un servicio con transacción reduce colisiones.
- Configuración por `.env` + `settings` en BD permite multi-tenant futuro sin reconfigurar despliegue.
