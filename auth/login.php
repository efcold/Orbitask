<?php
session_start();
if (isset($_SESSION['uid'])) {
    header('Location: dashboard.php');
    exit;
}
require __DIR__ . '/../vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\AuthException;
$serviceAccountPath = __DIR__ . '/../ms-digitalplanner-firebase-adminsdk-fbsvc-dc1c731d47.json';
$apiKey = 'AIzaSyD4wBVFweje8DEpj1wW0D1JHyvaWGOk76M';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        $endpoint = "https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key={$apiKey}";
        $payload  = json_encode([
            'email'             => $email,
            'password'          => $password,
            'returnSecureToken' => true,
        ]);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            $error = 'Could not contact Firebase Auth: ' . htmlspecialchars($curlErr);
        } else {
            $data = json_decode($response, true);
            if (isset($data['error'])) {
                $msg   = $data['error']['message'] ?? 'Unknown error';
                $error = 'Login failed: ' . htmlspecialchars($msg);
            } elseif ($httpCode !== 200) {
                $error = 'Unexpected HTTP status: ' . $httpCode;
            } else {
                $idToken = $data['idToken'];
                try {
                    $factory       = (new Factory)->withServiceAccount($serviceAccountPath);
                    $auth          = $factory->createAuth();
                    $verifiedToken = $auth->verifyIdToken($idToken);
                    $uid           = $verifiedToken->claims()->get('sub');
                    $_SESSION['uid'] = $uid;
                    $userRecord = $auth->getUser($uid);
                    $_SESSION['email']      = $userRecord->email;
                    $_SESSION['name']       = $userRecord->displayName;
                    $_SESSION['photo_url']  = $userRecord->photoUrl;
                    header('Location: ../directives/dashboard.php');
                    exit;
                } catch (AuthException $e) {
                    $error = 'Token verification failed: ' . htmlspecialchars($e->getMessage());
                }
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
  <link rel="stylesheet" href="../assets/css/login.css">
  <link href="https://fonts.googleapis.com/css2?family=Afacad:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/6dd2d34b20.js" crossorigin="anonymous"></script>
  <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.11.0/firebase-database-compat.js"></script>
    <script src="../conn/firebase-config.js"></script>
    <script src="../conn/auth.js"></script>
    <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
    <title>Orbitask - Login your account</title>
</head>
<body>
  <main class="login-container">
    <section class="login-form">
      <img src="../assets/img/pics/logo.png" alt="Logo" class="logo">
      <h2>Login your account</h2>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label for="email">Email</label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            placeholder="you@example.com" 
            required
            value="<?= htmlspecialchars($email) ?>"
          >
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input 
            type="password" 
            id="password" 
            name="password" 
            placeholder="Password" 
            required
          >
        </div>
        <div class="form-options">
          <label>
            <input type="checkbox" name="rememberMe">
            Remember Me
          </label>
          <a class="fpass" href="forgotpass.php">Forgot Password?</a>
        </div>
        <button type="submit" class="btn-primary">Login</button>
      </form>
      <div class="signup-link">
        Don’t have an account? <a href="register.php">Sign up</a>
      </div>
      <div class="divider">
        <hr class="line"><span>Or continue with</span><hr class="line">
      </div>
      <button type="button"  id="googleLoginBtn" class="btn-google">
        <img src="../assets/img/icons/google-icon.png" alt="Google Logo">
        Sign in with your Google Account
      </button>
    </section>
  </main>
</body>
</html>
