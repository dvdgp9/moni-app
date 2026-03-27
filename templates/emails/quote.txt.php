<?php
// Variables: $brandName, $appUrl, $quoteNumber, $clientName, $total, $validUntil, $publicUrl, $senderName, $senderEmail, $platformName
$brandName = $brandName ?? 'Moni';
$quoteNumber = $quoteNumber ?? '';
$clientName = $clientName ?? '';
$total = $total ?? '';
$validUntil = $validUntil ?? '';
$publicUrl = $publicUrl ?? '#';
$senderName = $senderName ?? $brandName;
$senderEmail = $senderEmail ?? '';
$platformName = $platformName ?? 'Moni';
?>
PRESUPUESTO <?= $quoteNumber ?>

<?php if ($clientName): ?>
Hola <?= $clientName ?>,
<?php endif; ?>

<?= $senderName ?> te ha enviado un presupuesto por un importe de <?= $total ?>.
<?php if ($validUntil): ?>
Válido hasta: <?= $validUntil ?>
<?php endif; ?>

Puedes ver los detalles y aceptar o rechazar el presupuesto en:
<?= $publicUrl ?>

<?php if ($senderEmail): ?>
Si necesitas responder por correo:
<?= $senderEmail ?>
<?php endif; ?>

--
Enviado por <?= $senderName ?> con ayuda de <?= $platformName ?>
