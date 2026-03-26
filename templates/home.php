<?php
use Moni\Support\Config;

$appName = (string) Config::get('app_name', 'Moni');
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<section class="landing-hero">
  <div class="container landing-hero-grid">
    <div class="landing-copy">
      <span class="beta-pill">Beta gratuita en desarrollo</span>
      <h1 class="landing-title">Controla facturas, gastos y recordatorios sin montarte otro trabajo paralelo.</h1>
      <p class="landing-subtitle">
        <?= htmlspecialchars($appName) ?> ayuda a pequeños autonomos a tener su actividad clara, ordenada y al dia,
        sin hojas de calculo eternas ni herramientas demasiado grandes para trabajar solo.
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
            <strong>4.860 €</strong>
            <span>Facturado este mes</span>
          </div>
          <div class="preview-stat">
            <strong>6</strong>
            <span>Pendientes de cobro</span>
          </div>
          <div class="preview-list">
            <div class="preview-list-header">
              <strong>Facturas relevantes</strong>
              <span>Periodo actual</span>
            </div>
            <div class="preview-row overdue">
              <div>
                <strong>2026-0018</strong>
                <span>Estudio Rivera</span>
              </div>
              <div>
                <span>12/03/2026</span>
                <em>Vencida</em>
              </div>
            </div>
            <div class="preview-row">
              <div>
                <strong>2026-0019</strong>
                <span>Laura Castillo</span>
              </div>
              <div>
                <span>19/03/2026</span>
                <em>Emitida</em>
              </div>
            </div>
            <div class="preview-row muted">
              <div>
                <strong>Borrador</strong>
                <span>Consultoria mensual</span>
              </div>
              <div>
                <span>28/03/2026</span>
                <em>Preparando</em>
              </div>
            </div>
          </div>
          <div class="preview-note">
            <strong>Proximos avisos</strong>
            <span>Modelo trimestral, cuota y vencimientos en un mismo sitio.</span>
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
      <strong>Prioridad real</strong>
      <span>Ver que toca ahora: cobrar, emitir, revisar gastos y no olvidar fechas importantes.</span>
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
      <p>No intentamos ser un ERP enorme. La idea es darte claridad en el dia a dia con una herramienta que puedas abrir y entender al momento.</p>
    </div>
    <div class="feature-grid">
      <article class="feature-card">
        <h3>Facturas con contexto</h3>
        <p>Crea borradores, emite, marca pagadas y filtra por periodos para localizar rapido lo importante.</p>
      </article>
      <article class="feature-card">
        <h3>Gastos organizados</h3>
        <p>Registra facturas recibidas, clasificalas y manten una base limpia para revisar lo deducible y lo pendiente.</p>
      </article>
      <article class="feature-card">
        <h3>Clientes y datos listos</h3>
        <p>Guarda la informacion de tus clientes y reutilizala sin tener que rehacer datos en cada documento.</p>
      </article>
      <article class="feature-card">
        <h3>Recordatorios utiles</h3>
        <p>Ten visibles las fechas que no quieres volver a perseguir en notas sueltas o en el calendario.</p>
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
        <strong>Ves rapidamente que facturas importan ahora</strong>
        <p>Fechas, estados y relevancia del periodo en una vista de trabajo de verdad.</p>
      </div>
      <div class="benefit-item">
        <strong>Reduces olvidos y tareas repetitivas</strong>
        <p>Menos tiempo buscando datos, menos dispersion y menos dependencias de hojas manuales.</p>
      </div>
      <div class="benefit-item">
        <strong>Centralizas lo esencial sin complicarte</strong>
        <p>Clientes, facturas, gastos y recordatorios en un solo sitio, pensado para alguien que trabaja solo o con estructura ligera.</p>
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
        <p>Una vez autenticado, el usuario entra al dashboard y navega por clientes, facturas, gastos y ajustes.</p>
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
          Acceso completo durante la fase de desarrollo. A cambio, te pedimos sugerencias de mejora y que nos informes si encuentras errores.
        </p>
        <ul class="pricing-list">
          <li>Acceso a toda la plataforma actual</li>
          <li>Nuevas mejoras segun evoluciona la beta</li>
          <li>Feedback directo para priorizar lo importante</li>
        </ul>
      </div>
      <div class="pricing-panel">
        <strong>Ideal si quieres probar desde ya</strong>
        <p>Si eres autonomo y te interesa una herramienta clara para tu operativa diaria, puedes entrar ahora y ayudarnos a pulirla con uso real.</p>
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
