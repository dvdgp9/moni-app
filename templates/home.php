<?php
use Moni\Support\Config;

$appName = (string) Config::get('app_name', 'Moni');
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<section class="landing-hero">
  <div class="container landing-hero-grid">
    <div class="landing-copy">
      <span class="beta-pill">Beta gratuita en desarrollo</span>
      <h1 class="landing-title">Menos gestión. Más control.</h1>
      <p class="landing-subtitle">
        <?= htmlspecialchars($appName) ?> ayuda a pequeños autónomos a trabajar con más claridad: emitir y cobrar, enviar presupuestos,
        extraer gastos desde PDF o foto con ayuda de IA y revisar el trimestre sin montar un sistema paralelo con hojas, notas y recordatorios sueltos.
      </p>
      <div class="landing-actions">
        <a class="btn" href="<?= $isLoggedIn ? route_path('dashboard') : route_path('register') ?>">
          <?= $isLoggedIn ? 'Ir a mi panel' : 'Crear cuenta gratis' ?>
        </a>
        <a class="btn btn-secondary" href="#funciones">Ver cómo funciona</a>
      </div>
      <p class="landing-footnote">
        Durante esta fase el uso es gratuito a cambio de sugerencias de mejora e informes de errores.
      </p>
    </div>

    <div class="landing-preview">
      <div class="preview-shell">
        <div class="preview-toolbar">
          <span></span><span></span><span></span>
        </div>
        <div class="preview-board">
          <div class="preview-stat primary">
            <strong>6.420 EUR</strong>
            <span>Facturado este mes</span>
          </div>
          <div class="preview-stat">
            <strong>2</strong>
            <span>Presupuestos esperando respuesta</span>
          </div>
          <div class="preview-list">
            <div class="preview-list-header">
              <strong>Panel operativo</strong>
              <span>Ventas y fiscalidad</span>
            </div>
            <div class="preview-row overdue">
              <div>
                <strong>Cobros vencidos</strong>
                <span>2 facturas emitidas</span>
              </div>
              <div>
                <span>1.280 EUR</span>
                <em>Prioridad</em>
              </div>
            </div>
            <div class="preview-row">
              <div>
                <strong>IVA estimado</strong>
                <span>1T 2026</span>
              </div>
              <div>
                <span>438 EUR</span>
                <em>Trimestre</em>
              </div>
            </div>
            <div class="preview-row muted">
              <div>
                <strong>Lector de gastos con IA</strong>
                <span>PDF o imagen con revisión asistida</span>
              </div>
              <div>
                <span>Activo</span>
                <em>OCR + extracción</em>
              </div>
            </div>
          </div>
          <div class="preview-note">
            <strong>Centro fiscal</strong>
            <span>Revisión trimestral, modelos aplicables y avisos en un mismo sitio.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="landing-strip">
  <div class="container landing-strip-grid">
    <div>
      <strong>Hecho para actividades pequeñas</strong>
      <span>Freelance, consultoría, servicios, profesiones técnicas y negocios unipersonales.</span>
    </div>
    <div>
      <strong>Operación diaria clara</strong>
      <span>Ver qué toca ahora: cobrar, cerrar presupuestos, revisar gastos extraídos y preparar el trimestre.</span>
    </div>
    <div>
      <strong>IA aplicada a lo útil</strong>
      <span>Subes un PDF o una foto, Moni propone los datos del gasto y tú solo revisas antes de guardar.</span>
    </div>
  </div>
</section>

<section id="funciones" class="landing-section">
  <div class="container">
    <div class="section-lead">
      <span class="section-kicker">Funciones</span>
      <h2>Todo lo esencial para llevar tu actividad con cabeza.</h2>
      <p>No intentamos ser un ERP enorme. La idea es darte una vista clara de ventas, compras y fiscalidad con una herramienta ligera, práctica y cada vez más asistida.</p>
    </div>
    <div class="feature-grid">
      <article class="feature-card">
        <h3>Facturas con contexto</h3>
        <p>Crea borradores, emite, marca pagadas y filtra por períodos para localizar rápido lo importante y lo pendiente de cobrar.</p>
      </article>
      <article class="feature-card">
        <h3>Presupuestos con aceptación</h3>
        <p>Prepara presupuestos, envíalos por correo y deja que el cliente los acepte o rechace desde un enlace directo.</p>
      </article>
      <article class="feature-card">
        <h3>Gastos con extracción inteligente</h3>
        <p>Sube PDFs o fotos de tickets desde móvil y Moni propone proveedor, fecha, importes y categoría para que revises y guardes más rápido.</p>
      </article>
      <article class="feature-card">
        <h3>Centro fiscal y avisos</h3>
        <p>Consulta IVA, IRPF, checklist trimestral y recordatorios útiles sin depender de notas externas.</p>
      </article>
    </div>
  </div>
