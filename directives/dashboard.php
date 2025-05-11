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

$userSnapshot = $database->getReference("users/{$uid}")->getValue();
$photoURL = '../assets/img/pics/default-avatar.jpg'; 

if ($userSnapshot && !empty($userSnapshot['photoURL'])) {
    require __DIR__ . '/../includes/image_cache.php';
    $photoURL = getCachedProfileImage($userSnapshot['photoURL']);
}

$plansRef = "users/{$uid}/plans/";
$myPlansRaw = $database->getReference($plansRef)->getValue() ?? [];

function sanitizeEmail(string $email): string {
    return str_replace(['.', '#', '$', '[', ']'], '_', $email);
}
$myEmailKey = sanitizeEmail($currentUserEmail);

$myPlans = [];
foreach ($myPlansRaw as $planId => $plan) {
    if (!empty($plan['title']) && !empty($plan['date'])) {
        $plan['plan_id'] = $planId;
        $myPlans[] = $plan;
    }
}
$plansByDate = [];
foreach ($myPlans as $plan) {
    $date = $plan['date'];
    if (!isset($plansByDate[$date])) {
        $plansByDate[$date] = [];
    }
    $plansByDate[$date][] = $plan;
}

$year  = date("Y");
$month = date("m");
$numDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);

$firstDayOfMonth = strtotime("$year-$month-01");
$firstWeekday = date("w", $firstDayOfMonth);
  $bannerImgPath = '../assets/img/banners/';
  $currentUserPhoto = htmlspecialchars($userSnapshot['photoURL'] ?? '');
  $current = basename($_SERVER['PHP_SELF']);
require __DIR__ . '/../handlers/dashboard/create.php';
require __DIR__ . '/../handlers/plans/fetchplans.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/create.css">
  <link rel="stylesheet" href="../assets/css/calendar.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Text:wght@400;500;700">
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/togglesidebar.js"></script>
  <script src="../assets/js/toggletabs.js"></script>
  <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
  <title>Orbitask</title>

</head>
<body>

<div class="navbar">
<div class="navbar-left">
  <button class="hamburger" onclick="toggleSidebar()">☰</button>
  <a href="dashboard.php">
  <img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
  <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2"> </a>
  <a href="dashboard.php">> List of Projects</a>
</div>
<div class="navbar-right">
    <button class="create-plan-btn" onclick="openPopup()">
      <img src="../assets/img/icons/plusicon.png" alt="" class="plus-icon">
    </button>
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

<div class="navbar-content">
          <div class="navbar-tabs">
              <a href="#" class="tablinks active" onclick="openTab(event, 'Cards')">My Projects</a>
              <a href="#" class="tablinks" onclick="openTab(event, 'Invites')">Shared Projects</a>
          </div>
    <div> 

