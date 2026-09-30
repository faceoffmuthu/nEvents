<?php
/** @var string $name */
/** @var string $verifyUrl */
/** @var int $ttlHours */
/** @var string $appName */
$safeName   = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeUrl    = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
$safeApp    = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your email</title>
</head>
<body style="margin:0;padding:0;background:#F6EFE4;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6EFE4;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#FFFDF9;border-radius:16px;overflow:hidden;border:1px solid #E7DCCB;">
          <tr>
            <td align="center" style="background:#F6EFE4;padding:24px 32px;border-bottom:1px solid #F2E9DC;"><img src="<?= htmlspecialchars(rtrim($_ENV['APP_URL'] ?? '', '/') . '/images/brand/n-events-logo.png', ENT_QUOTES, 'UTF-8') ?>" width="70" height="72" alt="N Events" style="display:block;border:0;outline:none;width:70px;height:72px;"></td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <h1 style="margin:0 0 16px;font-size:20px;font-weight:800;color:#4B2E1E;">Welcome, <?= $safeName ?>!</h1>
              <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#5C4130;">
                Thanks for creating an account on <?= $safeApp ?>. Please confirm this is your email address to activate your account.
              </p>
              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                <tr>
                  <td style="border-radius:10px;background:#4B2E1E;">
                    <a href="<?= $safeUrl ?>" target="_blank" rel="noopener"
                       style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#FFFDF9;text-decoration:none;border-radius:10px;">
                      Verify Email Address
                    </a>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#7A5238;">
                This link expires in <?= (int) $ttlHours ?> hours. If the button doesn't work, copy and paste this URL into your browser:
              </p>
              <p style="margin:0 0 24px;font-size:12px;line-height:1.6;word-break:break-all;">
                <a href="<?= $safeUrl ?>" style="color:#4B2E1E;"><?= $safeUrl ?></a>
              </p>
              <hr style="border:none;border-top:1px solid #E7DCCB;margin:0 0 16px;">
              <p style="margin:0;font-size:12px;line-height:1.6;color:#7A5238;">
                If you did not create this account, you can safely ignore this email — no account will be activated without verification.
              </p>
            </td>
          </tr>
        </table>
        <p style="margin:16px 0 0;font-size:12px;color:#7A5238;">&copy; <?= date('Y') ?> <?= $safeApp ?>. Proudly built in Tamil Nadu.</p>
      </td>
    </tr>
  </table>
</body>
</html>