</section>

<section id="beneficios" class="landing-section landing-section-contrast">
  <div class="container landing-benefits">
    <div class="section-lead compact">
      <span class="section-kicker">Beneficios</span>
      <h2>Menos ruido administrativo, más claridad para trabajar.</h2>
    </div>
    <div class="benefit-list">
      <div class="benefit-item">
        <strong>Sabes que mover hoy</strong>
        <p>Cobros vencidos, presupuestos esperando respuesta, gastos pendientes y foco fiscal en una vista de trabajo real.</p>
      </div>
      <div class="benefit-item">
        <strong>Reduces olvidos y cambios de contexto</strong>
        <p>Menos tiempo buscando datos entre apps distintas y menos trabajo manual al registrar tickets y facturas recibidas.</p>
      </div>
      <div class="benefit-item">
        <strong>Centralizas lo esencial sin complicarte</strong>
        <p>Clientes, facturas, presupuestos, gastos, proveedores y fiscalidad en un solo sitio, pensado para estructura ligera.</p>
      </div>
      <div class="benefit-item">
        <strong>Revisas en vez de teclear desde cero</strong>
        <p>La extracción asistida de gastos reduce fricción en el día a día: subes el documento, revisas la propuesta y corriges solo si hace falta.</p>
      </div>
      <div class="benefit-item">
        <strong>Influyes directamente en el producto</strong>
        <p>Esta beta no es decorativa: priorizamos mejoras según uso real y feedback concreto.</p>
      </div>
    </div>
  </div>
</section>

<section class="landing-section">
  <div class="container">
    <div class="section-lead">
      <span class="section-kicker">Cómo encaja</span>
      <h2>Una herramienta simple por fuera, útil por dentro.</h2>
      <p>La parte pública te cuenta qué puedes hacer con Moni. Y cuando entras, tienes tu espacio para trabajar de verdad: ventas, presupuestos, gastos con IA y la parte fiscal en un mismo sitio.</p>
    </div>
    <div class="flow-grid">
      <div class="flow-step">
        <span>1</span>
        <strong>Landing pública</strong>
        <p>Aquí ves rápido si Moni encaja contigo: facturación, gastos más fáciles de registrar y control fiscal sin complicarte.</p>
      </div>
      <div class="flow-step">
        <span>2</span>
        <strong>Acceso o registro</strong>
        <p>Si te convence, entras o te registras en un momento y pasas directamente a tu zona de trabajo.</p>
      </div>
      <div class="flow-step">
        <span>3</span>
        <strong>Zona de trabajo</strong>
        <p>Dentro ya tienes todo más a mano: ventas, presupuestos, gastos extraídos, fiscalidad y ajustes para llevar el día a día con más calma.</p>
      </div>
    </div>
  </div>
</section>

<section id="precios" class="landing-section">
  <div class="container">
    <div class="pricing-card">
      <div class="pricing-copy">
        <span class="section-kicker">Precios</span>
        <h2>Beta gratuita</h2>
        <p class="pricing-price">0 €</p>
        <p class="pricing-description">
          Acceso completo durante la fase de desarrollo. Incluye facturas, presupuestos, gastos con extracción inteligente y centro fiscal. A cambio, te pedimos sugerencias de mejora y que nos informes si encuentras errores o fricciones en el uso real.
        </p>
        <ul class="pricing-list">
          <li>Acceso a facturas, presupuestos, gastos con IA, proveedores y centro fiscal</li>
          <li>Nuevas mejoras según evoluciona la beta</li>
          <li>Feedback directo para priorizar lo importante</li>
        </ul>
      </div>
      <div class="pricing-panel">
        <strong>Ideal si quieres probar desde ya</strong>
        <p>Si eres autónomo y quieres una herramienta clara para el día a día, ya puedes probar un flujo real: emitir, presupuestar, subir gastos en PDF o foto y revisarlo todo desde un mismo sitio.</p>
        <a class="btn" href="<?= $isLoggedIn ? route_path('dashboard') : route_path('register') ?>">
          <?= $isLoggedIn ? 'Abrir mi espacio' : 'Empezar gratis' ?>
        </a>
        <?php if (!$isLoggedIn): ?>
          <a class="pricing-login" href="<?= route_path('login') ?>">Ya tengo cuenta</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
