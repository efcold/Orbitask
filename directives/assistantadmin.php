<?php
ob_start();
session_start();

if (!isset($_SESSION['uid'])) {
    header('Location: ../auth/login.php');
    exit();
}
$currentUserEmail = $_SESSION['email'] ?? '';
$uid = $_SESSION['uid'];
$planId = $_GET['plan_id'] ?? null;
$ownerUid = $_GET['owner_uid'] ?? null;

require __DIR__ . '/../vendor/autoload.php';
use Kreait\Firebase\Factory;
$Parsedown = new \Parsedown();
$Parsedown->setSafeMode(true);
$factory = (new Factory)
    ->withServiceAccount(__DIR__ . '/../ms-digitalplanner-firebase-adminsdk-fbsvc-dc1c731d47.json')
    ->withDatabaseUri('https://ms-digitalplanner-default-rtdb.firebaseio.com/');
$database = $factory->createDatabase();
$plansRef = "users/{$ownerUid}/plans/{$planId}";
function sanitizeEmail(string $email): string {
  return str_replace(['.', '#', '$', '[', ']'], '_', $email);
}
$userSnapshot = $database->getReference("users/{$uid}")->getValue();
$photoURL = '../assets/img/pics/default-avatar.jpg'; // default if no photo

if ($userSnapshot && !empty($userSnapshot['photoURL'])) {
    require __DIR__ . '/../includes/image_cache.php';
    $photoURL = getCachedProfileImage($userSnapshot['photoURL']);
}

$myEmailKey = sanitizeEmail($currentUserEmail);
$plan = $database->getReference($plansRef)->getValue();
$isOwner = isset($plan['creator']) && ($plan['creator'] === $uid);
$isAssistantAdmin = (!empty($plan['invited_role']) && $plan['invited_role'] === 'assistant admin');
$tasksWithUploads = [];
if (!empty($plan['tasks']) && is_array($plan['tasks'])) {
    foreach ($plan['tasks'] as $taskId => $task) {
        $uploadsRef = "{$plansRef}/tasks/{$taskId}/uploads";
        $uploadsRaw = $database->getReference($uploadsRef)->getValue() ?? [];
        $grouped = [];
        foreach ($uploadsRaw as $uploaderKey => $entry) {
            // Convert key back to email
            $email = str_replace('_', '.', $uploaderKey);
            $items = [];
            // Add file entries
            if (!empty($entry['files']) && is_array($entry['files'])) {
                foreach ($entry['files'] as $fileUrl) {
                    $items[] = ['type' => 'file', 'url' => $fileUrl];
                }
            }
            // Add website URL entries
            if (!empty($entry['website_urls']) && is_array($entry['website_urls'])) {
                foreach ($entry['website_urls'] as $link) {
                    $items[] = ['type' => 'link', 'url' => $link];
                }
            }
            if ($items) {
                $grouped[$email] = $items;
            }
        }
        $task['uploads'] = $grouped;
        $tasksWithUploads[$taskId] = $task;
    }
}
$userTasks = [];
if (!empty($plan['tasks']) && is_array($plan['tasks'])) {
        foreach ($plan['tasks'] as $taskId => $task) {
          if (!empty($task['assigned_to'])
              && is_array($task['assigned_to'])
              && in_array($myEmailKey, $task['assigned_to'], true)
          ) {
              $uploads = $task['uploads'][$myEmailKey] ?? [];
              $task['user_uploads_files'] = array_values($uploads['files'] ?? []);
              $task['user_uploads_urls'] = array_values($uploads['website_urls'] ?? []);
      
              $task['files']        = $task['files']        ?? [];
              $task['website_urls'] = $task['website_urls'] ?? [];
      
              $userTasks[$taskId] = $task;
          }
      }
}

$accepted = [];
if (!empty($plan['invited']) && is_array($plan['invited'])) {
    foreach ($plan['invited'] as $emailKey => $status) {
        if ($status === 'accepted') {
            $accepted[] = $emailKey;
        }
    }
}
$plan['tasks'] = $tasksWithUploads;

$allowedToComment = $isOwner || in_array($myEmailKey, $accepted);

$userProfileRef = $database->getReference("users/{$uid}");
$userProfile = $userProfileRef->getValue() ?? [];
$currentUserName  = $userProfile['name'] ?? $currentUserEmail;
$currentUserPhoto = htmlspecialchars($userSnapshot['photoURL'] ?? '');
$invitationsRaw = $database
  ->getReference("invitations/{$myEmailKey}")
  ->getValue() ?: [];


  // 1) Build a map emailKey=>trueRole by looking in invitations/{emailKey} for this plan
  $inviteeRoles = [];
  foreach (array_keys($plan['invited'] ?? []) as $emailKey) {
      $records = $database
        ->getReference("invitations/{$emailKey}")
        ->getValue() ?: [];
      foreach ($records as $rec) {
          if (
            ($rec['plan_id'] ?? '') === $planId
            && ($rec['owner']   ?? '') === $ownerUid
          ) {
              $inviteeRoles[$emailKey] = $rec['role'] ?? '';
              break;
          }
      }
  }

  // 2) Grab the owner’s emailKey
  $ownerProfile   = $database->getReference("users/{$ownerUid}")->getValue() ?: [];
  $ownerEmailKey  = sanitizeEmail($ownerProfile['email'] ?? '');
  // ensure owner always shows as “Owner”
  $ownerList      = [$ownerEmailKey => 'Owner'];

  // 3) Bucket the others by real role
  $assistantAdmins = [];
  $collaborators   = [];
  foreach ($inviteeRoles as $emailKey => $trueRole) {
      if ($emailKey === $ownerEmailKey) {
          continue;                // skip owner
      }
      $normalized = strtolower($trueRole);
      if ($normalized === 'assistant admin') {
          $assistantAdmins[$emailKey] = 'Assistant Admin';
      } else {
          $collaborators[$emailKey]   = $trueRole;
      }
  }
  $bannerImgPath = '../assets/img/banners/';
  $banner     = $plan['banner'] ?? '';
  $bannerStyle = '';
  if (strpos($banner, '#') === 0) {
    // it's a hex color
    $bannerStyle = "background-color: {$banner};";
  } elseif ($banner) {
    // assume it's an image filename
    $url = $bannerImgPath . htmlspecialchars($banner);
    $bannerStyle = "background-image: url('{$url}'); background-size: cover; background-position: center;";
  }
  $current = basename($_SERVER['PHP_SELF']);

require __DIR__ . '/../handlers/plans/asad.php';
require __DIR__ . '/../handlers/plans/fetchplans.php';
require_once __DIR__ . '/../popups/aspopup.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!--<link rel="stylesheet" href="../assets/css/sidebar.css">
  <link rel="stylesheet" href="../assets/css/navbar.css"> -->
  <link rel="stylesheet" href="../assets/css/viewplan.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Text:wght@400;500;700">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github.min.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/languages/php.min.js"></script>
  <script>hljs.highlightAll();</script>
  <script src="../assets/js/togglesidebar.js"></script>
  <script src="../assets/js/toggletabs.js"></script>
  <script src="../assets/js/viewplan.js"></script>
  <script>
    var tasks = <?= json_encode($plan['tasks'] ?? []) ?>;
    var usertasks = <?= json_encode($userTasks) ?>;
    document.addEventListener("DOMContentLoaded", function () {
    const userData = JSON.parse(sessionStorage.getItem("userData"));
    if (userData && userData.photoURL) {
        document.getElementById("profilePic").src = userData.photoURL;
    } else {
        console.log("User photo not found in session storage.");
    }
    });
  document.addEventListener('click', e => {
    if (!e.target.matches('.copy-btn')) return;
    const btn       = e.target;
    const targetId  = btn.getAttribute('data-target');
    const codeBlock = document.getElementById(targetId);
    if (!codeBlock) return;
  
    navigator.clipboard.writeText(codeBlock.innerText)
      .then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
      })
      .catch(() => {
        btn.textContent = 'Failed';
        setTimeout(() => btn.textContent = 'Copy', 2000);
      });
     });
    </script>
  <script src="../assets/js/smallcalendar.js"></script>
  <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
  <title>Orbitask - Shared Project</title>
