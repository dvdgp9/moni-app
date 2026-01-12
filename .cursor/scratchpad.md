# Scratchpad: Corrección de recordatorios duplicados (Cierre T4 / Resumen Anual)

## Background and Motivation
El usuario informa que está recibiendo recordatorios diarios del "cierre de T4" y "resumen anual" desde principios de año (12 de enero), cuando solo deberían enviarse una vez. El sistema parece estar detectando estos eventos como "activos" durante un rango de fechas y enviando el correo cada vez que se ejecuta el script de recordatorios porque la validación de duplicados solo mira el día actual.

## Key Challenges and Analysis
- **Lógica de Rango**: `ReminderService::getDueEventsForToday` identifica eventos si la fecha actual está entre `event_date` y `end_date`.
- **Idempotencia Insuficiente**: `ReminderService::runForToday` verifica `reminder_logs` usando `event_date = :todayStr`. Si un evento tiene un rango (ej: del 1 al 20 de enero), el sistema envía el correo hoy, registra que se envió "hoy", pero mañana vuelve a ver que está en rango y no encuentra un log para "mañana", enviándolo de nuevo.
- **Ciclo de Recurrencia**: Para recordatorios anuales, la validación debería comprobar si se ha enviado ya en la "ventana" actual del evento para ese año.

## High-level Task Breakdown
1. **Analizar `ReminderService.php`**: Confirmar que la consulta de logs solo filtra por el día actual. (COMPLETADO)
2. **Solicitar información de la DB**: Pedir al usuario que verifique la configuración de los recordatorios "Cierre T4" y "Resumen Anual".
3. **Corregir la lógica de validación**: Modificar `ReminderService::runForToday` para que busque si el recordatorio ya fue enviado durante el periodo de vigencia actual del evento, no solo hoy.
4. **Validar la solución**: Asegurar que los recordatorios de un solo día sigan funcionando y los de rango se detengan tras el primer envío exitoso.

## Project Status Board
- [x] Analizar código de `ReminderService.php` <!-- id: 10 -->
- [ ] Obtener datos de la DB del usuario (SQL) <!-- id: 11 -->
- [ ] Implementar corrección de idempotencia por periodo <!-- id: 12 -->
- [ ] Verificar con el usuario <!-- id: 13 -->

## Executor's Feedback or Assistance Requests
- He identificado que el problema es que la tabla `reminder_logs` se usa para evitar duplicados **en el mismo día**, pero no para evitar duplicados **dentro del mismo rango de fechas** del evento.
- Necesito que el usuario ejecute un SQL para confirmar las fechas de inicio y fin de esos recordatorios.

---

# Moni - Scratchpad

## Background and Motivation
Moni es una web-app para la gestión de finanzas de autónomos en España. Objetivos clave:
- Creación y gestión de clientes.
- Creación de facturas con IVA/IRPF y numeración configurable; exportación en PDF con branding.
- Asistencia en declaraciones trimestrales: cálculo de bases, IVA, IRPF para el trimestre actual/seleccionado.
- Notificaciones por email cuando abra el plazo de declaración trimestral (1/4, 1/7, 1/10, 1/1) y otras fechas configurables.
- UI moderna (colores azules), dashboard con resumen y próximos eventos. Enfoque desktop-first, usable en móvil.

### Nueva funcionalidad: Gastos/Facturas recibidas (Expenses)
**Necesidad:** Registrar facturas de compras/gastos que afecten a las declaraciones:
- **Modelo 303:** IVA soportado deducible (casilla 45)
- **Modelo 130:** Gastos acumulados (casilla 02)

**Requisitos:**
- Subir PDFs de facturas de proveedores
- Extraer automáticamente: proveedor, NIF, fecha, base imponible, IVA, total
- Rellenar formulario con datos extraídos (editable antes de guardar)
- Almacenar PDF original para referencia
- Integrar totales en página de declaraciones

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

### Análisis: Extracción de datos de PDFs de facturas

**Opciones evaluadas (ordenadas por coste):**

| Opción | Coste estimado | Precisión | Complejidad | Notas |
|--------|---------------|-----------|-------------|-------|
| 1. **Smalot/pdfparser (PHP)** | Gratis | Media-Alta* | Baja | Extrae texto de PDFs digitales. No funciona con escaneados. |
| 2. **Regex + patrones** | Gratis | Media | Media | Parsear texto extraído buscando patrones (NIF, fechas, totales). |
| 3. **Tesseract OCR** | Gratis | Media | Alta | Requiere instalación en servidor. Funciona con escaneados. |
| 4. **Google Cloud Vision** | ~€1.50/1000 págs | Alta | Media | OCR en la nube, muy preciso. |
| 5. **OpenAI GPT-4o-mini** | ~€0.003/factura | Muy Alta | Media | Visión + extracción estructurada. Entiende contexto. |
| 6. **Claude/GPT-4o** | ~€0.01-0.03/fact | Muy Alta | Media | Más preciso pero más caro. |

