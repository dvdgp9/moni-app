# Presupuestos con envío y aceptación

## Background and Motivation
El usuario quiere añadir un sistema completo de **presupuestos (quotes)** a Moni con el siguiente flujo:
1. **Crear presupuestos** — formulario similar a facturas (cliente, líneas, IVA/IRPF, notas, fecha validez)
2. **Enviar por correo** con enlace seguro — email al cliente con link a vista pública
3. **Vista pública para el cliente** — página accesible sin login donde el cliente ve el presupuesto completo
4. **Aceptar/Rechazar** — el cliente puede aceptar o rechazar desde la vista pública
5. **Convertir a factura en un clic** — desde el panel del usuario, un presupuesto aceptado se convierte en factura (borrador)

La app ya tiene un sistema completo de facturas (`invoices` + `invoice_items`) con repositorios, formulario, listado, PDF y numeración. Los presupuestos seguirán patrones idénticos pero con estados y flujo propios.

## Key Challenges and Analysis

### Arquitectura de datos
- **Tabla `quotes`**: similar a `invoices` pero con campos adicionales: `token` (UUID para enlace seguro), `valid_until` (fecha de validez), `status` (draft/sent/accepted/rejected/expired/converted), `accepted_at`, `rejected_at`, `rejection_reason`, `converted_invoice_id` (FK a invoices).
- **Tabla `quote_items`**: idéntica a `invoice_items` pero referenciando `quote_id`.
- No se necesita numeración secuencial formal como facturas (no hay obligación legal), pero sí un identificador legible tipo `P-YYYY-NNNN`.

### Seguridad del enlace público
- Token UUID v4 (32 hex chars) en la URL: `/presupuesto/{token}` — impredecible, no adivinable.
- La vista pública NO requiere sesión. Se valida solo por token.
- El token se genera al crear el presupuesto.
- Considerar expiración: si `valid_until` ha pasado, mostrar mensaje de expirado.

### Envío por email
- Reutilizar `EmailService` con nuevo método `sendQuote()` y plantilla HTML dedicada.
- El email incluye: resumen del presupuesto, importe total, enlace al presupuesto, botones de acción.

### Vista pública
- Nueva ruta `/presupuesto/{token}` que NO pasa por autenticación.
- Usa `layout_public.php` o un layout minimalista propio (sin nav de la app).
- Muestra: datos del emisor, datos del cliente, líneas, totales, botones Aceptar/Rechazar.
- Al rechazar, opcionalmente pedir motivo.

### Conversión a factura
- Copiar datos del presupuesto (cliente, líneas, notas) a una nueva factura en estado `draft`.
- Marcar el presupuesto como `converted` y guardar `converted_invoice_id`.
- Un presupuesto solo se puede convertir una vez.

### Integración en UI existente
- Añadir "Presupuestos" al menú de navegación (entre Facturas y Gastos).
- Dashboard: tarjeta resumen de presupuestos pendientes.

## High-level Task Breakdown

### Tarea 1: Migración BD — tablas `quotes` y `quote_items`
**Archivos:** `database/migrations/009_create_quotes.sql`
**Detalle:**
- Tabla `quotes`: id, user_id, quote_number, client_id, status ENUM('draft','sent','accepted','rejected','expired','converted'), token VARCHAR(64) UNIQUE, issue_date, valid_until, notes, accepted_at, rejected_at, rejection_reason, converted_invoice_id, created_at
- Tabla `quote_items`: id, quote_id, description, quantity, unit_price, vat_rate, irpf_rate
- Índice en token (para búsqueda pública rápida)
**Criterio de éxito:** SQL ejecutable sin errores, tablas creadas con estructura correcta.

### Tarea 2: Repositorios — `QuotesRepository` y `QuoteItemsRepository`
**Archivos:** `src/Repositories/QuotesRepository.php`, `src/Repositories/QuoteItemsRepository.php`
**Detalle:**
- `QuotesRepository`: all(), find(), findByToken(), create(), update(), setStatus(), delete(), countByClient()
- `QuoteItemsRepository`: byQuote(), deleteByQuote(), insertMany()
- Patrón idéntico a `InvoicesRepository` / `InvoiceItemsRepository` con scoping por `user_id`
- `findByToken()` NO requiere autenticación (es para vista pública), pero sí valida que el token existe
**Criterio de éxito:** Métodos CRUD funcionales, findByToken devuelve datos sin requerir sesión.

