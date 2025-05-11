
<?php
ob_start();
session_start();

if (!isset($_SESSION["uid"])) {
    header("Location: ../auth/login.php");
    exit();
}

$currentUserEmail = $_SESSION['email'] ?? '';
$uid = $_SESSION['uid'];

require __DIR__ . '/../vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
require __DIR__ . '/../includes/image_cache.php';

$factory = (new Factory)
    ->withServiceAccount(__DIR__ . '/../ms-digitalplanner-firebase-adminsdk-fbsvc-dc1c731d47.json')
    ->withDatabaseUri('https://ms-digitalplanner-default-rtdb.firebaseio.com/');

$auth = $factory->createAuth();
$database = $factory->createDatabase();
$userSnapshot = $database->getReference("users/{$uid}")->getValue() ?? [];

$rawPhotoURL      = $userSnapshot['photoURL'] ?? '';
$currentUserPhoto = getCachedProfileImage($userSnapshot['photoURL'] ?? '');

try {
    $userRecord   = $auth->getUser($uid);
    $providerIds  = array_map(fn($info) => $info->providerId, $userRecord->providerData);
    if (in_array('google.com', $providerIds, true) && !in_array('password', $providerIds, true)) {
        $authMethod = 'Google Authentication';
    } elseif (in_array('password', $providerIds, true) && !in_array('google.com', $providerIds, true)) {
        $authMethod = 'Email/Password';
    } else {
        $authMethod = 'Multiple Providers';
    }
} catch (Exception $e) {
    $authMethod = 'Unknown';
}

if (isset($_POST['save_profile'])) {
    $name    = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING)  ?? '';
    $phone   = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING) ?? '';
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING) ?? '';

    $database->getReference("users/{$uid}")->update([
        'name'      => $name,
        'phone'     => $phone,
        'address'   => $address,
        'updatedAt' => date('c'),
    ]);

    header("Location: settings.php");
    exit();
}

