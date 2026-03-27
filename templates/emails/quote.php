<?php
// Variables: $brandName, $appUrl, $quoteNumber, $clientName, $total, $validUntil, $publicUrl, $senderName, $senderEmail, $platformName
$brandName = $brandName ?? 'Moni';
$appUrl = $appUrl ?? '#';
$quoteNumber = $quoteNumber ?? '';
$clientName = $clientName ?? '';
$total = $total ?? '';
$validUntil = $validUntil ?? '';
$publicUrl = $publicUrl ?? '#';
$senderName = $senderName ?? $brandName;
$senderEmail = $senderEmail ?? '';
$platformName = $platformName ?? 'Moni';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Presupuesto <?= htmlspecialchars($quoteNumber) ?></title>
</head>
<body style="margin:0;padding:0;background:#f6f9fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6f9fb;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:14px;box-shadow:0 6px 20px rgba(2,12,27,0.06);overflow:hidden;border:1px solid #eef2f7;">
          <!-- Header -->
          <tr>
            <td style="padding:18px 22px;border-bottom:1px solid #eef2f7;background:#ffffff;">
              <div style="font-weight:800;font-size:18px;color:#0f172a;letter-spacing:0.1px;"><?= htmlspecialchars($senderName) ?></div>
              <div style="margin-top:4px;font-size:12px;color:#64748b;">Te ha enviado un presupuesto</div>
            </td>
          </tr>
          <!-- Greeting -->
          <tr>
            <td style="padding:22px 22px 6px 22px;">
              <h1 style="margin:0 0 8px 0;font-size:20px;line-height:1.3;color:#0f172a;"><?= htmlspecialchars($senderName) ?> te ha enviado un presupuesto</h1>
              <?php if ($clientName): ?>
                <p style="margin:0 0 12px 0;color:#475569;font-size:14px;">Hola <?= htmlspecialchars($clientName) ?>,</p>
              <?php endif; ?>
              <p style="margin:0 0 14px 0;color:#334155;font-size:14px;">Puedes revisarlo y aceptarlo o rechazarlo directamente desde el enlace de abajo.</p>
            </td>
          </tr>
          <!-- Summary card -->
          <tr>
            <td style="padding:0 22px 14px 22px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border-radius:10px;border:1px solid #eef2f7;">
                <tr>
                  <td style="padding:14px 16px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                      <tr>
                        <td style="font-size:13px;color:#64748b;padding-bottom:4px;">Presupuesto</td>
                        <td style="font-size:13px;color:#64748b;text-align:right;padding-bottom:4px;">Importe total</td>
                      </tr>
                      <tr>
                        <td style="font-size:16px;font-weight:700;color:#0f172a;"><?= htmlspecialchars($quoteNumber) ?></td>
                        <td style="font-size:16px;font-weight:700;color:#0FA3B1;text-align:right;"><?= htmlspecialchars($total) ?></td>
                      </tr>
                      <?php if ($validUntil): ?>
                      <tr>
                        <td colspan="2" style="padding-top:8px;font-size:12px;color:#64748b;">Válido hasta: <strong><?= htmlspecialchars($validUntil) ?></strong></td>
                      </tr>
                      <?php endif; ?>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- CTA -->
          <tr>
            <td style="padding:6px 22px 18px 22px;text-align:center;">
              <a href="<?= htmlspecialchars($publicUrl) ?>" style="display:inline-block;padding:12px 28px;background:#0FA3B1;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:700;font-size:15px;">Ver presupuesto</a>
            </td>
          </tr>
          <!-- Note -->
          <tr>
            <td style="padding:0 22px 18px 22px;">
              <p style="margin:0;color:#64748b;font-size:13px;">Desde el enlace podrás ver todos los detalles del presupuesto y responder directamente.</p>
              <?php if ($senderEmail): ?>
                <p style="margin:10px 0 0;color:#64748b;font-size:13px;">Si necesitas responder por correo, puedes escribir a <a href="mailto:<?= htmlspecialchars($senderEmail) ?>" style="color:#0FA3B1;text-decoration:none;"><?= htmlspecialchars($senderEmail) ?></a>.</p>
              <?php endif; ?>
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="padding:14px 22px;border-top:1px solid #eef2f7;background:#f9fbfd;color:#64748b;font-size:12px;">
              <div>Enviado por <?= htmlspecialchars($senderName) ?> con ayuda de <a href="<?= htmlspecialchars($appUrl) ?>" style="color:#0FA3B1;text-decoration:none;"><?= htmlspecialchars($platformName) ?></a></div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