</head>
<body>

  <div class="navbar">
    <div class="navbar-left">
      <button class="hamburger" onclick="toggleSidebar()">☰</button>
      <a href="dashboard.php">
      <img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
      <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2"></a>
      <a href="#">> Shared Project Details</a>
    </div>
    <div class="profile-dropdown">
    <img
          src="<?= htmlspecialchars($currentUserPhoto) ?>"
          class="profile-pic"
          onclick="toggleProfileDropdown()"
        >
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
          <a href="dashboard.php">
            <img src="../assets/img/icons/homeicon.png" alt="" class="sidebar-icon">Home
          </a>
          <a href="calendar.php">
            <img src="../assets/img/icons/calendaricon.png" alt="" class="sidebar-icon">Calendar
          </a>
          <a  href="#" class="sidebar-item no-border non-clickable <?= $current === 'assistantadmin.php' ? 'active' : '' ?>">
            <img src="../assets/img/icons/tasksicon.png" alt="" class="sidebar-icon">Tasks Due date:
          </a>
              <div class="outer-card"> 
                <div class="calendar-navigation">
                  <button class="calendar-button" id="prevMonth">&#8592;</button>
                  <span id="calendarMonth">Loading...</span>
                  <button class="calendar-button" id="nextMonth">&#8594;</button>
                </div>
                <div class="small-calendar" id="smallCalendar"></div>
              </div>
          <a href="archive.php"><img src="../assets//img/icons/archive-icon.png" alt="" class="sidebar-icon">Archive</a>
    </div>
  </div>

  <div class="navbar-content">
    <div class="navbar-tabs">
      <a href="#" class="tablinks active" onclick="openTab(event, 'Cards')">Discussion Board</a>
      <a href="#" class="tablinks" onclick="openTab(event, 'Tasks')">Tasks</a>
      <a href="#" class="tablinks" onclick="openTab(event, 'Invites')">People</a>
    </div>

    <div class="main-content">

      <!-- Banner -->
      <div class="banner" style="<?= $bannerStyle ?>">
        <div class="banner-left">
          <h1 class="plan-title"><?= htmlspecialchars($plan['title'] ?? 'Untitled Plan') ?></h1>
          <p class="plan-status"><strong>Status:</strong> <?= htmlspecialchars($plan['status'] ?? 'In Progress') ?></p>
          <p class="plan-role"><strong>Your Role:</strong> Assistant Admin</p>
        </div>
        <div class="banner-right">
          <div class="banner-right-top">
            <p><strong>Start Date:</strong> <?= htmlspecialchars($plan['start_date'] ?? '—') ?></p>
            <p><strong>End Date:</strong> <?= htmlspecialchars($plan['end_date'] ?? '—') ?></p>
          </div>
          <div class="banner-right-bottom">
            <div class="info-dropdown-container">
              <button class="plus-btn" onclick="togglePlusDropdown()">+</button>
              <button class="info-btn" onclick="toggleInfoDropdown()">i</button>
              <div id="plusDropdown" class="dropdown-content plus-dropdown">
                <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
                  <button  class="dropdown-item" onclick="openPopup('announcementPopup')">Add Announcement</button>
                  <button class="dropdown-item" onclick="openPopup('notesPopup')">Add Note</button>
                <?php endif; ?>
              </div>
              <div id="infoDropdown" class="info-dropdown">
                <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
                  <form method="POST">
                    <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                    <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
                    <input type="hidden" name="update_status" value="1">
                    <select name="status" onchange="this.form.submit()">
                      <option value="In Progress" <?= ($plan['status'] === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                      <option value="Completed" <?= ($plan['status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
                    </select>
                  </form>
                  <button class="edit-btn" onclick="toggleNotes()">View Notes</button>
                <?php endif; ?>
                <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
                  <button class="edit-btn" onclick="openPopup('bannerPopup')">Edit Banner</button>
                  <form method="POST" onsubmit="return confirm('Delete this plan?');">
                    <input type="hidden" name="delete_plan" value="1">
                    <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                    <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
                    <button class="delete-btn">Delete Plan</button>
                  </form>
                <?php endif; ?>
              </div>
              <script>
    function toggleNotes() {
        const notesCard = document.querySelector('.card-notes');
        const annoCard = document.querySelector('.card-anno');
        const screenWidth = window.innerWidth;

        if (screenWidth <= 1024) {
            if (notesCard.style.display === 'none' || notesCard.style.display === '') {
                notesCard.style.display = 'block';
                annoCard.style.display = 'none';
            } else {
                notesCard.style.display = 'none';
                annoCard.style.display = 'block';
            }
        }
    }

    // Ensure the initial state is set correctly based on screen size
    document.addEventListener('DOMContentLoaded', () => {
        const notesCard = document.querySelector('.card-notes');
        const annoCard = document.querySelector('.card-anno');
        const screenWidth = window.innerWidth;

        if (screenWidth <= 1024) {
            notesCard.style.display = 'none';
            annoCard.style.display = 'block';
        }
    });

    // Adjust visibility on window resize
    window.addEventListener('resize', () => {
        const notesCard = document.querySelector('.card-notes');
        const annoCard = document.querySelector('.card-anno');
        const screenWidth = window.innerWidth;

        if (screenWidth > 1024) {
            notesCard.style.display = 'block';
            annoCard.style.display = 'block';
        } else {
            notesCard.style.display = 'none';
            annoCard.style.display = 'block';
        }
    });
</script>
            </div>
          </div>
        </div>
      </div>

      <div id="Cards" class="tabcontent" style="display:block;">
            <div class="card-container">
                <div class="card card-anno">
                <ul class="card-list">
                  <?php if (!empty($plan['announcements']) && is_array($plan['announcements'])): ?>
                    <?php foreach ($plan['announcements'] as $announcementId => $announcement): ?>
                      <div class="announcement-card">
                      <li class="announcement-item">
                      <div class="announcement-header">
                        <img
                          src="<?= htmlspecialchars($announcement['authorPhoto'] ?? '') ?>"
                          alt="<?= htmlspecialchars($announcement['authorName'] ?? $announcement['authorEmail']) ?>"
                          class="announcement-avatar"                        />
                        <div class="announcement-meta">
                          <span class="announcement-author">
                            <?= htmlspecialchars($announcement['authorName'] ?? $announcement['authorEmail']) ?>
                          </span>
                          <em class="announcement-timestamp">
                            <?= date('F j, Y g:i a', $announcement['timestamp']) ?>
                          </em>
                        </div>
                   
                        <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
                  <div class="announcement-actions">
                    <button
                      type="button"
                      class="dropdown-toggle"
                      onclick="toggleDropdown('actionsDropdown-<?= $announcementId ?>')"
                      aria-haspopup="true"
                      aria-expanded="false"
                    >⋯</button>

                    <div id="actionsDropdown-<?= $announcementId ?>" class="dropdown-menu">
                      <button
                        type="button"
                        class="dropdown-item"
                        onclick="openPopup('editAnnouncementPopup-<?= $announcementId ?>')"
                      >Edit</button>

                      <button type="button" class="dropdown-item" onclick="openDeleteModal('<?= htmlspecialchars($planId) ?>', '<?= htmlspecialchars($announcementId) ?>')">
                        Delete
                      </button>
                    </div>
                  </div>
                <?php endif; ?>

                  </div>
              <?php
              echo $Parsedown->text($announcement['text']);

              if (!empty($announcement['code'])): 
                $langClass = $announcement['code_lang'] 
                           ? 'language-'. htmlspecialchars($announcement['code_lang']) 
                           : '';
                $codeId    = "code-{$announcementId}";
            ?>
              <div class="code-container">
                <button class="copy-btn" data-target="<?= $codeId ?>">Copy</button>
                <pre><code id="<?= $codeId ?>" class="<?= $langClass ?>">
            <?= htmlspecialchars($announcement['code'] ?? '')?>
            </code></pre>
              </div>
            <?php endif; ?>
                
              <?php if (!empty($announcement['files']) && is_array($announcement['files'])): ?>
                <div class="announcement-files">
                  <?php foreach ($announcement['files'] as $fileUrl): ?>
                    <?php 
                      $extension = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
                      $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                      $fileName = basename($fileUrl);
                    ?>
                    <button class="file-preview-btn" onclick="window.open('<?= htmlspecialchars($fileUrl) ?>', '_blank')">
                      <?php if ($isImage): ?>
                        <img src="<?= htmlspecialchars($fileUrl) ?>" alt="Image Preview" class="file-thumbnail" />
                      <?php else: ?>
                        <i class="fas fa-file file-icon"></i>
                      <?php endif; ?>
                      <span class="file-name"><?= htmlspecialchars($fileName) ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($announcement['website_urls']) && is_array($announcement['website_urls'])): ?>
                <div class="announcement-website">
                  <?php foreach ($announcement['website_urls'] as $url): ?>
                    <?php 
                      $websiteHost = parse_url($url, PHP_URL_HOST);
                    ?>
                    <button class="website-link-btn" onclick="window.open('<?= htmlspecialchars($url) ?>', '_blank')">
                      <img src="https://www.google.com/s2/favicons?domain=<?= htmlspecialchars($websiteHost) ?>" alt="Favicon" class="website-favicon">
                      <span class="website-name"><?= htmlspecialchars($websiteHost) ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

            <?php if (!empty($announcement['comments']) && is_array($announcement['comments'])): ?>
              <div class="comments-container">
          <ul class="comment-list">
            <h1>Comments:</h1>
            <?php 
              $commentCount = count($announcement['comments']);
              $i = 0;
            ?>
            <?php foreach ($announcement['comments'] as $commentId => $comment): ?>
              <?php 
                $hiddenClass = ($i >= 1) ? 'hidden-comment' : '';
                $commentAuthorEmail = $comment['authorEmail'] ?? '';
                $isCommentOwner = (trim(strtolower($commentAuthorEmail)) === trim(strtolower($currentUserEmail)));
      
              ?>
              <li class="comment-item <?= $hiddenClass ?>">
                <img src="<?= htmlspecialchars($comment['authorPhoto'] ?? '/assets/img/default-avatar.png') ?>" alt="Commenter Avatar" class="comment-avatar">
                <div class="comment-details">
                  <strong><?= htmlspecialchars($comment['authorName'] ?? '') ?></strong>
                  <em> <?= date('F j, Y g:i a', $comment['timestamp']) ?></em>
                  <p><?= htmlspecialchars($comment['text']) ?></p>
                  </div>
                  <?php if ($isCommentOwner): ?>
            <!-- Three-dots dropdown -->
            <div class="comment-actions" style="position: relative;">
              <button
                type="button"
                class="dropdown-toggle"
                onclick="toggleDropdown('commentDropdown-<?= $announcementId ?>-<?= $commentId ?>')"
                aria-haspopup="true"
                aria-expanded="false"
              >⋯</button>

              <div id="commentDropdown-<?= $announcementId ?>-<?= $commentId ?>" class="dropdown-menu">
                <button type="button" class="dropdown-item"
                        onclick="openPopup('editComment-<?= $announcementId ?>-<?= $commentId ?>')">
                  Edit
                </button>
                <button type="button" class="dropdown-item"
                        onclick="openPopup('deleteComment-<?= $announcementId ?>-<?= $commentId ?>')">
                  Delete
                </button>
              </div>
            </div>

            <!-- Edit Comment Popup -->
            <div id="editComment-<?= $announcementId ?>-<?= $commentId ?>" class="popup" style="display:none;">
              <div class="popup-content elegant-popup">
                <form method="post">
                  <h4>Edit Comment</h4>
                  <textarea name="edited_comment_text" required><?= htmlspecialchars($comment['text']) ?></textarea>
                  <input type="hidden" name="plan_id"         value="<?= htmlspecialchars($planId) ?>">
                  <input type="hidden" name="owner_uid"       value="<?= htmlspecialchars($ownerUid) ?>">
                  <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($announcementId) ?>">
                  <input type="hidden" name="comment_id"      value="<?= htmlspecialchars($commentId) ?>">
                  <button type="submit" name="edit_comment">Save</button>
                  <button type="button" onclick="closePopup('editComment-<?= $announcementId ?>-<?= $commentId ?>')">Cancel</button>
                </form>
              </div>
            </div>

            <!-- Delete Comment Modal -->
            <div id="deleteComment-<?= $announcementId ?>-<?= $commentId ?>" class="popup" style="display:none;">
              <div class="popup-content-delete">
                <p>Are you sure you want to delete this comment?</p>
                <form method="post">
                  <input type="hidden" name="plan_id"         value="<?= htmlspecialchars($planId) ?>">
                  <input type="hidden" name="owner_uid"       value="<?= htmlspecialchars($ownerUid) ?>">
                  <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($announcementId) ?>">
                  <input type="hidden" name="comment_id"      value="<?= htmlspecialchars($commentId) ?>">
                  <button type="submit" name="delete_comment" class="deletebutton">Delete</button>
                  <button type="button" class="cancelbutton" onclick="closePopup('deleteComment-<?= $announcementId ?>-<?= $commentId ?>')">Cancel</button>
                </form>
              </div>
            </div>
          <?php endif; ?>
              </li>
              <?php $i++; ?>
            <?php endforeach; ?>
          </ul>
          <?php if ($commentCount > 1): ?>
            <button class="toggle-comments-btn" data-visible="false" onclick="toggleComments(this)">
              View All Comments
            </button>
          <?php endif; ?>
        </div>
      <?php endif; ?>
                
      <?php if ($allowedToComment): ?>
    <div class="comment-form-wrapper">
      <img src="<?= htmlspecialchars($currentUserPhoto ?: '/assets/img/default-avatar.png') ?>" alt="User Avatar" class="comment-avatar">
      <form method="POST" class="comment-form">
        <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
        <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
        <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($announcementId) ?>">
        <textarea name="comment_text" placeholder="Add a comment..." required></textarea>
        <button type="submit" name="add_comment" class="comment-submit-btn">
          <i class="fas fa-paper-plane"></i>
        </button>
      </form>
    </div>
   <?php endif; ?>

      <div class="popup" id="editAnnouncementPopup-<?= $announcementId ?>">
       <div class="popup-content elegant-popup">
          <h4>Edit Announcement</h4>
          <span class="close-popup" onclick="closePopup('editAnnouncementPopup-<?= $announcementId ?>')">&times;</span>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
            <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
            <input type="hidden" name="announcement_id" value="<?= htmlspecialchars($announcementId) ?>">
              <textarea name="announcement_text" required><?= htmlspecialchars($announcement['text']) ?></textarea>
              <label for="announcement_code_lang_edit">Code Language:</label>
              <select name="announcement_code_lang" id="announcement_code_lang_edit">
                <option value="" <?= empty($announcement['code_lang']) ? 'selected' : '' ?>>– none –</option>
                <option value="php"        <?= ($announcement['code_lang'] ?? '')==='php'        ? 'selected' : '' ?>>PHP</option>
                <option value="javascript" <?= ($announcement['code_lang'] ?? '')==='javascript' ? 'selected' : '' ?>>JavaScript</option>
                <option value="python"     <?= ($announcement['code_lang'] ?? '')==='python'     ? 'selected' : '' ?>>Python</option>
                <option value="html"       <?= ($announcement['code_lang'] ?? '')==='html'       ? 'selected' : '' ?>>HTML</option>
                <option value="css"        <?= ($announcement['code_lang'] ?? '')==='css'        ? 'selected' : '' ?>>CSS</option>
              </select>

              <label for="announcement_code_edit">Edit Code Snippet:</label>
              <textarea
                name="announcement_code"
                id="announcement_code_edit"
                placeholder="Paste your code here…"
                style="font-family: monospace; min-height: 100px;"><?=
                  htmlspecialchars($announcement['code'] ?? '')
              ?></textarea>

              <label for="announcement_files_edit">Attach New Files:</label>
              <input 
                type="file" 
                name="announcement_files[]" 
                id="announcement_files_edit" 
                accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" 
                multiple >
                <?php if (!empty($announcement['files'])): ?>
                <div class="existing-files-list">
                  <strong class="existing-files-title">Already attached:</strong>
                  <?php foreach ($announcement['files'] as $idx => $fileUrl): 
                    $fileName = basename($fileUrl);
                    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $isImage  = in_array($ext, ['jpg','jpeg','png','gif'], true);
                  ?>
                    <div class="file-preview-btn">
                      <?php if ($isImage): ?>
                        <img src="<?= htmlspecialchars($fileUrl) ?>" 
                            alt="<?= htmlspecialchars($fileName) ?>" 
                            class="file-thumbnail" />
                      <?php else: ?>
                        <i class="fas fa-file file-icon"></i>
                      <?php endif; ?>
                      <a href="<?= htmlspecialchars($fileUrl) ?>" 
                        target="_blank" 
                        class="file-name"><?= htmlspecialchars($fileName) ?></a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <label>Website Links:</label>
              <div id="editWebsiteLinksContainer" class="url-input-stack">
              <button type="button" id="addWebsiteLinkBtn_edit" class="add-link-btn">Add</button>
                <?php 
                  if (!empty($announcement['website_urls']) && is_array($announcement['website_urls'])):
                    foreach ($announcement['website_urls'] as $url):
                ?>
                  <input type="url" name="announcement_urls[]" placeholder="https://example.com" value="<?= htmlspecialchars($url) ?>">
                <?php 
                    endforeach;
                  else: 
                ?>
                  <input type="url" name="announcement_urls[]" placeholder="https://example.com">
                <?php endif; ?>
              </div>
              <button type="submit" name="edit_announcement" class="submit-btn">Save Changes</button>
            </form>
          </div>
        </div>
      </li>

      <br>

      <?php endforeach; ?>
              <?php else: ?>
                <div class="no-announcement-message">
                <p>No announcements yet.</p>
              </div>
          <?php endif; ?>
         </ul>
     </div>

    <div class="card card-notes">
        <div class="card-header">
          <h4>Notes</h4>
        </div>
    <ul class="card-list">
          <?php if (!empty($plan['notes']) && is_array($plan['notes'])): ?>
            <?php 
              $lastNote = end($plan['notes']);
              foreach ($plan['notes'] as $noteId => $note): 
            ?>
              <li style="padding: 10px 0;">
                <div class="note-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
                  <div style="display: flex; align-items: flex-start;">
                    <img 
                      src="<?= htmlspecialchars($note['authorPhoto'] ?? '/assets/img/default-avatar.png') ?>" 
                      alt="Author Photo" 
                      style="width: 32px; height: 32px; border-radius: 50%; margin-right: 8px;"
                    />
                    <div>
                      <p style="margin: 0;"><?= htmlspecialchars($note['authorName'] ?? $note['author']) ?></p>
                      <em style="display: block; font-size: 0.85em; color: #666;">
                      <em><?= date('F j, Y g:i a', $note['timestamp']) ?></em>
                      </em>
                    </div>
                  </div>

                  <?php if (($note['authorName'] ?? '') === $currentUserName): ?>
                    <div class="announcement-actions" style="position: relative;">
                      <button
                        type="button"
                        class="dropdown-toggle"
                        onclick="toggleDropdown('noteDropdown-<?= $noteId ?>')"
                        aria-haspopup="true"
                        aria-expanded="false"
                      >⋯</button>

                      <div id="noteDropdown-<?= $noteId ?>" class="dropdown-menu">
                        <button type="button" class="dropdown-item" onclick="openPopup('editNotePopup-<?= $noteId ?>')">Edit</button>
                        <button type="button" class="dropdown-item" onclick="openPopup('noteDeleteModal-<?= $noteId ?>')">Delete</button>
                      </div>
                    </div>
                  <div id="noteDeleteModal-<?= $noteId ?>" class="popup" style="display: none;">
                  <div class="popup-content-delete">
                    <p>Are you sure you want to delete this note?</p>
                    <form method="post">
                      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                      <input type="hidden" name="note_id" value="<?= htmlspecialchars($noteId) ?>">
                      <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
                      <button type="submit" class="deletebutton" name="delete_note">Delete</button>
                      <button type="button" class="cancelbutton" onclick="closePopup('noteDeleteModal-<?= $noteId ?>')">Cancel</button>
                    </form>
                  </div>
                </div>
                  <div id="editNotePopup-<?= $noteId ?>" class="popup">
                    <div class="popup-content elegant-popup">
                      <h4>Edit Note</h4>
                      <span class="close-popup" onclick="closePopup('editNotePopup-<?= $noteId ?>')">&times;</span>
                      <form method="POST">
                        <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                        <input type="hidden" name="note_id" value="<?= htmlspecialchars($noteId) ?>">
                        <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
                        <textarea name="edited_note_text" required><?= htmlspecialchars($note['text']) ?></textarea>
                        <button type="submit" name="edit_note" class="submit-btn">Save Changes</button>
                        <button type="button" class="cancelbutton" onclick="closePopup('editNotePopup-<?= $noteId ?>')">Cancel</button>
                      </form>
                    </div>
                  </div>

                  <?php endif; ?>
                </div>

                <div style="margin-top: 8px; padding-left: 40px;">
                  <pre class="note-text" style="margin: 0;"><?= htmlspecialchars($note['text']) ?></pre>
                </div>

                <?php if ($note !== $lastNote): ?>
                  <hr style="margin: 5px 0; border: 0; border-top: 1px solid #ddd;">
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
      <div class="no-notes-message">
      <i class="fas fa-sticky-note"></i>
      <p>No notes yet.</p>
    </div>
    <?php endif; ?>
  </ul>
  </div>
</div>
</div>

<div id="Invites" class="tabcontent">
        <div class="invites-tab-header">
          <h3>People</h3>
          <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
            <div class="invites-dropdown">
            <!-- three-dot trigger -->
            <button class="invites-dropdown-toggle" aria-label="Invite actions">⋯</button>

            <!-- dropdown menu -->
            <ul class="invites-dropdown-menu">
              <li>
                <button onclick="openPopup('invitePopup')">
                  + Invite People
                </button>
              </li>
              <li>
                <button onclick="openPopup('pendingPopup')">
                  View Pending Invites
                </button>
              </li>
            </ul>
          </div>
          <?php endif; ?>
        </div>

        <ul class="people-list">
  <!-- Owner Section -->
  <li class="section-title">Owner</li>
  <?php foreach ($ownerList as $emailKey => $_role): ?>
    <?php $email = str_replace('_','.',$emailKey); ?>
    <li>
      <span class="people-icon">✉</span>
      <span class="email-text"><?= htmlspecialchars($email) ?></span>
    </li>
  <?php endforeach; ?>

  <!-- Assistant Admins Section -->
  <?php if (!empty($assistantAdmins)): ?>
    <li class="section-title">Assistant Admin</li>
    <?php foreach ($assistantAdmins as $emailKey => $role): ?>
      <?php $email = str_replace('_','.',$emailKey); ?>
      <li>
        <span class="people-icon">✉</span>
        <span class="email-text"><?= htmlspecialchars($email) ?></span>
        <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
          <form method="POST" class="change-role-form" style="display: inline;">
            <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
            <input type="hidden" name="email_key" value="<?= htmlspecialchars($emailKey) ?>">
            <select name="new_role" onchange="this.form.submit()">
              <option value="assistant admin">Assistant Admin</option>
              <option value="collaborator" <?= strtolower($role) === 'collaborator' ? 'selected' : '' ?>>Collaborator</option>
            </select>
          </form>
          <form method="POST" class="remove-invite-form">
            <input type="hidden" name="plan_id"  value="<?= htmlspecialchars($planId) ?>">
            <input type="hidden" name="email_key" value="<?= htmlspecialchars($emailKey) ?>">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?= urlencode($email) ?>"
               target="_blank"  class="send-email-btn" title="Email <?= htmlspecialchars($email) ?>">
              <i class="fas fa-envelope"></i>
            </a>
            <button class="invites-open-popup-btn"
                    type="submit"
                    name="remove_invite"
                    onclick="return confirm('Remove <?= htmlspecialchars($email) ?>?');">
              Remove
            </button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Collaborators Section -->
  <?php if (!empty($collaborators)): ?>
    <li class="section-title">Collaborators</li>
    <?php foreach ($collaborators as $emailKey => $role): ?>
      <?php $email = str_replace('_','.',$emailKey); ?>
      <li>
        <span class="people-icon">✉</span>
        <span class="email-text"><?= htmlspecialchars($email) ?></span>
        <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
          <form method="POST" class="change-role-form" style="display: inline;">
            <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
            <input type="hidden" name="email_key" value="<?= htmlspecialchars($emailKey) ?>">
            <select name="new_role" onchange="this.form.submit()">
              <option value="assistant admin">Assistant Admin</option>
              <option value="collaborator" <?= strtolower($role) === 'collaborator' ? 'selected' : '' ?>>Collaborator</option>
            </select>
          </form>
          <form method="POST" class="remove-invite-form">
            <input type="hidden" name="plan_id"  value="<?= htmlspecialchars($planId) ?>">
            <input type="hidden" name="email_key" value="<?= htmlspecialchars($emailKey) ?>">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?= urlencode($email) ?>"
               target="_blank"  class="send-email-btn" title="Email <?= htmlspecialchars($email) ?>">
              <i class="fas fa-envelope"></i>
            </a>
            <button class="invites-open-popup-btn"
                    type="submit"
                    name="remove_invite"
                    onclick="return confirm('Remove <?= htmlspecialchars($email) ?>?');">
              Remove
            </button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (empty($ownerList) && empty($assistantAdmins) && empty($collaborators)): ?>
    <li class="no-collaborators">No collaborators invited yet.</li>
  <?php endif; ?>
</ul>
      </div>

              <div id="Tasks" class="tabcontent">   
              <div class="task-tab-header">
                <h3 id="detailTitle" style="margin: 0;">List of Tasks</h3>
                <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
                <div class="task-detail-dropdown">
                  <!-- three-dot trigger button -->
                  <button class="td-dropdown-toggle" aria-label="Task actions">⋯</button>

                  <!-- hidden until toggled -->
                  <ul class="td-dropdown-menu">
                    <li>
                      <button onclick="openSubTab(event, 'P-Tasks'); closeTaskDetailsDropdown(this)">
                        Tasks
                      </button>
                    </li>
                    <li>
                      <button onclick="openSubTab(event, 'Activity'); closeTaskDetailsDropdown(this)">
                        Activity
                      </button>
                    </li>
                    <li>
                      <button onclick="openPopup('taskPopup'); closeTaskDetailsDropdown(this)">
                        + Add Task
                      </button>
                    </li>
                  
                  </ul>
                </div>
                <?php endif; ?>
              </div>

            <div id="P-Tasks" class="subtabcontent">
              <?php if (!empty($plan['tasks'])): ?>            
              <?php foreach ($plan['tasks'] as $taskId => $task): ?>
                <div class="task-card" onclick="showTaskDetails('<?= htmlspecialchars($taskId) ?>')">
                  <div class="task-header">
                    <form method="POST" class="task-form">
                      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                      <input type="hidden" name="owner_uid" value="<?= $ownerUid ?>">
                      <input type="hidden" name="task_id" value="<?= $taskId ?>">
                      <span class="task-name"><?= htmlspecialchars($task['name']) ?></span>
                      <?php 
                        if (!empty($task['due_date']) && !empty($task['due_time'])):
                          $dueTs = strtotime($task['due_date'] . ' ' . $task['due_time']);
                      ?>
                        <span class="task-due">
                          (Due: <?= date('F j, Y', $dueTs) ?> <br> at <?= date('g:i a', $dueTs) ?>)
                        </span>
                      <?php endif; ?>
                    </form>
                    
                    <?php if (!empty($task['files'])): ?>
                      <div class="task-files announcement-files">
                        <?php foreach ($task['files'] as $fileUrl): ?>
                          <?php 
                            $extension = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
                            $isImage   = in_array($extension, ['jpg','jpeg','png','gif'], true);
                            $fileName  = basename($fileUrl);
                          ?>
                          <button
                            class="file-preview-btn"
                            type="button">
                            <?php if ($isImage): ?>
                              <img
                                src="<?= htmlspecialchars($fileUrl) ?>"
                                alt="Preview of <?= htmlspecialchars($fileName) ?>"
                                class="file-thumbnail"
                              />
                            <?php else: ?>
                              <i class="fas fa-file file-icon"></i>
                            <?php endif; ?>
                            <span class="file-name"><?= htmlspecialchars($fileName) ?></span>
                          </button>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <div class="assignment-info">
                      <strong>Assigned to:</strong>
                      <?php if (!empty($task['assigned_to'])): ?>
                        <?php
                          $assignedNames = [];
                          foreach ($task['assigned_to'] as $assignedEmail) {
                              $assignedNames[] = htmlspecialchars(str_replace('_', '.', $assignedEmail));
                          }
                          echo implode(", ", $assignedNames);
                        ?>
                      <?php else: ?>
                        <span>Unassigned</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  
                </div>

                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="no-task-message">
                <i class="fas fa-tasks"></i>
                <p>No tasks available.</p>
              </div>
                <?php endif; ?>
              </div>
              
              <div id="Activity" class="subtabcontent" style="display: none;">
              <?php if (!empty($userTasks)): ?>
            <?php foreach ($userTasks as $taskId => $usertasks): ?>
                  <div class="task-card" onclick="showuserTaskDetails('<?= htmlspecialchars($taskId) ?>')">
                    <div class="task-header">
                      <form method="POST" class="task-form">
                        <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                        <input type="hidden" name="owner_uid" value="<?= $uid ?>">
                        <input type="hidden" name="task_id" value="<?= $taskId ?>">
                        <span class="task-name"><?= htmlspecialchars($usertasks['name']) ?></span>
                        <?php 
                          if (!empty($usertasks['due_date']) && !empty($usertasks['due_time'])):
                            // parse into a Unix timestamp
                            $dueTs = strtotime($usertasks['due_date'] . ' ' . $usertasks['due_time']);
                        ?>
                          <span class="task-due">
                            (Due: <?= date('F j, Y', $dueTs) ?> <br> at <?= date('g:i a', $dueTs) ?>)
                          </span>
                        <?php endif; ?>

                      </form>
                      
                      <?php if (!empty($usertasks['files'])): ?>
                        <div class="task-files announcement-files">
                          <?php foreach ($usertasks['files'] as $fileUrl): ?>
                            <?php 
                              $extension = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
                              $isImage   = in_array($extension, ['jpg','jpeg','png','gif'], true);
                              $fileName  = basename($fileUrl);
                            ?>
                            <button
                              class="file-preview-btn"
                              type="button">
                              <?php if ($isImage): ?>
                                <img
                                  src="<?= htmlspecialchars($fileUrl) ?>"
                                  alt="Preview of <?= htmlspecialchars($fileName) ?>"
                                  class="file-thumbnail"
                                />
                              <?php else: ?>
                                <i class="fas fa-file file-icon"></i>
                              <?php endif; ?>
                              <span class="file-name"><?= htmlspecialchars($fileName) ?></span>
                            </button>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>                 
                  </div>

                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="no-task-message">
                <i class="fas fa-tasks"></i>
                <p>No tasks available.</p>
              </div>
                  <?php endif; ?>
                </div>
                   
              </div>
              
            <div id="TaskDetails" class="tabcontent" style="display:none;">
            <div class="task-tab-header">
                      <h3 id="detailTitle" style="margin: 0;">Task Details</h3>
                      <?php if ($isOwner || in_array($myEmailKey, $accepted)): ?>
                      <div class="task-detail-dropdown">
                        <!-- three-dot trigger button -->
                        <button class="td-dropdown-toggle" aria-label="Task actions">⋯</button>

                        <!-- hidden until toggled -->
                        <ul class="td-dropdown-menu">
                          <li>
                            <button onclick="openPopup('editTaskPopup-<?= $taskId ?>')">
                              Edit
                            </button>
                          </li>
                          <li>
                            <button onclick="openPopup('assignPopup')">
                              Assign Task
                            </button>
                          </li>
                          <li>
                          <form method="POST" class="inline-form">
                          <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                          <input type="hidden" name="owner_uid" value="<?= $ownerUid ?>">
                          <input type="hidden" name="task_id" value="<?= htmlspecialchars($taskId) ?>">
                          <button type="submit" name="finish_task">Finish Task</button>
                        </form>
                       </li>
                          <li>
                            <button onclick="openPopup('deleteTaskModal')">
                              Delete
                            </button>
                          </li>
                          <li>
                            <button onclick="closeTaskDetails()">
                              Close
                            </button>
                          </li>
                        </ul>
                      </div>
                    <?php endif; ?>             
                  </div>
                  <ul class="task-card-list">
          <li class="task-card">
            <div class="task-card__body">
              <div class="task-card__main">
                <div class="task-card__info">
                  <h2 class="task-card__name" id="taskDetailName"></h2>
                  <p class="task-card__description" id="taskDetailDescription">—</p>
                  <p class="task-card__submeta">
                    <strong>Assigned To:</strong> <span id="taskDetailAssignedTo"></span>
                  </p>
                </div>
                <div class="task-card__meta">
                  <p><strong>Completed:</strong> <span id="taskDetailCompleted"></span></p>
                  <p><strong>Due Date:</strong> <span id="taskDetailDueDate"></span></p>
                  <p><strong>Due Time:</strong> <span id="taskDetailDueTime"></span></p>
                </div>
              </div>
            </div>

            <div class="task-card__section task-card__files" id="fileContainer">
              <h4 class="task-card__section-title">Files:</h4>
            </div>

            <div class="task-card__section task-card__urls" id="urlContainer">
              <h4 class="task-card__section-title">Website URLs:</h4>
            </div>

            <div class="task-card__section uploads-list" id="uploadsContainer">
              <h4 class="task-card__section-title">Uploads:</h4>
            </div>
          </li>
        </ul>
            </div>
            <div class="popup uploads-popup" id="uploadsPopup">
  <div class="popup-content elegant-popup">
    <button class="close-popup" onclick="closePopup('uploadsPopup')">&times;</button>
    <h4 class="popup-title" id="uploadsPopupTitle">Uploads</h4>
    <div id="uploadsPopupBody" class="uploads-grid">
      <!-- thumbnails injected here -->
    </div>
  </div>
</div>
          <div class="popup" id="assignPopup">
            <div class="popup-content elegant-popup">
              <span class="close-popup" onclick="closePopup('assignPopup')">&times;</span>
              <h4>Assign Task</h4>
              <form method="POST" id="assignForm">
                <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
                <input type="hidden" name="task_id"   value="" id="assignFormTaskId">

                <div class="form-group" id="assignCheckboxes">
                  <?php if (!empty($accepted)): ?>
                    <?php foreach ($accepted as $emailKey): ?>
                      <div class="checkbox-group">
                        <label>
                          <input type="checkbox" name="assigned_to[]" value="<?= htmlspecialchars($emailKey) ?>">
                          <?= htmlspecialchars(str_replace('_','.', $emailKey)) ?>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <p>No accepted collaborators</p>
                  <?php endif; ?>
                </div>

                <button type="submit" name="assign_task">Save Assignment</button>
              </form>
            </div>
          </div>


          <div class="popup" id="editTaskPopup-<?= $taskId ?>">
            <div class="popup-content elegant-popup">
              <h4>Edit Task</h4>
              <span class="close-popup" onclick="closePopup('editTaskPopup-<?= $taskId ?>')">&times;</span>    <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
                <input type="hidden" name="task_id" id="editTaskId" value="">

                <label>Name:</label>
                <input
                    type="text"
                    name="task_name"
                    required
                    value="<?= htmlspecialchars($task['name'] ?? '') ?>"
                  id="editTaskName">

                  <textarea
                name="task_description"
                id="editTaskDescription"
                rows="4"
                placeholder="Enter a description…"
              ><?= htmlspecialchars($task['description'] ?? '') ?></textarea>

                <label>Due Date:</label>
                <input
                  type="date"
                  name="due_date"
                  value="<?= htmlspecialchars($task['due_date'] ?? '') ?>"
                  id="editTaskDate">

                <label>Due Time:</label>
                <input
                  type="time"
                  name="due_time"
                  value="<?= htmlspecialchars($task['due_time'] ?? '') ?>"
                  id="editTaskTime">

                <label>Attach New Files:</label>
                <input
                    type="file"
                    name="task_files[]"
                    accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    multiple
                  >
                  <?php if (!empty($task['files'])): ?>
                <div class="existing-files-list">
                  <strong class="existing-files-title">Already attached:</strong>
                  <?php foreach ($task['files'] as $idx => $fileUrl): 
                    $fileName = basename($fileUrl);
                    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $isImage  = in_array($ext, ['jpg','jpeg','png','gif'], true);
                  ?>
                    <div class="file-preview-btn">
                      <?php if ($isImage): ?>
                        <img src="<?= htmlspecialchars($fileUrl) ?>"
                            alt="<?= htmlspecialchars($fileName) ?>"
                            class="file-thumbnail" />
                      <?php else: ?>
                        <i class="fas fa-file file-icon"></i>
                      <?php endif; ?>
                      <a href="<?= htmlspecialchars($fileUrl) ?>"
                        target="_blank"
                        class="file-name"><?= htmlspecialchars($fileName) ?></a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
                <label>Website Links:</label>
                <button type="button" onclick="addTaskUrl('<?= $taskId ?>')" class="add-link-btn">Add Link</button>
                <div id="editTaskUrlsContainer-<?= $taskId ?>" class="url-input-stack">
                  <?php if (!empty($task['website_urls']) && is_array($task['website_urls'])): ?>
                    <?php foreach ($task['website_urls'] as $url): ?>
                      <input
                        type="url"
                        name="task_urls[]"
                        placeholder="https://example.com"
                        value="<?= htmlspecialchars($url) ?>"
                      >
                    <?php endforeach; ?>
                  <?php else: ?>
                    <input type="url" name="task_urls[]" placeholder="https://example.com">
                  <?php endif; ?>
                </div>

                <button type="submit" name="edit_task" class="submit-btn">Save Changes</button>
                <button type="button" class="cancelbutton" onclick="closePopup('editTaskPopup')">Cancel</button>
              </form>
            </div>
          </div>


          <div class="popup" id="deleteTaskModal">
            <div class="popup-content-delete">
              <p>Are you sure you want to delete this task?</p>
              <form method="POST">
                <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
                <input type="hidden" name="task_id" id="deleteTaskId" value="">
                <button type="submit" name="delete_task" class="deletebutton">Delete</button>
                <button type="button" class="cancelbutton" onclick="closePopup('deleteTaskModal')">Cancel</button>
              </form>
            </div>
          </div>

      <div id="userTaskDetails" class="tabcontent" style="display:none;">
    <div class="task-tab-header">
      <h3 id="detailTitle" style="margin: 0;">Task Details</h3>
      <div class="task-detail-actions">
        <button class="td-open-popup-btn" onclick="closeTaskDetails()">X</button>
      </div>
    </div>
                     
    <!-- Wrap in a flex card -->
    <div class="card task-details-card">
      <!-- Left: existing announcement/body info -->
      <div class="card-body announcement-body--detail">
        <p><strong></strong> <span id="userTaskDetailName"></span></p>
        <p><strong></strong> <span id="usertaskDetailDescription"></span></p>
     
        <div id="userFileContainer" class="announcement-files detail-files">
          <strong>Files:</strong>
          <!-- JS injects file buttons -->
        </div>
        <div id="userUrlContainer" class="announcement-website detail-urls">
          <strong>Website URLs:</strong>
          <!-- JS injects URL buttons -->
        </div>
        </div>
        <p class="task-card__meta">
            <span id="userTaskDetailCompleted"></span><br>
            Due Date: <span id="userTaskDetailDueDate"></span><br>
            at <span id="userTaskDetailDueTime"></span>
          </p>
        <!-- Right: upload / URL card -->
    <div class="card task-detail-extra-card">
    <div class="content">
      <h4>Your Work</h4>

      <div class="uploaded-section">
        <h5>Uploaded Files</h5>
        <ul id="uploadedFileContainer" class="uploaded-list"></ul>

        <h5>Submitted URLs</h5>
        <ul id="uploadedUrlContainer" class="uploaded-list"></ul>
      </div>

      <form method="POST" enctype="multipart/form-data" class="upload-form">
        <input type="hidden" name="plan_id"   value="<?= htmlspecialchars($planId) ?>">
        <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
        <input type="hidden" name="task_id"   value="" id="uploadTaskId">
        <input type="hidden" name="task_id"   value="" id="urlTaskId">
        <div class="inputs-grid">
          <div class="form-group">
            <label for="taskFiles">Choose files</label>
            <input id="taskFiles" type="file" name="task_files[]" multiple>
          </div>
          <div class="form-group">
            <label for="taskUrl">Add a URL</label>
            <input id="taskUrl" type="url" name="task_url" placeholder="https://example.com">
          </div>
        </div>

        <button type="submit" name="add_task_upload" class="btn">Submit</button>
      </form>
    </div>
    </div>
<script>
  document.addEventListener("DOMContentLoaded", () => {
  const card = document.querySelector(".task-detail-extra-card");
  const topTip = document.createElement("div");
  topTip.classList.add("top-tip");
  card.appendChild(topTip);

  let isDragging = false;
  let startY = 0;
  let startHeight = 0;

  // Function to check screen width
  const isSmallScreen = () => window.innerWidth <= 1024;

  // Toggle expanded/collapsed state
  topTip.addEventListener("click", () => {
    if (isSmallScreen()) {
      card.classList.toggle("expanded");
    }
  });

  // Dragging functionality
  topTip.addEventListener("mousedown", (e) => {
    if (!isSmallScreen()) return;
    isDragging = true;
    startY = e.clientY;
    startHeight = card.offsetHeight;
    document.body.style.userSelect = "none"; // Prevent text selection
  });

  document.addEventListener("mousemove", (e) => {
    if (!isDragging || !isSmallScreen()) return;
    const deltaY = startY - e.clientY;
    const newHeight = Math.min(
      Math.max(startHeight + deltaY, 50), // Minimum height
      window.innerHeight * 0.8 // Maximum height
    );
    card.style.height = `${newHeight}px`;
  });

  document.addEventListener("mouseup", () => {
    if (isDragging) {
      isDragging = false;
      document.body.style.userSelect = ""; // Re-enable text selection
    }
  });
});
  </script>
    </div>
  </div>

        </div>
        <script>
          document.addEventListener('DOMContentLoaded', () => {
          const addTaskLinkBtn = document.getElementById('task-addWebsiteLinkBtn');
          if (!addTaskLinkBtn) return;

          addTaskLinkBtn.addEventListener('click', () => {
            const container = document.getElementById('task-websiteLinksContainer');
            const newInput = document.createElement('input');
            newInput.type = 'url';
            newInput.name = 'task_urls[]';
            newInput.placeholder = 'https://example.com';
            container.insertBefore(newInput, addTaskLinkBtn);
          });
        });
        function showuserTaskDetails(taskId) {
      const task = usertasks[taskId];
      if (!task) return alert("Task not found.");
      console.log("Populating user details for", taskId);

      // Fill basic fields
      document.getElementById("userTaskDetailName").textContent =
          task.name || "—";
      document.getElementById("userTaskDetailCompleted").textContent =
          task.completed ? "Yes" : "No";
          document.getElementById('usertaskDetailDescription').textContent =
          task.description || '—';
    if (task.due_date && task.due_time) {
      const dt = new Date(`${task.due_date}T${task.due_time}`);
        document.getElementById("userTaskDetailDueDate")
                .textContent = dt.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});
        document.getElementById("userTaskDetailDueTime")
                .textContent = dt.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
      } else {
        document.getElementById("userTaskDetailDueDate").textContent = 'N/A';
        document.getElementById("userTaskDetailDueTime").textContent = 'N/A';
      }
    
    
      const fileContainer = document.getElementById('userFileContainer');
      userFileContainer.innerHTML = ''; 
      if (task.files && task.files.length) {
        task.files.forEach(url => {
          const ext = url.split('.').pop().toLowerCase();
          const isImage = ['jpg','jpeg','png','gif'].includes(ext);
    
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'file-preview-btn';
          btn.onclick = () => window.open(url,'_blank');
    
          if (isImage) {
            const img = document.createElement('img');
            img.src = url;
            img.alt = 'Preview';
            img.className = 'file-thumbnail';
            btn.appendChild(img);
          } else {
            const icon = document.createElement('i');
            icon.className = 'fas fa-file file-icon';
            btn.appendChild(icon);
          }
    
          const span = document.createElement('span');
          span.className = 'file-name';
          span.innerText = url.split('/').pop();
          btn.appendChild(span);
    
          userFileContainer.appendChild(btn);
        });
      } else {
        userFileContainer.innerHTML += '<p style="color:#777;font-style:italic;font-size:12px;">No files attached.</p>';
      }
    
    
      const urlContainer = document.getElementById('userUrlContainer');
      userUrlContainer.innerHTML = '';
      if (task.website_urls && task.website_urls.length) {
        task.website_urls.forEach(url => {
          const host = new URL(url).hostname;
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'website-link-btn';
          btn.onclick = () => window.open(url,'_blank');
    
          const favicon = document.createElement('img');
          favicon.src = `https://www.google.com/s2/favicons?domain=${host}`;
          favicon.alt = '';
          favicon.className = 'website-favicon';
          btn.appendChild(favicon);
    
          const span = document.createElement('span');
          span.className = 'website-name';
          span.innerText = host;
          btn.appendChild(span);
    
          userUrlContainer.appendChild(btn);
        });
      } else {
        userUrlContainer.innerHTML += '<p style="color:#777;font-style:italic;font-size:12px;">No URLs provided.</p>';
      }
      // Set hidden inputs
      document.getElementById('uploadTaskId').value = taskId;
      document.getElementById('urlTaskId').value    = taskId;

      // Manage user uploads section
      const form      = document.querySelector('.task-detail-extra-card form');
      const fileInput = form.querySelector('input[type="file"]');
      const urlInput  = form.querySelector('input[type="url"]');
      const btn       = form.querySelector('button[type="submit"]');

      const uploadedFileContainerEl = document.getElementById('uploadedFileContainer');
      const uploadedUrlContainerEl  = document.getElementById('uploadedUrlContainer');
      const uploadedFiles = task.user_uploads_files || [];
      const uploadedUrls  = task.user_uploads_urls  || [];

      // Uploaded Files
      uploadedFileContainerEl.innerHTML = uploadedFiles.length
        ? uploadedFiles.map(u => `
            <button type="button" class="file-preview-btn" onclick="window.open('${u}','_blank')">
              <span class="file-name">${u.split('/').pop()}</span>
            </button>
          `).join('')
        : '<em style="color:#777">No files submitted</em>';

      // Uploaded URLs
      uploadedUrlContainerEl.innerHTML = uploadedUrls.length
        ? uploadedUrls.map(u => {
            const h = new URL(u).hostname;
            return `
            <button type="button" class="website-link-btn" onclick="window.open('${u}','_blank')">
              <img src="https://www.google.com/s2/favicons?domain=${h}" class="website-favicon"/>
              <span class="website-name">${h}</span>
            </button>
            `;
          }).join('')
        : '<em style="color:#777">No URLs submitted</em>';

      // Toggle inputs and button
      if (uploadedFiles.length || uploadedUrls.length) {
        fileInput.disabled = true;
        urlInput.disabled  = true;
        btn.textContent    = 'Unsubmit';
        btn.name           = 'unsubmit_task';
        btn.addEventListener('click', e => {
          e.preventDefault();
          fileInput.disabled = false;
          urlInput.disabled  = false;
          btn.textContent    = 'Upload';
          btn.name           = 'add_task_upload';
        }, { once: true });
      } else {
        fileInput.disabled = false;
        urlInput.disabled  = false;
        btn.textContent    = 'Upload';
        btn.name           = 'add_task_upload';
      }
      openTab(null, 'userTaskDetails');
      
    }
    
      
      function closeTaskDetails() {
    
      const tasksTab = document.getElementById('tab-Tasks');
      openTab({ currentTarget: tasksTab }, 'Tasks');
    }
            // toggle dropdown on click
            document.addEventListener('click', function(e) {
  const toggle = e.target.closest('.td-dropdown-toggle');
  if (toggle) {
    const dd = toggle.parentElement;
    dd.classList.toggle('open');
    return;
  }
  // Click outside? close any open dropdown
  document.querySelectorAll('.task-detail-dropdown.open').forEach(dd => {
    if (!dd.contains(e.target)) dd.classList.remove('open');
  });
});