### Tarea 3: Servicio de numeración — `QuoteNumberingService`
**Archivos:** `src/Services/QuoteNumberingService.php`
**Detalle:**
- Tabla `quote_sequences` (user_id, seq_year, last_number) — puede ir en la misma migración 009
- Formato: `P-YYYY-NNNN` (P de presupuesto)
- Asignación atómica como `InvoiceNumberingService`
**Criterio de éxito:** Numeración secuencial única por usuario y año.

### Tarea 4: Formulario de presupuesto — crear/editar
**Archivos:** `templates/quotes_form.php`
**Detalle:**
- Mismo formulario que `invoices_form.php`: cliente, fecha, líneas con IVA/IRPF, notas, totales en vivo
- Campo adicional: "Válido hasta" (fecha de validez, por defecto +30 días)
- Estado: Borrador / Enviado
- Al guardar como borrador, no se asigna número. Al enviar, se asigna número.
**Criterio de éxito:** Formulario funcional, crea/edita presupuestos con líneas y totales correctos.

### Tarea 5: Listado de presupuestos
**Archivos:** `templates/quotes_list.php`
**Detalle:**
- Tabla con: Nº, Cliente, Estado (badge con colores), Fecha, Válido hasta, Importe, Acciones
- Acciones: Editar, PDF, Enviar, Convertir a factura, Eliminar
- Filtros: búsqueda, año, estados
- Resumen: total presupuestos, pendientes, aceptados, importe
**Criterio de éxito:** Listado funcional con filtros y acciones correctas según estado.

### Tarea 6: PDF del presupuesto
**Archivos:** `templates/quotes_pdf.php`
**Detalle:**
- Reutilizar la plantilla de `invoices_pdf.php` adaptada: título "Presupuesto" en vez de "Factura", mostrar "Válido hasta", sin fecha de vencimiento de pago.
- Mismo estilo con branding del usuario (colores, logo).
**Criterio de éxito:** PDF generado con diseño coherente al de facturas.

### Tarea 7: Envío por email
**Archivos:** `src/Services/EmailService.php` (nuevo método), `templates/emails/quote.php`, `templates/emails/quote.txt.php`
**Detalle:**
- Método `sendQuote(string $to, string $subject, array $data): bool`
- Plantilla HTML branded: resumen del presupuesto, enlace público, CTA
- `$data`: brandName, appUrl, quoteNumber, clientName, total, validUntil, publicUrl
- Se envía al email del cliente (tomado de `clients.email`)
**Criterio de éxito:** Email recibido con diseño correcto y enlace funcional.

### Tarea 8: Vista pública del presupuesto
**Archivos:** `templates/quotes_public.php`
**Detalle:**
- Ruta: `/presupuesto/{token}` — se añade patrón regex al router en `index.php`
- Sin autenticación requerida. Solo token válido.
- Layout minimalista (sin nav de la app, solo branding del emisor)
- Muestra: datos emisor, datos cliente, líneas, totales, estado actual
- Si estado = `sent` y no expirado: botones "Aceptar" y "Rechazar"
- Si estado = `accepted`: mensaje "Presupuesto aceptado el DD/MM/YYYY"
- Si estado = `rejected`: mensaje "Presupuesto rechazado"
- Si expirado (valid_until < hoy y status=sent): mensaje "Presupuesto expirado"
- POST para aceptar/rechazar: valida token + CSRF, actualiza estado y timestamps
**Criterio de éxito:** Vista accesible por enlace, aceptar/rechazar funciona, estados se reflejan correctamente.

### Tarea 9: Conversión a factura
**Archivos:** lógica en `templates/quotes_list.php` (acción POST) o servicio dedicado
**Detalle:**
- Botón "Convertir a factura" solo visible si estado = `accepted`
- Crea factura draft con: mismo client_id, issue_date=hoy, copiar líneas, copiar notas
- Actualiza quote: status='converted', converted_invoice_id=nueva factura
- Redirige al formulario de la nueva factura para revisar/emitir
**Criterio de éxito:** Un clic crea factura borrador con datos del presupuesto, presupuesto queda marcado como convertido.

