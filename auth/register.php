<?php
require __DIR__ . '/../vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\FirebaseException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailEx;
use PHPMailer\PHPMailer\OAuth;
use League\OAuth2\Client\Provider\Google;

$serviceAccountPath = __DIR__ . '/../ms-digitalplanner-firebase-adminsdk-fbsvc-dc1c731d47.json';
$databaseUri        = 'https://ms-digitalplanner-default-rtdb.firebaseio.com/';

$factory  = (new Factory)
    ->withServiceAccount($serviceAccountPath)
    ->withDatabaseUri($databaseUri);
$auth     = $factory->createAuth();
$database = $factory->createDatabase();

$step    = $_GET['step'] ?? 'email';
$error   = '';
$status  = '';

$name        = trim($_POST['name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phoneNumber = trim($_POST['phoneNumber'] ?? '');
$password    = $_POST['password'] ?? '';
$confirm     = $_POST['confirmPassword'] ?? '';
function emailToKey(string $email): string {
  return str_replace('.', ',', $email);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'email') {
        if (!$name || !$email || !$phoneNumber || !$password) {
            $error = 'All fields are required.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            if (strpos($phoneNumber, '+63') !== 0) {
                $phoneNumber = ltrim($phoneNumber, '0');
                $phoneNumber = '+63' . $phoneNumber;
            }
         
            $otp     = random_int(100000, 999999);
            $expires = time() + 600; 
            $email    = trim($_POST['email']);
            $emailKey = emailToKey($email); 
            
            $database
              ->getReference("registerVerifications/{$emailKey}")
              ->set([
                'otp'       => (string)$otp,
                'expiresAt' => $expires,
                'name'      => $name,
                'phone'     => $phoneNumber,
                'password'  => $password,
                'email'     => $email,         
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
                $mail->Subject = 'Your Registration Verification Code';
                $mail->Body    = "Your verification code is: {$otp}. It expires in 10 minutes.";
                $mail->send();

                $status = "Verification code sent to {$email}.";
            } catch (MailEx $e) {
                $error = 'Failed to send verification email: ' . $e->getMessage();
            }

            if ($status && !$error) {
                header("Location: register.php?step=otp&email={$email}", true, 303);
                exit;
            }
        }

    } elseif ($step === 'otp') {
        $entered = trim($_POST['otp'] ?? '');
        $email    = trim($_GET['email']);
        $emailKey = emailToKey($email);
        
        $ref = $database
          ->getReference("registerVerifications/{$emailKey}")
          ->getSnapshot()
          ->getValue();
        

        if (!$ref) {
            $error = 'No registration request found.';
        } elseif (time() > $ref['expiresAt']) {
            $error = 'Verification code expired. Please start over.';
        } elseif ($entered !== $ref['otp']) {
            $error = 'Incorrect code.';
        } else {
          $database->getReference("registerVerifications/{$emailKey}")->remove();

            try {
                $createdUser = $auth->createUser([
                    'email'       => $email,
                    'password'    => $ref['password'],
                    'displayName' => $ref['name'],
                    'phoneNumber' => $ref['phone'],
                ]);
                $uid = $createdUser->uid;
                $database
                    ->getReference("users/{$uid}")
                    ->set([
                        'uid'       => $uid,
                        'name'      => $ref['name'],
                        'email'     => $email,
                        'password'  => $ref['password'],
                        'phone'     => $ref['phone'],
                        'address'   => '',
                        'photoURL'  => '',
                        'createdAt' => time(),
                    ]);
                header('Location: login.php?registered=1', true, 303);
                exit;

            } catch (AuthException $e) {
                $error = 'Registration failed: ' . $e->getMessage();
            } catch (FirebaseException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
  <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-database-compat.js"></script>
  <script src="../conn/firebase-config.js"></script>
  <script src="../conn/auth.js"></script>
  <title>Orbitask - Register an account</title>
  <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body>
  <main class="container">
    <section class="form-container">
      <header class="form-header">
        <img src="../assets/img/pics/logo.png" alt="Logo" class="logo">
        <h2>Sign Up an account</h2>
      </header>

      <?php if ($step === 'email'): ?>
        <?php if ($status): ?><div class="status"><?=$status?></div><?php endif; ?>
        <?php if ($error ): ?><div class="error"><?=$error?></div><?php endif; ?>
        <form method="POST" class="form">
          <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?=$name?>" placeholder="John Doe" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?=$email?>" placeholder="you@example.com" required>
            </div>
            <div class="form-group phone-group">
              <label for="phoneNumber">Phone Number</label>
              <input type="text" id="phoneNumber" name="phoneNumber"
                     value="<?=$phoneNumber?:'+63'?>"
                     placeholder="+63 9123456789" required>
            </div>
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <div class="password-group">
              <input type="password" id="password" name="password" placeholder="Password" required>
              <i class="fas fa-eye-slash toggle-password" onclick="togglePassword('password', this)"></i>
            </div>
          </div>
          <div class="form-group">
            <label for="confirmPassword">Confirm Password</label>
            <div class="password-group">
              <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Password" required>
              <i class="fas fa-eye-slash toggle-password" onclick="togglePassword('confirmPassword', this)"></i>
            </div>
          </div>
          <div class="form-options">
            <label><input type="checkbox" id="terms" name="terms" required> Agree to <a href="#">Terms and Conditions</a></label>
          </div>
          <button type="submit">Send Verification Code</button>
        </form>

      <?php else: ?>
        <p>We sent a 6 digit code to <strong><?=$email?></strong>.</p>
        <?php if ($error): ?><div class="error"><?=$error?></div><?php endif; ?>
        <form method="POST" class="form">
          <label for="otp">Verification Code</label>
          <input type="text" id="otp" name="otp" pattern="\d{6}" required>
          <button type="submit">Verify & Complete Registration</button>
        </form>
      <?php endif; ?>

      <div class="signup-link">
        Already have an account? <a href="login.php">Login</a>
      </div>
      <div class="divider">Or continue with</div>
      <button class="social-btn social-btn--google" id="googleLoginBtn">
        <img src="../assets/img/icons/google-icon.png" alt="Google"> Sign in with your Google Account
      </button>
    </section>
  </main>

  <script src="https://kit.fontawesome.com/6dd2d34b20.js" crossorigin="anonymous"></script>
  <script>
    function togglePassword(id, icon) {
      const input = document.getElementById(id);
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('fa-eye');
      icon.classList.toggle('fa-eye-slash');
    }
  </script>
</body>
</html>