// Close dropdown when selecting an item
function closeTaskDetailsDropdown(btn) {
  const dd = btn.closest('.task-detail-dropdown');
  dd.classList.remove('open');
}
// reuse the same click‐handler logic
document.addEventListener('click', function(e) {
  const toggle = e.target.closest('.invites-dropdown-toggle');
  if (toggle) {
    const dd = toggle.parentElement;
    dd.classList.toggle('open');
    return;
  }
  // close open invites dropdown if clicking elsewhere
  document.querySelectorAll('.invites-dropdown.open').forEach(dd => {
    if (!dd.contains(e.target)) dd.classList.remove('open');
  });
});
// Toggle the unique profile dropdown
function toggleProfileDropdown() {
  const menu = document.getElementById('profileDropdownMenu');
  menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

// Close dropdown when clicking outside
window.addEventListener('click', function(e) {
  const dropdown = document.querySelector('.profile-dropdown');
  const menu = document.getElementById('profileDropdownMenu');
  if (!dropdown.contains(e.target)) {
    menu.style.display = 'none';
  }
});
function openBannerPopup() {
  document.getElementById('bannerPopup').style.display = 'flex';
}
function closeBannerPopup() {
  document.getElementById('bannerPopup').style.display = 'none';
}
document.addEventListener('DOMContentLoaded', () => {
  const radios  = document.querySelectorAll('.banner-popup__option input[name="banner"]');
  const colorIn = document.getElementById('bannerColorPicker');

  radios.forEach(r => {
    r.addEventListener('change', () => {
      colorIn.disabled = !(r.value === 'custom' && r.checked);
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