if (isset($_POST['save_security']) && $authMethod === 'Email/Password') {

  $photoURL = '../assets/img/pics/default-avatar.jpg';

  if (
      !empty($_FILES['photo']['tmp_name']) &&
      $_FILES['photo']['error'] === UPLOAD_ERR_OK
  ) {
      $uploadsDir = __DIR__ . '/../cache/';
      if (!is_dir($uploadsDir)) {
          mkdir($uploadsDir, 0755, true);
      }
      $filename   = $uid . '_' . time() . '_' . basename($_FILES['photo']['name']);
      $targetPath = $uploadsDir . $filename;

      if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
          $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                        ? 'https'
                        : 'http';
          $baseUrl  = $scheme . '://' . $_SERVER['HTTP_HOST'];
          $photoURL = $baseUrl . '/ms-projectmonitoring/cache/' . $filename;
      }
  }

  if (!empty($photoURL) && filter_var($photoURL, FILTER_VALIDATE_URL)) {
      $auth->updateUser($uid, ['photoUrl' => $photoURL]);
      $database
          ->getReference("users/{$uid}")
          ->update(['photoURL' => $photoURL]);
  }

  $oldPassword     = $_POST['old_password']     ?? '';
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $storedPassword = $userSnapshot['password'] ?? '';
    if ($oldPassword === '') {
        $errorMessage = 'Please enter your old password.';
    } elseif ($oldPassword !== $storedPassword) {
        $errorMessage = 'Old password does not match.';
    } elseif ($newPassword !== '' && $newPassword === $confirmPassword) {
        $auth->updateUser($uid, ['password' => $newPassword]);
        $database->getReference("users/{$uid}")->update(['password' => $newPassword]);
        header("Location: settings.php");
        exit();
    } else {
        $errorMessage = 'New passwords do not match.';
    }

  header("Location: settings.php");
  exit();
}
$name             = htmlspecialchars($userSnapshot['name']     ?? '');
$email            = htmlspecialchars($userSnapshot['email']    ?? $currentUserEmail);
$phone            = htmlspecialchars($userSnapshot['phone']    ?? '');
$address          = htmlspecialchars($userSnapshot['address']  ?? '');
$currentUserPhoto = htmlspecialchars($userSnapshot['photoURL'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/settings.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Text:wght@400;500;700">
  <script src="../assets/js/togglesidebar.js"></script>
  <script src="../assets/js/main.js"></script>
  <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
  <title>Orbitask - Settings</title>
</head>
<body>

<div class="navbar">
    <div class="navbar-left">
      <button class="hamburger" onclick="toggleSidebar()">☰</button>
      <a href="dashboard.php">
            <img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
            <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2"> </a>
            <a href="#">> Settings</a>
    </div>
    <div class="navbar-right">
      <div class="profile-dropdown">
      <img
      src="<?= htmlspecialchars($currentUserPhoto) ?>"
        class="profile-pic"
        onclick="toggleProfileDropdown()"
        onerror="this.onerror=null; this.src='../assets/img/pics/default-avatar.jpg';"
      />
        <div id="profileDropdownMenu" class="profile-dropdown-menu">
          <a href="settings.php">
            <img src="../assets/img/icons/settingsicon.png" alt="Settings" class="profile-dropdown-icon">
            Settings
          </a>
          <a href="../auth/logout.php">Logout</a>
        </div>
      </div>
    </div>
  </div>

  <div class="sidebar">
<div class="sidebar-logo">
<img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
    <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2">
  </div>
    <a href="dashboard.php"><img src="../assets/img/icons/homeicon.png" alt="" class="sidebar-icon">Home</a>
    <a href="calendar.php"><img src="../assets//img/icons/calendaricon.png" alt="" class="sidebar-icon">Calendar</a>
    <a href="archive.php"><img src="../assets//img/icons/archive-icon.png" alt="" class="sidebar-icon">Archive</a>
  </div>
  <div class="settings-container">
  <?php if ($authMethod === 'Email/Password'): ?>
    <div class="settings-card email-pass-card" style="position:relative;">
      <div class="settings-header">
        <div class="avatar" style="margin:0 auto 20px;">
          <img src="<?= htmlspecialchars($currentUserPhoto) ?>?v=<?= time() ?>" class="avatar-img"  onerror="this.onerror=null; this.src='../assets/img/pics/default-avatar.jpg';">
        </div>
        <h2>Your Profile</h2>
        <p><strong>Auth Method:</strong> <?= $authMethod ?></p>

        <div class="options-menu" style="position:absolute; top:20px; right:20px;">
          <button id="optionsBtn" class="options-btn">&#x22EE;</button>
          <div id="optionsDropdown" class="options-dropdown hidden">
            <button onclick="openPhotoPopup()">Change Photo</button>
            <button onclick="openPasswordPopup()">Change Password</button>
          </div>
        </div>
      </div>
      <form method="post" id="profileForm">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required value="<?= htmlspecialchars($name) ?>">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" readonly value="<?= htmlspecialchars($email) ?>">

        <label for="phone">Phone</label>
        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($phone) ?>">

        <label for="address">Address</label>
        <input type="text" id="address" name="address" value="<?= htmlspecialchars($address) ?>">

        <button name="save_profile" class="btn-primary" type="submit">Save Profile</button>
      </form>
    </div>
</div>

<div id="photoPopup" class="popup hidden">
  <div class="popup-content">
    <h3>Change Profile Photo</h3>
    <form method="post" enctype="multipart/form-data">
      <input type="file" id="photoInput" name="photo" accept="image/*" required>
      <div class="button-group">
        <button type="button" onclick="closePhotoPopup()">Cancel</button>
        <button name="save_security" type="submit">Upload</button>
      </div>
    </form>
  </div>
</div>

<div id="passwordPopup" class="popup hidden">
  <div class="popup-content">
    <h3>Change Password</h3>
    <form method="post">
      <label for="old_password">Enter Old Password</label>
      <input type="password" id="old_password" name="old_password" required>

      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" required>

      <label for="confirm_password">Confirm Password</label>
      <input type="password" id="confirm_password" name="confirm_password" required>

      <div class="button-group">
        <button type="button" onclick="closePasswordPopup()">Cancel</button>
        <button name="save_security" type="submit">Update</button>
      </div>
    </form>
  </div>
</div>

    <?php else: ?>
      <div class="settings-card">
        <div class="settings-header">
          <div class="avatar">
            <img src="<?=  htmlspecialchars($currentUserPhoto) ?>" alt="Avatar" class="avatar-img">
          </div>
          <h2>Your Profile</h2>
          <p><strong>Auth Method:</strong> <?= $authMethod ?></p>
        </div>
        <form method="post" id="settingsForm">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" required value="<?= $name ?>">

          <label for="email">Email</label>
          <input type="email" id="email" name="email" readonly value="<?= $email ?>">

          <label for="phone">Phone</label>
          <input type="text" id="phone" name="phone" value="<?= $phone ?>">

          <label for="address">Address</label>
          <input type="text" id="address" name="address" value="<?= $address ?>">

          <button name="save_profile" class="btn-primary" type="submit">Save Changes</button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <script>
 document.getElementById('optionsBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('optionsDropdown').classList.toggle('hidden');
  });
  window.addEventListener('click', function() {
    document.getElementById('optionsDropdown').classList.add('hidden');
  });

  function openPhotoPopup() {
    document.getElementById('photoPopup').classList.remove('hidden');
    document.getElementById('optionsDropdown').classList.add('hidden');
  }
  function closePhotoPopup() {
    document.getElementById('photoPopup').classList.add('hidden');
  }

  function openPasswordPopup() {
    document.getElementById('passwordPopup').classList.remove('hidden');
    document.getElementById('optionsDropdown').classList.add('hidden');
  }
  function closePasswordPopup() {
    document.getElementById('passwordPopup').classList.add('hidden');
  }
  function toggleProfileDropdown() {
  const menu = document.getElementById('profileDropdownMenu');
  menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

  </script>
</body>
<footer class="footer">
  <div class="footer-content">
    <div class="footer-links">
      <a href="dashboard.php">Home</a>
      <a href="calendar.php">Calendar</a>
      <a href="archive.php">Archive</a>
      <a href="settings.php">Settings</a>
    </div>
    <div class="footer-copyright">
      &copy; <?= date('Y') ?> MS Project Monitoring. All rights reserved.
    </div>
  </div>
</footer>
</html>