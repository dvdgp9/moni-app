<?php
$today = new DateTime('today');
$year = (int)$today->format('Y');
$events = [
    ["title" => "Inicio trimestre Q1", "date" => "$year-01-01"],
    ["title" => "Inicio trimestre Q2", "date" => "$year-04-01"],
    ["title" => "Inicio trimestre Q3", "date" => "$year-07-01"],
    ["title" => "Inicio trimestre Q4", "date" => "$year-10-01"],
];
?>
<section>
  <h1>Dashboard</h1>
  <div class="cards">
    <div class="card">
      <h3>Próximos eventos</h3>
      <ul>
        <?php foreach ($events as $e): ?>
          <li>
            <strong><?= htmlspecialchars($e['title']) ?>:</strong>
            <span><?= htmlspecialchars((new DateTime($e['date']))->format('d/m/Y')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="card">
      <h3>Resumen</h3>
      <p>Próximamente: resumen de ingresos/gastos por trimestre.</p>
    </div>
  </div>
</section>