### Tarea 10: Integración en navegación y rutas
**Archivos:** `public/index.php`, `src/bootstrap.php`, `templates/layout.php`
**Detalle:**
- Nuevas rutas: `/presupuestos`, `/presupuestos/nuevo`, `/presupuestos/editar`, `/presupuestos/pdf`, `/presupuesto/{token}` (pública)
- Enlace "Presupuestos" en nav (entre Facturas y Gastos)
- Rutas protegidas excepto la pública
**Criterio de éxito:** Navegación funcional, rutas resuelven correctamente, vista pública no pide login.

## Project Status Board
- [x] T1: Migración BD (quotes, quote_items, quote_sequences)
- [x] T2: Repositorios (QuotesRepository, QuoteItemsRepository)
- [x] T3: Servicio de numeración (QuoteNumberingService)
- [x] T4: Formulario de presupuesto (crear/editar)
- [x] T5: Listado de presupuestos
- [x] T6: PDF del presupuesto
- [x] T7: Envío por email (método + plantillas)
- [x] T8: Vista pública del presupuesto (ruta + template + aceptar/rechazar)
- [x] T9: Conversión a factura
- [x] T10: Integración en navegación y rutas

## Current Status / Progress Tracking
- 2026-03-27: **Presupuestos IMPLEMENTADO** — Todos los archivos creados:
  - Migración: `database/migrations/009_create_quotes.sql` (3 tablas: quotes, quote_items, quote_sequences)
  - Repositorios: `QuotesRepository.php` (con findByToken, acceptByToken, rejectByToken, markConverted), `QuoteItemsRepository.php` (con byQuotePublic para vista pública)
  - Servicio: `QuoteNumberingService.php` (formato P-YYYY-NNNN)
  - Templates: `quotes_form.php`, `quotes_list.php`, `quotes_pdf.php`, `quotes_public.php`
  - Email: `EmailService::sendQuote()` + plantillas `emails/quote.php` y `emails/quote.txt.php`
  - Rutas: `index.php` actualizado con rutas protegidas + ruta pública `/presupuesto/{token}`
  - Navegación: enlace "Presupuestos" añadido en `layout.php`
  - CSS: badge `status-converted` añadido en `styles.css`
  - **Pendiente usuario:** Ejecutar migración `009_create_quotes.sql` en BD local/producción

## Executor's Feedback or Assistance Requests
- El usuario debe ejecutar la migración SQL antes de probar la funcionalidad.
- La vista pública tiene su propio CSS inline completo (no depende de styles.css de la app), diseñada para ser limpia y profesional para clientes.

## Lessons
- La ruta pública `/presupuesto/{token}` usa regex en el router porque es un patrón dinámico, no una ruta estática. Se procesa antes del auth check y sale con exit.
- El token es de 64 caracteres hex (32 bytes random) — suficientemente seguro para enlaces públicos.
- Los badges de estado reutilizan las clases CSS existentes (status-draft, status-issued=sent, status-paid=accepted, status-cancelled=rejected) más el nuevo status-converted.

---

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

---

# Planner: Benchmark TaxHacker para mejorar lector de gastos IA en Moni (2026-04-12)

## Background and Motivation
El usuario quiere reemplazar el sistema actual de extracción de facturas (regex-based, que no funciona bien) por un **pipeline IA-first usando OpenRouter** como proveedor de LLM. Inspirado en TaxHacker pero adaptado a Moni.

**Decisiones del usuario:**
- **OpenRouter** como proveedor principal (API OpenAI-compatible en `https://openrouter.ai/api/v1`).
- **IA-first**: toda la extracción (PDF e imágenes) pasa por IA. El parser regex actual (`InvoiceParserService`) queda obsoleto.
- **Auto-categorización** incluida en el prompt de IA.
- El parser regex no se elimina físicamente aún (por si falla la IA), pero deja de ser el flujo principal.

**Contexto actual confirmado en código:**
- `ExpenseDocumentService`: almacena PDF/imagen, clasifica `document_kind` (pdf/image).
- `PdfExtractorService`: extrae texto con `smalot/pdfparser`. Se conserva para enviar texto al LLM como contexto adicional (más barato que visión).
- `InvoiceParserService`: ~494 líneas de regex. Queda como fallback si IA está desactivada.
- `expense_form.php`: flujo AJAX `action=extract` → store → parse → respuesta JSON → fillForm JS.
- `SettingsRepository`: key-value por usuario en tabla `settings`. Patrón `get(key)`/`set(key, value)`.
- `.env.example`: sin variables de IA aún.
- Categorías hardcodeadas en `ExpensesRepository::getCategories()`.
- Migraciones hasta `009_create_quotes.sql`.