*Solo para PDFs digitales (generados por software, no escaneados).

**Recomendación COST-EFFECTIVE (híbrida):**

Para uso personal con pocas facturas/mes (~10-30), la mejor relación coste/precisión es:

1. **Capa 1 (gratis):** `smalot/pdfparser` para extraer texto de PDFs digitales
2. **Capa 2 (€0.003/factura):** Si el texto está vacío o es ilegible → enviar imagen a **GPT-4o-mini** para OCR + extracción estructurada

**Coste estimado mensual:** €0.03-0.10 (10-30 facturas, asumiendo ~30% necesitan OCR)

**Alternativa 100% gratis (menor precisión):**
- Solo `smalot/pdfparser` + regex para patrones comunes
- Formulario manual como fallback cuando falle la extracción
- Limitación: no funcionará con PDFs escaneados

**Decisión recomendada:** Enfoque híbrido con GPT-4o-mini como fallback. Es prácticamente gratis para volumen personal y ofrece la mejor precisión.

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
  - [ ] Dashboard placeholder + estilo (pendiente de validar estilo cargado en producción)
- [x] H2: Clientes (CRUD)
- [x] H3: Facturas (CRUD + cálculos + numeración)
- [x] H4: PDF de facturas (Dompdf)
- [ ] H5: Asistencia declaraciones trimestrales
- [ ] H5.1: **Gastos/Expenses con extracción de PDF** (NUEVA FUNCIONALIDAD)

### H5.1 - Desglose detallado

**Tarea 1: Migración BD + estructura de archivos**
- Crear `database/migrations/006_create_expenses.sql`
- Tabla `expenses`: id, supplier_name, supplier_nif, invoice_number, invoice_date, base_amount, vat_rate, vat_amount, total_amount, category, pdf_path, notes, status (pending/validated), created_at
- Crear directorio `storage/expenses/` con `.gitkeep`
- Success: Migración ejecutable sin errores, directorio creado

**Tarea 2: Upload de PDFs**
- Endpoint en `public/index.php` para `page=expenses&action=upload`
- Validar tipo MIME (application/pdf), tamaño max (10MB)
- Guardar con nombre único (UUID o timestamp) en `storage/expenses/`
- Success: PDF se sube y almacena correctamente

**Tarea 3: Servicio de extracción de texto (capa gratis)**
- Instalar `smalot/pdfparser` via Composer
- Crear `src/Services/PdfExtractorService.php`
- Método `extractText(string $pdfPath): string`
- Success: Extraer texto plano de PDF digital de prueba

**Tarea 4: Parser de datos de factura (regex)**
- Crear `src/Services/InvoiceParserService.php`
- Patrones regex para: NIF español (B12345678, 12345678A), fechas (dd/mm/yyyy, yyyy-mm-dd), importes (1.234,56 €), "Base imponible", "IVA", "Total"
- Método `parse(string $text): array` → devuelve campos encontrados + confianza
- Success: Parsear correctamente 2-3 facturas de prueba

**Tarea 5: Fallback con GPT-4o-mini (opcional, configurable)**
- Crear `src/Services/AiExtractorService.php`
- Convertir PDF a imagen (primera página) con Imagick o pdftoppm
- Llamar API OpenAI con imagen + prompt estructurado
- Devolver JSON con campos extraídos
- Guardar API key en `.env` (`OPENAI_API_KEY`)
- Toggle en settings para activar/desactivar AI extraction
- Success: Extraer datos de PDF escaneado correctamente

**Tarea 6: Formulario de gastos con pre-llenado**
- Template `templates/expenses_form.php`
- Flujo: subir PDF → extraer datos → mostrar formulario pre-llenado → editar si necesario → guardar
- Campos: proveedor, NIF, nº factura, fecha, base, %IVA, IVA, total, categoría (dropdown), notas
- Indicador visual de "campos auto-detectados" vs "introducidos manualmente"
- Success: Formulario funcional con datos pre-llenados editables

**Tarea 7: CRUD completo de gastos**
- `ExpensesRepository.php`: all(), find(), create(), update(), delete()
- Template `templates/expenses.php` (listado con filtros por fecha/categoría)
- Acciones: ver PDF original, editar, eliminar
- Success: CRUD completo funcional

**Tarea 8: Integración en declaraciones**
- Modificar `TaxQuarterService.php`:
  - `summarizeExpenses(year, quarter)`: base_total, iva_total (deducible)
  - `summarizeExpensesYTD(year, quarter)`: gastos acumulados
- Actualizar `templates/declaraciones.php`:
  - Casilla 45 (IVA deducible) con datos reales
  - Casilla 02 (Gastos) con datos reales
- Success: Declaraciones muestran IVA deducible y gastos reales

