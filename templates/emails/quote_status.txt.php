<?php
$senderName = $senderName ?? 'Tu cliente';
$senderEmail = $senderEmail ?? '';
$platformName = $platformName ?? 'Moni';
$quoteNumber = $quoteNumber ?? '';
$clientName = $clientName ?? '';
$statusLabel = $statusLabel ?? '';
$statusMessage = $statusMessage ?? '';
$publicUrl = $publicUrl ?? '#';
$rejectionReason = $rejectionReason ?? '';
$actedAt = $actedAt ?? '';
$appUrl = $appUrl ?? '#';
?>
Actualización de presupuesto

<?= $statusLabel ?>: <?= $quoteNumber ?>
<?= $statusMessage ?>

Cliente: <?= $clientName ?: 'Cliente' ?>
Fecha: <?= $actedAt ?>

<?php if ($rejectionReason !== ''): ?>
Motivo indicado por el cliente:
<?= $rejectionReason ?>

<?php endif; ?>
Ver presupuesto:
<?= $publicUrl ?>

<?php if ($senderEmail): ?>
Correo de contacto configurado:
<?= $senderEmail ?>

<?php endif; ?>
<?= $platformName ?>:
<?= $appUrl ?>