## Key Challenges and Analysis

### Arquitectura del pipeline IA-first

```
Documento (PDF/imagen)
  │
  ├─ ExpenseDocumentService::storeUploaded()  (ya existe)
  │
  ├─ ¿Es PDF con texto útil?
  │     SÍ → extraer texto (PdfExtractorService) → enviar TEXTO al LLM (más barato, sin visión)
  │     NO → convertir primera página a imagen
  │
  ├─ ¿Es imagen?
  │     SÍ → enviar IMAGEN base64 al LLM (visión multimodal)
  │
  ├─ AiExtractorService::extract()
  │     → POST a OpenRouter /chat/completions
  │     → Prompt estructurado → respuesta JSON
  │     → Parsear y normalizar resultado
  │
  ├─ SuppliersRepository::findMatch() (ya existe) para enlazar proveedor
  │
  └─ Devolver resultado unificado al frontend
```

### Prompt de extracción (español-first)
El prompt pide JSON con campos fijos + categoría sugerida + confianza por campo.
Campos: `supplier_name`, `supplier_nif`, `invoice_number`, `invoice_date`, `base_amount`, `vat_rate`, `vat_amount`, `total_amount`, `suggested_category`, `confidence`.
Categorías válidas se inyectan en el prompt desde `ExpensesRepository::getCategories()`.

### OpenRouter - detalles técnicos
- Endpoint: `https://openrouter.ai/api/v1/chat/completions`
- Auth: `Authorization: Bearer {API_KEY}`
- Headers extra recomendados: `HTTP-Referer: {APP_URL}`, `X-Title: Moni`
- Modelo recomendado: `google/gemini-2.0-flash-001` (barato, bueno en OCR, soporta visión)
- Alternativas: `openai/gpt-4o-mini`, `anthropic/claude-3.5-haiku`
- Para texto (sin visión): cualquier modelo barato sirve
- Para imagen (visión): necesita modelo multimodal

### PDF a imagen
- Opción A: `Imagick` (extensión PHP) — `$im = new Imagick(); $im->readImage($pdf.'[0]'); $im->setImageFormat('jpg');`
- Opción B: shell `pdftoppm -jpeg -r 200 -f 1 -l 1 input.pdf output`
- Opción C: si ninguna disponible, enviar solo texto al LLM (degradación graceful)
- Detectar disponibilidad en runtime y elegir la mejor

### Config y seguridad
- API key **solo en `.env`** (nunca en BD). Variable: `OPENROUTER_API_KEY`.
- Modelo y base URL configurables desde Settings (BD) para poder cambiar sin redesplegar.
- Settings keys: `ai_enabled`, `ai_model`, `ai_base_url` (default OpenRouter).
- Timeout: 30s configurable.

## High-level Task Breakdown

### T1: Config — `.env` + Settings de IA
**Archivos a modificar:**
- `.env.example` — añadir `OPENROUTER_API_KEY=`
- `templates/settings.php` — nueva sección "Extracción inteligente (IA)" (toggle, modelo, base URL, botón test)
- Lógica de guardado en `settings.php` para las nuevas keys

**Detalle:**
- `OPENROUTER_API_KEY` en `.env` (seguro, nunca en BD)
- `ai_enabled` (0/1) en settings BD — default `1` si hay API key
- `ai_model` en settings BD — default `google/gemini-2.0-flash-001`
- `ai_base_url` en settings BD — default `https://openrouter.ai/api/v1`
- `ai_timeout` en settings BD — default `30`

**Criterio de éxito:** Se pueden guardar/leer ajustes de IA desde la página de Settings. La API key se lee de `.env`.

---

### T2: `AiExtractorService.php` — servicio de extracción con IA
**Archivo nuevo:** `src/Services/AiExtractorService.php`

**Detalle:**
- Método principal: `extract(string $contentOrImagePath, string $mode = 'text'): array`
  - `$mode = 'text'`: envía texto plano en el prompt (sin visión)
  - `$mode = 'image'`: codifica imagen en base64 y usa content type `image_url`
- Construye el payload OpenAI-compatible:
  ```php
  [
    'model' => $model,
    'messages' => [
      ['role' => 'system', 'content' => $systemPrompt],
      ['role' => 'user', 'content' => $userContent], // texto o [{type:text},{type:image_url}]
    ],
    'response_format' => ['type' => 'json_object'],
    'temperature' => 0.1,
    'max_tokens' => 1000,
  ]
  ```
