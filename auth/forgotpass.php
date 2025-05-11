<?php
require __DIR__ . '/../vendor/autoload.php';
use Kreait\Firebase\Factory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailEx;
use PHPMailer\PHPMailer\OAuth;
use League\OAuth2\Client\Provider\Google;

$factory = (new Factory)
      ->withServiceAccount(__DIR__ . '/../ms-digitalplanner-firebase-adminsdk-fbsvc-dc1c731d47.json')
    ->withDatabaseUri('https://ms-digitalplanner-default-rtdb.firebaseio.com/');
$db   = $factory->createDatabase();
$step   = $_GET['step'] ?? 'email';
$uid    = $_GET['uid'] ?? '';
$error  = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'email') {
        $email = trim($_POST['email'] ?? '');

        $allUsers = $db->getReference('users')->getSnapshot()->getValue();
        $uid = null;
        if (is_array($allUsers)) {
            foreach ($allUsers as $key => $user) {
                if (!empty($user['email']) && $user['email'] === $email) {
                    $uid = $key;
                    break;
                }
            }
        }

        if (!$uid) {
            $error = 'Email not found.';
        } else {
            $otp     = random_int(100000, 999999);
            $expires = time() + 600;
            $db->getReference("passwordResets/{$uid}")->set([
                'otp'       => (string)$otp,
                'expiresAt' => $expires,
            ]);

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->Port       = 587;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->SMTPAuth   = true;
                $mail->AuthType   = 'XOAUTH2';

                $provider = new Google([
                    'clientId'     => '530491108453-7kpslds8aj5nencleap23m3mic6c2gt0.apps.googleusercontent.com',
                    'clientSecret' => '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',
                ]);

                $mail->setOAuth(new OAuth([
                    'provider'     => $provider,
                    'clientId'     => '530491108453-7kpslds8aj5nencleap23m3mic6c2gt0.apps.googleusercontent.com',
                    'clientSecret' => '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',
                    'refreshToken' => '1//044R_UxD6FygCCgYIARAAGAQSNwF-L9Ir6nzrlEevkPrykwUFQv3SKk14TJBvAFoA5LXC2BG8Mez9jvV7v5XEd4isWR6IJtuAm9A',
                    'userName'     => 'rosswellacabo2004@gmail.com',
                ]));

                $mail->setFrom('orbitaskplanner@gmail.com', 'Orbitask Digital Planner');
                $mail->addAddress($email);
                $mail->Subject = 'Password Reset Code';
                $mail->Body    = "Your OTP code is: {$otp}. It expires in 10 minutes.";

                $mail->send();
                $status = "OTP sent successfully to {$email}.";
            } catch (MailEx $e) {
                $error = 'Failed to send OTP: ' . $e->getMessage();
            }

            if ($status && !$error) {
                header("Location: ?step=otp&uid={$uid}", true, 303);
                exit;
            }
        }
    } elseif ($step === 'otp') {
        $entered = trim($_POST['otp'] ?? '');
        $ref     = $db->getReference("passwordResets/{$uid}")->getSnapshot()->getValue();
        if (!$ref) {
            $error = 'No reset request found.';
        } elseif (time() > $ref['expiresAt']) {
            $error = 'OTP expired. Please try again.';
        } elseif ($entered !== $ref['otp']) {
            $error = 'Incorrect code.';
        } else {
            $db->getReference("passwordResets/{$uid}")->remove();
            header("Location: ?step=changepass&uid={$uid}", true, 303);
            exit;
        }
    } elseif ($step === 'changepass') {
        $p1 = $_POST['password'] ?? '';
        $p2 = $_POST['confirm']  ?? '';
        if (!$p1 || $p1 !== $p2) {
          $error = 'Passwords must match.';
      } else {
          $auth = $factory->createAuth();
          try {
              $auth->changeUserPassword($uid, $p1);
          } catch (AuthException $e) {
              $error = 'Could not update password: ' . $e->getMessage();
          }
      
          if (!$error) {
              $db->getReference("users/{$uid}/password")->set($p1);
              header('Location: login.php?reset=success', true, 303);
              exit;
          }
      }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="../assets/css/fpass.css">
    <link
      href="https://fonts.googleapis.com/css2?family=Afacad:wght@400;600;700&family=Poppins:wght@400;500&display=swap"
      rel="stylesheet"
    />
    <script src="https://kit.fontawesome.com/6dd2d34b20.js" crossorigin="anonymous"></script>
    <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-database-compat.js"></script>
    <script src="../conn/firebase-config.js"></script>
    <script src="../conn/auth.js"></script>
    <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
    <title>Orbitask - Forgout your password?</title>
  </head>
  <body>
    <div class="container">
      <a href="login.php" class="back"><i class="fa-solid fa-less-than"></i><span>Back</span></a>
      <div class="form-box">
        <img src="../assets/img/pics/logo.png" alt="Logo" class="logo" />
      
        <?php if ($step === 'email'): ?>
        <h2>Forgot Password?</h2>
        <p>Enter your email below to recover your account.</p>
        <?php if ($status): ?>
          <div class="status"><?= htmlspecialchars($status) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
          <label>Email</label>
          <input type="email" name="email" required>
          <button type="submit">Send OTP</button>
        </form>

      <?php elseif ($step === 'otp'): ?>
        <h2>Enter OTP</h2>
        <p>Enter 6 digits OTP below to recover your account.</p>
        <?php if ($error): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
          <label>6‑digit code</label>
          <input type="text" name="otp" pattern="\d{6}" required>
          <button type="submit">Verify Code</button>
        </form>

      <?php elseif ($step === 'changepass'): ?>
        <h2>Change Password</h2>
        <p>Enter your new password make sure to take note of it.</p>
        <?php if ($error): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
          <label>New Password</label>
          <input type="password" name="password" required>
          <label>Confirm Password</label>
          <input type="password" name="confirm" required>
          <button type="submit">Set Password</button>
        </form>
      <?php endif; ?>
        <div class="divider"><hr class="line" /><span>Or</span><hr class="line" /></div>
        <button id="googleLoginBtn" class="google-button"><img src="../assets/img/icons/google-icon.png" alt="Google" />Sign in with your Google Account</button>
      </div>
    </div>
  </body>
</html>
