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
$plansRef = "users/{$uid}/plans/";
$myPlansRaw = $database->getReference($plansRef)->getValue() ?? [];
$myPlans = [];
foreach ($myPlansRaw as $planId => $plan) {
    if (!empty($plan['title']) && !empty($plan['date'])) {
        $plan['plan_id'] = $planId;
        $myPlans[] = $plan;
    }
}
$ignoredInvitations = $database->getReference("invitations/{$myEmailKey}")
    ->orderByChild("ignored")
    ->equalTo(true)
    ->getValue();
    $bannerImgPath = '../assets/img/banners/';
    $currentUserPhoto = htmlspecialchars($userSnapshot['photoURL'] ?? '');
    $current = basename($_SERVER['PHP_SELF']);

require __DIR__ . '/../handlers/dashboard/create.php';
require __DIR__ . '/../handlers/plans/fetchplans.php';
require __DIR__ . '/../handlers/plans/fetchnc.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/create.css">
  <link rel="stylesheet" href="../assets/css/calendar.css">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Text:wght@400;500;700">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/togglesidebar.js"></script>
  <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
  <title>Orbitask - Archive</title>
<style>
  .no-archive-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 20px;
    font-style: italic;
    color: #888;
    min-height: 100px;
  }

  .no-archive-message i {
    font-size: 2em;
    margin-bottom: 10px;
  }
  .empty-state-icon {
  width: 64px;
  height: 64px;
  margin-bottom: 16px;
  opacity: 0.5;
}

</style>
</head>
<body>

<div class="navbar">
<div class="navbar-left">
  <button class="hamburger" onclick="toggleSidebar()">☰</button>
  <a href="dashboard.php">
            <img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
            <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2"> </a>
            <a href="#">> Archived Invitations</a>
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
    Home
  </a>
  <a href="calendar.php"
     class="<?= $current === 'calendar.php' ? 'active' : '' ?>">
    <img src="../assets/img/icons/calendaricon.png"
         alt="" class="sidebar-icon">
    Calendar
  </a>
  <a href="archive.php"
     class="<?= $current === 'archive.php' ? 'active' : '' ?>">
    <img src="../assets/img/icons/archive-icon.png"
         alt="" class="sidebar-icon">
    Archive
  </a>
</div>

<div class="main-content">
  <div class="card-container">
    <?php if (!empty($ignoredInvitations)): ?>
        <?php
            foreach ($ignoredInvitations as $inviteKey => $invitation):
                $planId   = $invitation['plan_id'] ?? '';
                $ownerUid = $invitation['owner'] ?? '';
                $status   = $invitation['status'] ?? 'In Progress';

                $planRef = "users/{$ownerUid}/plans/{$planId}";
                $planData = $database->getReference($planRef)->getValue();
                $title = $planData['title'] ?? 'Untitled Plan';
                $banner  = $plan['banner'] ?? '';
                $styleAttr = '';
                if (strpos($banner, '#') === 0) {
                  $styleAttr = "background-color: {$banner};";
                } elseif ($banner) {
                  $url = $bannerImgPath . htmlspecialchars($banner);
                  $styleAttr = "background-image: url('{$url}'); background-size: cover; background-position: center;";
                }
            ?>

        <div class="card">
        <div class="plan-card-banner" style="<?= $styleAttr ?>"></div>

          <h3><?= htmlspecialchars($title) ?></h3>
          <p><strong>Status:</strong> <?= htmlspecialchars($status) ?></p>
          <p><strong>Creator:</strong> <?= htmlspecialchars($ownerUid) ?></p>
          <div class="card-buttons">
            <form method="POST" action="archive.php">
              <input type="hidden" name="invite_key" value="<?= htmlspecialchars($inviteKey) ?>">
              <input type="hidden" name="plan_id"    value="<?= htmlspecialchars($planId) ?>">
              <input type="hidden" name="owner_uid"  value="<?= htmlspecialchars($ownerUid) ?>">
              <input type="hidden" name="invited_role" value="<?= htmlspecialchars($role) ?>">
              <button type="submit" name="accept_invite" class="btn-accept">Accept</button>
              <button type="submit" name="ignore_invite" class="btn-ignore">Ignore</button>
            </form>
          </div>

      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-archive-message">
      <img src="../assets/img/icons/archive-pic.png" alt="" class="empty-state-icon">
  <p>No items in the archive.</p>
</div>

    <?php endif; ?>
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
</body>
</html>

