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

$factory = (new Factory)
    ->withServiceAccount(__DIR__ . '/../ms-digitalplanner-firebase-adminsdk-fbsvc-dc1c731d47.json')
    ->withDatabaseUri('https://ms-digitalplanner-default-rtdb.firebaseio.com/');
$database = $factory->createDatabase();

$plansRef = "users/{$uid}/plans/";

function sanitizeEmail(string $email): string {
    return str_replace(['.', '#', '$', '[', ']'], '_', $email);
}
$userSnapshot = $database->getReference("users/{$uid}")->getValue();
$photoURL = '../assets/img/pics/default-avatar.jpg'; 

if ($userSnapshot && !empty($userSnapshot['photoURL'])) {
    require __DIR__ . '/../includes/image_cache.php';
    $photoURL = getCachedProfileImage($userSnapshot['photoURL']);
}
$myEmailKey = sanitizeEmail($currentUserEmail);
require __DIR__ . '/../handlers/plans/vip.php';
require __DIR__ . '/../handlers/plans/fetchplans.php';
$currentUserPhoto = htmlspecialchars($userSnapshot['photoURL'] ?? '');

$plansByDate = [];
foreach ($myPlans as $plan) {
    if (!empty($plan['date'])) {
        $date = $plan['date'];
        if (!isset($plansByDate[$date])) {
            $plansByDate[$date] = [];
        }
        $plansByDate[$date][] = $plan;
    }
}

$year  = date("Y");
$month = date("m");
$numDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$current = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/calendar.css"> 
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Text:wght@400;500;700">
  <script>
    function showLoading() {
  document.getElementById("loading-overlay").style.display = "flex";
}

function hideLoading() {
  document.getElementById("loading-overlay").style.display = "none";
}
  </script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/togglesidebar.js"></script>
  <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
  <title>Orbitask - Calendar</title>
</head>
<body>
<div class="navbar">
<div class="navbar-left">
  <button class="hamburger" onclick="toggleSidebar()">☰</button>
  <a href="dashboard.php">
            <img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
            <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2"> </a>
            <a href="#">> Project & Tasks Calendar</a>
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
  
  </div>
  <div class="sidebar">
  <div class="sidebar-logo">
<img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
    <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2">
  </div>
  <a href="dashboard.php"
     class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
    <img src="../assets/img/icons/homeicon.png"
         alt="" class="sidebar-icon">
         <span class="sidebar-text">Home</span>
  </a>
  <a href="calendar.php"
     class="<?= $current === 'calendar.php' ? 'active' : '' ?>">
    <img src="../assets/img/icons/calendaricon.png"
         alt="" class="sidebar-icon">
         <span class="sidebar-text">Calendar</span>
  </a>
  <a href="archive.php"
     class="<?= $current === 'archive.php' ? 'active' : '' ?>">
    <img src="../assets/img/icons/archive-icon.png"
         alt="" class="sidebar-icon">
         <span class="sidebar-text">Archive</span>
  </a>
</div>

<div class="main-content">
  <div class="content-wrapper">
    <div class="calendar-section">
      <div id="calendar"></div>
    </div>
  </div>
</div>
<script>
function toggleProfileDropdown() {
  const menu = document.getElementById('profileDropdownMenu');
  menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

window.addEventListener('click', function(e) {
  const dropdown = document.querySelector('.profile-dropdown');
  const menu = document.getElementById('profileDropdownMenu');
  if (!dropdown.contains(e.target)) {
    menu.style.display = 'none';
  }
});
</script>
<script src="../assets/js/calendar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
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