**Dependencias a instalar:**
```
composer require smalot/pdfparser
```

**Estimación de esfuerzo:** 6-8 horas de desarrollo
- [x] H6: Auth básica
- [x] H7: Despliegue y cron en cPanel
  - [x] Crear repo GitHub y primer push (COMPLETADO 2025-09-20)
  - [x] Configurar hosting (document root a `public/`, clonar repo, `composer install`, `.env` producción)
  - [x] Configurar cron diario 08:00 Europe/Madrid

## Current Status / Progress Tracking
- 2026-01-07: **H5.1 COMPLETADO** - Funcionalidad de Gastos con extracción de PDF implementada:
  - Tabla `expenses` creada (migración 006)
  - Upload de PDFs con validación MIME y tamaño
  - Servicio `PdfExtractorService` con smalot/pdfparser
  - Servicio `InvoiceParserService` con regex para NIF, fechas, importes españoles
  - Formulario con pre-llenado automático y cálculo base/IVA/total
  - CRUD completo con filtros por año/categoría
  - Integración en declaraciones: IVA deducible (casilla 45) y gastos acumulados (casilla 02)
  - Enlace "Gastos" añadido al menú de navegación
  - **Pendiente usuario:** Ejecutar migración BD en local/producción

- 2025-09-20: Planificación inicial completada (Planner). Aprobado pasar a Executor.
- 2025-09-20: Executor ha creado el esqueleto del proyecto:
  - Archivos clave: `composer.json`, `public/index.php`, `src/bootstrap.php`, `src/support/Config.php`, `src/Database.php`, `src/Services/EmailService.php`, `templates/*`, `assets/css/styles.css`, `scripts/run_reminders.php`, `database/migrations/001_init.sql`, `.env.example` y `.gitignore`.
  - Pendiente ejecutar `composer install` para descargar dependencias.
  - Pendiente crear BD e importar `database/migrations/001_init.sql`.
 - 2025-09-20: Git inicializado, primer commit y push a `origin/main` (repo: `dvdgp9/moni-app`).
- 2025-09-20: Ajustes persistentes y motor de recordatorios implementados. Cron configurado en cPanel (08:00 Europe/Madrid).
 - 2025-10-13: Executor: Corregida idempotencia de envíos para recordatorios (obligatorios). Ahora, cuando el esquema de `reminder_logs` no tiene columna `title`, se usa `reminder_id` para evitar falsos positivos que bloqueaban envíos. Código modificado en `src/Services/ReminderService.php`.

- 2025-10-15: Executor: Página `reminders` ahora ordena por próxima ocurrencia (desempate alfabético) con selector de orden (`next`/`far`/`alpha`) y badge informativo. Implementado en `templates/reminders.php`. TZ según ajustes (`Europe/Madrid` por defecto). Pendiente añadir tests unitarios de cálculo de `next_occurrence`.

- 2025-10-15: Executor: Implementado MVP de declaraciones.
  - Servicio `src/Services/TaxQuarterService.php`: `quarterRange()` y `summarizeSales(year, quarter)` filtrando `invoices.issue_date` por trimestre y `status IN ('issued','paid')`. Devuelve `base_total`, `iva_total`, `irpf_total`, desglose `by_vat` y `range`.
  - Página `templates/declaraciones.php`: selector año/trimestre; tarjetas de 303 (Base, Devengado 27, Deducible 45=0, Resultado 46) y 130 (Ingresos 01, Gastos 02=0, Rendimiento 03, 20% 04). Sin export por ahora.
  - Enrutador `public/index.php`: nueva ruta `page=declaraciones` protegida por sesión; añadido enlace en `templates/layout.php`.
  - Alcance: solo ventas desde facturas (no gastos ni IVA deducible). Redondeo a 2 decimales al final.
  - Pendiente: campos manuales persistentes, export CSV/PDF, tests del servicio (rangos de trimestre y acumulados), y validación con casos reales.

## Executor's Feedback or Assistance Requests
- Confirmar nombre del repositorio GitHub (p.ej., `moni` o `moni-app`).
- Confirmar si preferimos cron via PHP CLI (recomendado) y un endpoint web protegido como alternativa manual. Elegido: PHP CLI en cPanel.
- Proveer logo y colores cuando lleguemos al H4 (PDF/branding) o añadirlos a Ajustes cuando implementemos la sección de marca.

## Lessons
- Mantener cron idempotente con `reminder_logs` evita duplicados de envío el mismo día/evento.
- Diseñar numeración de facturas desacoplada en un servicio con transacción reduce colisiones.
- Configuración por `.env` + `settings` en BD permite multi-tenant futuro sin reconfigurar despliegue.
 - La verificación de duplicados debe usar identificadores estables. Si hay esquemas antiguos sin columna `title`, usar `reminder_id` por evento evita que un envío previo del día bloquee otros eventos diferentes.