<div class="main-content">
  <div class="dashboard-container">

    <div class="cards-container">

      <div class="cards-container">
      <div id="Cards" class="tabcontent" style="display:block;">

        <div class="card-container">
          <?php if ($myPlans): foreach ($myPlans as $planId => $plan):
            if (empty($plan['title'])) continue;
            $creator = $currentUserEmail;
            $status = $plan['status'] ?? 'In Progress';
            $date = $plan['date'] ?? '—';
            $viewUrl = "viewplan.php?plan_id=" . urlencode($planId) . "&owner_uid=" . urlencode($uid);
            $banner    = $plan['banner']    ?? '';
            $styleAttr = '';
            if (strpos($banner, '#') === 0) {
       
              $styleAttr = "background-color: {$banner};";
            } elseif ($banner) {
        
              $url = $bannerImgPath . htmlspecialchars($banner);
              $styleAttr = "background-image: url('{$url}'); background-size: cover; background-position: center;";
            }
          ?>
             <div class="card plan-card" onclick="window.location.href='<?= $viewUrl ?>'">
             <div class="plan-card-banner" style="<?= $styleAttr ?>"></div>
             <div class="plan-card-content">
              <h3><?= htmlspecialchars($plan['title']) ?></h3>
              <p><strong>Status:</strong> <?= htmlspecialchars($status) ?></p>
            </div>
          </div>
          <?php endforeach; else: ?>
            <div class="empty-state">
            <img src="../assets/img/icons/empty-icon.png" alt="" class="empty-state-icon">
            <p>No plans available.</p>
          </div>
          <?php endif; ?>
        </div>
        </div>

        <div id="Invites" class="tabcontent" style="display:none;">

        <div class="card-container">
          <?php if ($invitedPlans): foreach ($invitedPlans as $plan):
            $creator = $plan['owner'];
            $status = $plan['status'] ?? 'In Progress';
            $date = $plan['date'] ?? '—';
            $isAccepted = !empty($plan['accepted']);
            $role = $plan['invited_role'] ?? ''; 
            $banner  = $plan['banner'] ?? '';

            if ($role === 'assistant admin') {
                $viewUrl = "assistantadmin.php?plan_id="
                        . urlencode($plan['plan_id'])
                        . "&owner_uid=" . urlencode($plan['owner']);
            } else {
                $viewUrl = "invites.php?plan_id="
                        . urlencode($plan['plan_id'])
                        . "&owner_uid=" . urlencode($plan['owner']);
            }
            $styleAttr = '';
            if (strpos($banner, '#') === 0) {
         
              $styleAttr = "background-color: {$banner};";
            } elseif ($banner) {
      
              $url = $bannerImgPath . htmlspecialchars($banner);
              $styleAttr = "background-image: url('{$url}'); background-size: cover; background-position: center;";
            }
            ?>
            <?php if ($isAccepted): ?>
              <div class="card" onclick="window.location.href='<?= $viewUrl ?>'">
              <div class="plan-card-banner" style="<?= $styleAttr ?>"></div>
              <div class="plan-card-content">
                <h3><?= htmlspecialchars($plan['title'] ?? 'Untitled') ?></h3>
                <p><strong>Status:</strong> <span><?= htmlspecialchars($status) ?></span></p>
                <p><strong>Creator:</strong> <span><?= htmlspecialchars($creator) ?></span></p>
              </div>
              </div>
            <?php else: ?>
              <div class="card plan-card">
                  <div class="plan-card-banner" style="<?= $styleAttr ?>"></div>
                  <div class="plan-card-content">
                <h3><?= htmlspecialchars($plan['title'] ?? 'Untitled') ?></h3>
                <p><strong>Status:</strong> <span><?= htmlspecialchars($status) ?></span></p>
                <p><strong>Creator:</strong> <span><?= htmlspecialchars($creator) ?></span></p>
                <div class="card-inner-buttons">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="invite_key" value="<?= htmlspecialchars($plan['invite_key']) ?>">
                  <input type="hidden" name="plan_id" value="<?= htmlspecialchars($plan['plan_id']) ?>">
                  <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($plan['owner']) ?>">
                  <input type="hidden" name="invited_role" value="<?= htmlspecialchars($role) ?>">
                  <button type="submit" name="accept_invite">Accept</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="invite_key" value="<?= htmlspecialchars($plan['invite_key']) ?>">
                  <button type="submit" name="ignore_invite">Ignore</button>
                </form>
            </div>
              </div>
              </div>
            <?php endif; ?>
          <?php endforeach; else: ?>
         <div class="empty-state">
          <img src="../assets/img/icons/empty-icon-inv.png" alt="" class="empty-state-icon">
          <p>No invitations available.</p>
        </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    </div>

  </div>
</div>
  
<div id="popupOverlay" class="hidden"></div>

<div id="popupForm" class="popup hidden">
  <h3>Create a New Project</h3>
  <form method="POST" onsubmit="syncTasksJson()">
    <div class="form-group plan-banner-group">
      <label>Banner:</label>
      <div class="plan-banner-options">
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <label class="plan-banner-option">
            <input type="radio" name="banner" value="banner<?= $i ?>.jpg" <?= $i === 1 ? 'checked' : '' ?>>
            <img src="<?= $bannerImgPath ?>banner<?= $i ?>.jpg" alt="Banner <?= $i ?>">
          </label>
        <?php endfor; ?>

        <label class="plan-banner-option color-picker-option">
          <input type="radio" name="banner" id="banner_custom_radio" value="custom">
          Custom Color:
          <input 
            type="color" 
            id="bannerColorPicker" 
            name="banner_color" 
            value="#FF5733" 
            disabled 
            title="Pick a custom color"
          >
        </label>
      </div>
    </div>

    <div class="form-group">
      <label for="planTitle">Title:</label>
      <input type="text" id="planTitle" name="title" required>
    </div>
    <div class="form-group">
      <label for="startDate">Start Date:</label>
      <input type="date" id="startDate" name="start_date" required>
    </div>
    <div class="form-group">
      <label for="endDate">End Date:</label>
      <input type="date" id="endDate" name="end_date" required>
    </div>

    <div class="form-group task-group">
      <label>Tasks:</label>
      <div class="task-inputs">
        <input type="text" id="newTask" placeholder="Task name">
        <input type="date" id="taskDueDate">
        <input type="time" id="taskDueTime">
        <button type="button" onclick="addTask()">Add Task</button>
      </div>
      <ul id="taskList"></ul>
      <input type="hidden" name="tasks_json" id="tasks_json" value="">
    </div>

    <div class="button-group">
      <button type="submit" name="create_plan" class="btn btn-primary">Create Project</button>
      <button type="button" onclick="closePopup()" class="btn btn-secondary">Close</button>
    </div>
  </form>
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
  document.addEventListener('DOMContentLoaded', function() {
    const customRadio = document.getElementById('banner_custom_radio');
    const colorInput  = document.getElementById('bannerColorPicker');
    document.querySelectorAll('input[name="banner"]').forEach(radio => {
      radio.addEventListener('change', () => {
        colorInput.disabled = !customRadio.checked;
      });
    });
  });
  
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