- Usa `curl` nativo PHP (sin dependencias extra)
- System prompt incluye:
  - Instrucciones de extracción en español
  - Lista de categorías válidas (inyectadas dinámicamente)
  - Formato JSON esperado con ejemplo
  - Instrucción de devolver `null` si un campo no se encuentra
  - Instrucción de devolver confianza `high`/`medium`/`low` por campo
- Parsea respuesta JSON y normaliza:
  - Fechas a `YYYY-MM-DD`
  - Importes a `float`
  - NIF a mayúsculas
- Método auxiliar: `testConnection(): bool` — para botón test en Settings
- Manejo de errores: timeout, rate limit, respuesta inválida → devuelve array vacío + log

**Criterio de éxito:** Dado un texto o imagen base64, devuelve array con campos extraídos o array vacío si falla. Logs de error informativos.

---

### T3: `PdfToImageService.php` — conversión PDF a imagen
**Archivo nuevo:** `src/Services/PdfToImageService.php`

**Detalle:**
- Método: `convertFirstPage(string $pdfPath): ?string` → devuelve ruta a JPG temporal o `null`
- Intenta en orden:
  1. `Imagick` (si extensión cargada)
  2. `pdftoppm` via `shell_exec` (si binario disponible)
  3. `null` (degradación: se usará solo texto)
- Guarda imagen temporal en `storage/expenses/tmp_*.jpg`
- Limpieza: el llamante borra el temporal después de usarlo
- Método auxiliar: `isAvailable(): bool` — para diagnosticar en Settings

**Criterio de éxito:** Convierte primera página de PDF a JPG en al menos un entorno (dev o producción). Devuelve null si no puede.

---

### T4: Reescribir flujo de extracción en `expense_form.php`
**Archivo a modificar:** `templates/expense_form.php` (bloque `action === 'extract'`, líneas ~57-117)

**Nuevo flujo (reemplaza el actual):**
```
1. Store documento (sin cambios)
2. Si IA habilitada Y hay API key:
   a. PDF con texto útil → AiExtractorService::extract(texto, 'text')
   b. PDF sin texto útil → PdfToImageService::convertFirstPage() → AiExtractorService::extract(imagen, 'image')
   c. Imagen → AiExtractorService::extract(imagen, 'image')
3. Si IA NO habilitada o falla:
   a. PDF → PdfExtractorService + InvoiceParserService (fallback actual)
   b. Imagen → sin extracción (manual)
4. SuppliersRepository::findMatch() con datos extraídos
5. Devolver JSON con:
   - extracted (datos)
   - extraction_source ('ai' | 'regex' | 'manual')
   - suggested_category (nuevo campo)
   - message adaptado según fuente
```

**Cambios en JS `fillForm()`:**
- Nuevo campo `suggested_category` → pre-selecciona `<select id="category">`
- Indicadores de fuente: badge "Extraído por IA" vs "Extraído localmente" vs "Manual"
- Mensajes de confianza por campo (ya existe, se mantiene)

**Criterio de éxito:** Subir un ticket/factura (PDF o imagen) → IA extrae todos los campos → formulario pre-llenado → usuario revisa y guarda.

---

### T5: UI — indicadores de IA en el formulario
**Archivo a modificar:** `templates/expense_form.php` (zona de resultado de extracción)

**Detalle:**
- Banner de resultado muestra fuente: "Datos extraídos con IA (modelo X)" / "Datos extraídos localmente" / "Completa los datos manualmente"
- Badge por campo de confianza (ya existe el sistema `field-hint`, se mantiene)
- Si la IA sugiere categoría, el `<select>` se pre-selecciona y muestra hint "Categoría sugerida por IA"
- Si la IA falla, mostrar warning claro y permitir reintento o continuar manual

**Criterio de éxito:** El usuario distingue visualmente qué extrajo la IA y qué necesita revisar.

---

### T6: Settings UI — sección de IA
**Archivo a modificar:** `templates/settings.php`

