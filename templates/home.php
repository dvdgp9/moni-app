<?php
use Moni\Support\Config;

$appName = (string) Config::get('app_name', 'Moni');
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<section class="landing-hero">
  <div class="container landing-hero-grid">
    <div class="landing-copy">
      <span class="beta-pill">Beta gratuita en desarrollo</span>
      <h1 class="landing-title">Facturas, presupuestos, gastos y fiscalidad en una sola herramienta pensada para autonomos.</h1>
      <p class="landing-subtitle">
        <?= htmlspecialchars($appName) ?> ayuda a pequenos autonomos a trabajar con mas claridad: emitir y cobrar, enviar presupuestos,
        registrar gastos desde movil y revisar el trimestre sin montar un sistema paralelo con hojas, notas y recordatorios sueltos.
      </p>
      <div class="landing-actions">
        <a class="btn" href="<?= $isLoggedIn ? route_path('dashboard') : route_path('register') ?>">
          <?= $isLoggedIn ? 'Ir a mi panel' : 'Crear cuenta gratis' ?>
        </a>
        <a class="btn btn-secondary" href="#funciones">Ver como funciona</a>
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
                <strong>Scanner de tickets</strong>
                <span>Foto o PDF desde movil</span>
              </div>
              <div>
                <span>Listo</span>
                <em>Gastos</em>
              </div>
            </div>
          </div>
          <div class="preview-note">
            <strong>Centro fiscal</strong>
            <span>Revision trimestral, modelos aplicables y avisos en un mismo sitio.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="landing-strip">
  <div class="container landing-strip-grid">
    <div>
      <strong>Hecho para actividades pequenas</strong>
      <span>Freelance, consultoria, servicios, profesiones tecnicas y negocios unipersonales.</span>
    </div>
    <div>
      <strong>Operacion diaria clara</strong>
      <span>Ver que toca ahora: cobrar, cerrar presupuestos, revisar gastos y preparar el trimestre.</span>
    </div>
    <div>
      <strong>Beta con feedback</strong>
      <span>Tu uso y tus sugerencias ayudan a decidir que mejoramos primero.</span>
    </div>
  </div>
</section>

<section id="funciones" class="landing-section">
  <div class="container">
    <div class="section-lead">
      <span class="section-kicker">Funciones</span>
      <h2>Todo lo esencial para llevar tu actividad con cabeza.</h2>
      <p>No intentamos ser un ERP enorme. La idea es darte una vista clara de ventas, compras y fiscalidad con una herramienta que puedas abrir y entender al momento.</p>
    </div>
    <div class="feature-grid">
      <article class="feature-card">
        <h3>Facturas con contexto</h3>
        <p>Crea borradores, emite, marca pagadas y filtra por periodos para localizar rapido lo importante y lo pendiente de cobrar.</p>
      </article>
      <article class="feature-card">
        <h3>Presupuestos con aceptacion</h3>
        <p>Prepara presupuestos, envialos por correo y deja que el cliente los acepte o rechace desde un enlace directo.</p>
      </article>
      <article class="feature-card">
        <h3>Gastos y scanner base</h3>
        <p>Sube PDFs o fotos de tickets desde movil, vincula proveedores y deja preparado el gasto para revisarlo mas rapido.</p>
      </article>
      <article class="feature-card">
        <h3>Centro fiscal y avisos</h3>
        <p>Consulta IVA, IRPF, checklist trimestral y recordatorios utiles sin depender de notas externas.</p>
      </article>
    </div>
  </div>
</section>

<section id="beneficios" class="landing-section landing-section-contrast">
  <div class="container landing-benefits">
    <div class="section-lead compact">
      <span class="section-kicker">Beneficios</span>
      <h2>Menos ruido administrativo, mas claridad para trabajar.</h2>
    </div>
    <div class="benefit-list">
      <div class="benefit-item">
        <strong>Sabes que mover hoy</strong>
        <p>Cobros vencidos, presupuestos esperando respuesta, gastos pendientes y foco fiscal en una vista de trabajo real.</p>
      </div>
      <div class="benefit-item">
        <strong>Reduces olvidos y cambios de contexto</strong>
        <p>Menos tiempo buscando datos entre apps distintas y menos dependencias de hojas manuales.</p>
      </div>
      <div class="benefit-item">
        <strong>Centralizas lo esencial sin complicarte</strong>
        <p>Clientes, facturas, presupuestos, gastos, proveedores y fiscalidad en un solo sitio, pensado para estructura ligera.</p>
      </div>
      <div class="benefit-item">
        <strong>Influyes directamente en el producto</strong>
        <p>Esta beta no es decorativa: priorizamos mejoras segun uso real y feedback concreto.</p>
      </div>
    </div>
  </div>
</section>

<section class="landing-section">
  <div class="container">
    <div class="section-lead">
      <span class="section-kicker">Como encaja</span>
      <h2>Web publica por un lado, aplicacion por otro.</h2>
      <p>La entrada publica explica el producto y capta nuevos usuarios. La zona privada queda reservada para trabajar dentro de la aplicacion sin mezclar navegacion comercial y operativa.</p>
    </div>
    <div class="flow-grid">
      <div class="flow-step">
        <span>1</span>
        <strong>Landing publica</strong>
        <p>El trafico llega a la home, conoce el producto, precios beta y propuesta para autonomos.</p>
      </div>
      <div class="flow-step">
        <span>2</span>
        <strong>Acceso o registro</strong>
        <p>Desde ahi se entra por rutas dedicadas: login y alta, sin saltar directamente al dashboard.</p>
      </div>
      <div class="flow-step">
        <span>3</span>
        <strong>Zona de trabajo</strong>
        <p>Una vez autenticado, el usuario entra al dashboard y navega por ventas, compras, fiscalidad y ajustes.</p>
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
          Acceso completo durante la fase de desarrollo. A cambio, te pedimos sugerencias de mejora y que nos informes si encuentras errores o fricciones en el uso real.
        </p>
        <ul class="pricing-list">
          <li>Acceso a facturas, presupuestos, gastos, proveedores y centro fiscal</li>
          <li>Nuevas mejoras segun evoluciona la beta</li>
          <li>Feedback directo para priorizar lo importante</li>
        </ul>
      </div>
      <div class="pricing-panel">
        <strong>Ideal si quieres probar desde ya</strong>
        <p>Si eres autonomo y te interesa una herramienta clara para tu operativa diaria, puedes entrar ahora y ayudarnos a pulirla con uso real de verdad.</p>
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
