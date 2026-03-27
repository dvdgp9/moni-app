<?php
// Variables: $brandName, $appUrl, $quoteNumber, $clientName, $total, $validUntil, $publicUrl
$brandName = $brandName ?? 'Moni';
$quoteNumber = $quoteNumber ?? '';
$clientName = $clientName ?? '';
$total = $total ?? '';
$validUntil = $validUntil ?? '';
$publicUrl = $publicUrl ?? '#';
?>
PRESUPUESTO <?= $quoteNumber ?>

<?php if ($clientName): ?>
Hola <?= $clientName ?>,
<?php endif; ?>

Te hemos preparado un presupuesto por un importe de <?= $total ?>.
<?php if ($validUntil): ?>
Válido hasta: <?= $validUntil ?>
<?php endif; ?>

Puedes ver los detalles y aceptar o rechazar el presupuesto en:
<?= $publicUrl ?>

--
<?= $brandName ?>