**Detalle:**
- Nueva sección/card "Extracción inteligente (IA)" con:
  - Toggle activar/desactivar (`ai_enabled`)
  - Campo modelo (`ai_model`) con placeholder y sugerencia
  - Campo base URL (`ai_base_url`) con default OpenRouter
  - Indicador de API key configurada (lee de `.env`, muestra "✓ Configurada" o "✗ No configurada — añade OPENROUTER_API_KEY en .env")
  - Botón "Probar conexión" → AJAX → `AiExtractorService::testConnection()` → muestra resultado
  - Info de coste estimado por documento
- Posición: después de la sección SMTP/recordatorios

**Criterio de éxito:** El usuario puede activar/desactivar IA, cambiar modelo, y verificar conexión desde Settings.

---

### T7: Tests manuales y edge cases
**Verificar:**
- PDF digital (texto) → extracción por IA modo texto ✓
- PDF escaneado (imagen) → conversión + extracción por IA modo imagen ✓
- Imagen JPG desde móvil (ticket) → extracción por IA modo imagen ✓
- IA desactivada → fallback a regex (comportamiento actual) ✓
- API key no configurada → warning en UI, fallback a regex ✓
- Timeout/error de red → warning en UI, formulario manual ✓
- Documento sin datos reconocibles → mensaje claro ✓

**Criterio de éxito:** Todos los casos funcionan sin errores. Logs claros.

## Project Status Board
- [x] PB1: Benchmark TaxHacker vs Moni (alto nivel)
- [x] PB2: Identificar gaps y oportunidades concretas
- [x] PB3: Definir roadmap técnico detallado IA-first + OpenRouter
- [x] T1: Config — `.env` + Settings de IA
- [x] T2: `AiExtractorService.php`
- [x] T3: `PdfToImageService.php`
- [x] T4: Reescribir flujo extracción en `expense_form.php`
- [x] T5: UI — indicadores de IA en formulario
- [x] T6: Settings UI — sección de IA
- [ ] T7: Tests manuales y edge cases

## Current Status / Progress Tracking
- 2026-04-12 (Planner): benchmark completado.
- 2026-04-12 (Planner): plan técnico detallado completado. **7 tareas**, enfoque IA-first con OpenRouter. Listo para Executor.
- 2026-04-12 (Executor): **T1 completada**.
  - `.env.example`: añadido `OPENROUTER_API_KEY=`.
  - `src/bootstrap.php`: defaults IA (`ai_enabled`, `ai_model=google/gemini-3.1-flash-lite-preview`, `ai_base_url`, `ai_timeout`) + carga de overrides desde `SettingsRepository`.
  - `templates/settings.php`: nueva sección "Extracción inteligente (IA)" (toggle, modelo, base URL, timeout, estado de API key en `.env`) y persistencia de ajustes con validaciones.
  - Seguridad: la API key que compartió el usuario **no** se ha escrito en código ni en archivos versionados.
- 2026-04-12 (Executor): **T2 completada**.
  - Nuevo servicio `src/Services/AiExtractorService.php`.
  - Implementados métodos:
    - `isConfigured()` y `isEnabled()`.
    - `extractFromText(string $text, array $categories): array`.
    - `extractFromImagePath(string $imagePath, array $categories): array`.
    - `testConnection(): bool`.
  - Integración OpenRouter OpenAI-compatible (`/chat/completions`) con headers `Authorization`, `HTTP-Referer`, `X-Title`.
  - Prompt estructurado en español con campos fiscales + `suggested_category` + `confidence`.
  - Normalización de salida (fecha, importes, NIF, categoría).
  - Validación rápida: `php -l src/Services/AiExtractorService.php` sin errores.
- 2026-04-12 (Executor): **T3 completada**.
  - Nuevo servicio `src/Services/PdfToImageService.php`.
  - Conversión de primera página PDF a JPG con estrategia:
    1) `Imagick` si está disponible,
    2) `pdftoppm` por shell si existe,
    3) degradación a `null` si no hay backend de conversión.
  - Método `isAvailable()` añadido para diagnóstico.
- 2026-04-12 (Executor): **T4 completada**.
  - `templates/expense_form.php` actualizado a flujo **IA-first**.
  - PDF:
    - Si hay texto útil -> `AiExtractorService::extractFromText()`.
    - Si no hay texto útil -> `PdfToImageService::convertFirstPage()` + `AiExtractorService::extractFromImagePath()`.
  - Imagen:
    - `AiExtractorService::extractFromImagePath()`.
  - Fallback activo a `InvoiceParserService::parse()` solo si IA falla o no devuelve datos.
  - Respuesta JSON ampliada con `extraction_source` (`ai|regex|manual`) y mensajes diferenciados.
  - Validación sintáctica OK: `php -l src/Services/PdfToImageService.php` y `php -l templates/expense_form.php`.
- 2026-04-12 (Executor): **T5 completada**.
  - `templates/expense_form.php`: añadido badge visual de fuente de extracción (`IA`, `Local`, `Manual`).
  - Añadido hint de categoría y autoselección de `suggested_category` cuando llega desde IA.
  - Mensajería de resultado refinada para mejorar claridad al usuario.
- 2026-04-12 (Executor): **T6 completada**.
  - `templates/settings.php`: acción `test_ai_connection` con `AiExtractorService::testConnection()`.
  - Añadido botón "Probar conexión IA" en la card de IA.
  - Feedback por flash (`success/error`) según conexión OpenRouter.
  - Validación sintáctica OK: `php -l templates/settings.php` y `php -l templates/expense_form.php`.
- 2026-04-12 (Executor): **Hotfix extracción JSON aplicado** tras prueba manual del usuario.
  - Síntoma: `Unexpected token '<', "<!doctype ..." is not valid JSON` al subir PDF/imagen.
  - Causa raíz confirmada: `public/index.php` envolvía `expense_form` con el layout HTML en peticiones POST AJAX, porque no existía excepción de router para extracción.
  - Fix en `templates/expense_form.php`:
    - POST ahora a URL actual (`window.location.pathname + window.location.search`).
    - `credentials: 'same-origin'` + header `X-Requested-With`.
    - Parseo robusto: leer `response.text()`, intentar `JSON.parse`, mostrar snippet de respuesta si no es JSON.
  - Fix adicional en `public/index.php`:
    - bypass del layout para `expense_form` cuando la petición es POST de extracción (`action=extract` o XHR + archivo).
  - Validación sintáctica OK: `php -l templates/expense_form.php`.
- 2026-04-12 (Executor): **Landing pública actualizada** para reflejar estado real del producto.
  - `templates/home.php`: copy revisado para destacar extracción inteligente de gastos desde PDF/foto, revisión asistida y posicionamiento actual del producto.
  - `templates/layout_public.php`: footer ajustado para alinear el mensaje con facturación + gastos con ayuda inteligente + claridad fiscal.
  - Objetivo: que la home ya no describa el lector de gastos como "scanner base", sino como capacidad activa del producto.

## Executor's Feedback or Assistance Requests
- ✅ Modelo default fijado por usuario: `google/gemini-3.1-flash-lite-preview`.
- T7 listo para ejecución manual conjunta (usuario + executor):
  1) Ajustes -> IA: guardar modelo/base URL/timeout y pulsar "Probar conexión IA".
  2) Subir PDF digital -> comprobar prellenado y badge "IA".
  3) Subir imagen ticket -> comprobar extracción por IA.
  4) Simular fallback: desactivar IA y subir PDF -> comprobar badge "Local"/comportamiento regex.
  5) Verificar que `suggested_category` rellena categoría cuando existe.
  6) Confirmar guardado final del gasto en CRUD sin regresiones.

## Lessons
- OpenRouter es API OpenAI-compatible: mismo formato de payload, solo cambia base URL y auth header.
- Para facturas españolas, enviar texto al LLM es significativamente más barato que enviar imagen (~10x). Solo usar visión cuando no hay texto extraíble.
- El parser regex actual (`InvoiceParserService`) tiene ~494 líneas y falla frecuentemente. No merece más inversión; mejor redirigir a IA.
- `SettingsRepository` soporta key-value por usuario → ideal para `ai_enabled`, `ai_model`, `ai_base_url`.
- La API key debe ir en `.env` (no en BD) por seguridad. Se lee con `$_ENV['OPENROUTER_API_KEY']`.
- En formularios con rutas `nuevo/editar`, para AJAX POST es más seguro usar URL actual (`window.location.pathname + window.location.search`) que un helper fijo de ruta, para evitar respuestas HTML por redirección y errores de parseo JSON.
- Si un template necesita devolver JSON en esta app, además del código del template hay que añadir una excepción en `public/index.php` para evitar que el layout lo envuelva.
- Cuando una capacidad pasa de "beta interna" a flujo real usable, la landing debe actualizarse para reflejarla explícitamente; si no, el producto parece menos avanzado de lo que ya es.
