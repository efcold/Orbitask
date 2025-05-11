  <?php
  ob_start();
  session_start();

  if (!isset($_SESSION["uid"])) {
      header("Location: ../auth/login.php");
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
  $myEmailKey = sanitizeEmail($currentUserEmail);
  $plan = $database->getReference($plansRef)->getValue();
  $isOwner = isset($plan['creator']) && ($plan['creator'] === $uid);
  $userSnapshot = $database->getReference("users/{$uid}")->getValue();
  $photoURL = '../assets/img/pics/default-avatar.jpg'; // default if no photo
  
  if ($userSnapshot && !empty($userSnapshot['photoURL'])) {
      require __DIR__ . '/../includes/image_cache.php';
      $photoURL = getCachedProfileImage($userSnapshot['photoURL']);
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
  $allowedToComment = $isOwner || in_array($myEmailKey, $accepted);
  $userProfileRef  = $database->getReference("users/{$uid}");
  $userProfile     = $userProfileRef->getValue() ?? [];
  $currentUserName = $userProfile['name'] ?? $currentUserEmail;
  if (!$plan) {
    die("Plan not found.");
  }
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
  require __DIR__ . '/../handlers/plans/vip.php';
  require __DIR__ . '/../handlers/plans/fetchplans.php';
  if (isset($planId, $ownerUid)) {
    $filteredInvitedPlans = array_filter($invitedPlans, function($plan) use ($planId, $ownerUid) {
        return $plan['plan_id'] === $planId && $plan['owner'] === $ownerUid;
    });
    $plan = !empty($filteredInvitedPlans) ? reset($filteredInvitedPlans) : null;
  } else {
    $plan = null;
  }
  $currentUserPhoto = htmlspecialchars($userSnapshot['photoURL'] ?? '');
  $current = basename($_SERVER['PHP_SELF']);

  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <!-- <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/navbar.css"> -->
    <link rel="stylesheet" href="../assets/css/invites.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans+Text:wght@400;500;700">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github.min.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/languages/php.min.js"></script>
    <script>hljs.highlightAll();</script>
    <script src="../assets/js/togglesidebar.js"></script>
    <script src="../assets/js/toggletabs.js"></script>
    <script src="../assets/js/sc-invites.js"></script>
    <script>
        var tasks = <?= json_encode($userTasks) ?>;
        console.log("All tasks loaded:", tasks);
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
  <link rel="icon" type="image/png" href="../assets/img/pics/Logotail.png">
  <title>Orbitask - Share Project</title>
  </head>
  <body>

  <div class="navbar">
  <div class="navbar-left">
    <button class="hamburger" onclick="toggleSidebar()">☰</button>
    <a href="dashboard.php">
   <img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
   <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2"> </a>
  <a href="#">> Shared Project Details</a>
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
          <a href="dashboard.php">
            <img src="../assets/img/icons/homeicon.png" alt="" class="sidebar-icon">Home
          </a>
          <a href="calendar.php">
            <img src="../assets/img/icons/calendaricon.png" alt="" class="sidebar-icon">Calendar
          </a>
          <a  href="#" class="sidebar-item no-border non-clickable <?= $current === 'invites.php' ? 'active' : '' ?>">
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
              <a href="#" id="tab-Tasks" class="tablinks" onclick="openTab(event, 'Tasks')">Tasks</a>
              <a href="#" class="tablinks" onclick="openTab(event, 'Invites')">People</a>
          </div>
  <div>
    
        <div class="main-content">
      <?php if ($plan): ?>
        <div class="banner" style="<?= $bannerStyle ?>">
          <div class="banner-left">
            <h4 class="plan-title"><?= htmlspecialchars($plan['title'] ?? 'Untitled') ?></h4>
            <p class="plan-status"><strong>Status:</strong> <?= htmlspecialchars($plan['status'] ?? 'In Progress') ?></p>
            <p class="plan-role"><strong>Your Role:</strong> Collaborator</p>
          </div>
          <div class="banner-right">
            <div class="banner-right-top">
              <p><strong>Start Date:</strong> <?= htmlspecialchars($plan['start_date'] ?? '—') ?></p>
              <p><strong>End Date:</strong> <?= htmlspecialchars($plan['end_date'] ?? '—') ?></p>
            </div>
            <div class="banner-right-bottom">
                  <div class="info-dropdown-container">
                      <button class="info-btn" onclick="toggleInfoDropdown()">i</button>
                          <div id="infoDropdown" class="info-dropdown">     
                          <button class="edit-btn" onclick="toggleNotes()">View Notes</button>               
                              <form method="POST" onsubmit="return confirm('Are you sure you want to leave this plan?');">
                              <input type="hidden" name="plan_id" value="<?= htmlspecialchars($plan['plan_id']) ?>">
                              <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($plan['owner']) ?>">
                              <input type="hidden" name="invite_key" value="<?= htmlspecialchars($plan['invite_key']) ?>">
                              <button type="submit" class="leave-btn" name="leave_plan" >Leave Plan</button>
                            </form>
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
                              class="announcement-avatar"/>
                            <div class="announcement-meta">
                              <span class="announcement-author">
                                <?= htmlspecialchars($announcement['authorName'] ?? $announcement['authorEmail']) ?>
                              </span>
                              <em class="announcement-timestamp">
                                <?= date('F j, Y g:i a', $announcement['timestamp']) ?>
                              </em>
                          </div>                   
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
        // Count comments and track index for hiding extras
        $commentCount = count($announcement['comments']);
        $i = 0;
      ?>
      <?php foreach ($announcement['comments'] as $commentId => $comment): ?>
        <?php 
          // For hiding beyond the first comment
          $hiddenClass = ($i >= 1) ? 'hidden-comment' : '';
          // Determine if current user is the author
          $commentAuthorEmail = $comment['authorEmail'] ?? '';
          $isCommentOwner = (trim(strtolower($commentAuthorEmail)) === trim(strtolower($currentUserEmail)));
        ?>
        <li class="comment-item <?= $hiddenClass ?>">
          <img
            src="<?= htmlspecialchars($comment['authorPhoto'] ?? '/assets/img/default-avatar.png') ?>"
            alt="Commenter Avatar"
            class="comment-avatar"
          />
          <div class="comment-details">
            <strong><?= htmlspecialchars($comment['authorName'] ?? '') ?></strong>
            <em><?= date('F j, Y g:i a', $comment['timestamp']) ?></em>
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
              <div class="popup-content">
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
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-announcement-message">
                <p>No announcements yet.</p>
              </div>
    <?php endif; ?>
  </ul>
  </div>

  <?php endif; ?>
  <div class="card card-notes">
      <div class="card-header">
        <h4>Notes</h4>
        <?php if ($plan): ?>
          <button class="open-popup-btn" onclick="openPopup('notesPopup')">+ Add Note</button>
        <?php endif; ?>
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
              
              <?php
                $noteEmail = isset($note['authorEmail']) ? trim(strtolower($note['authorEmail'])) : '';
                $currentEmail = trim(strtolower($currentUserEmail));

                if ($noteEmail === $currentEmail):
                ?>

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
                  <div class="popup-content">
                    <form method="post">
                      <p>Edit Note</p>
                      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                      <input type="hidden" name="note_id" value="<?= htmlspecialchars($noteId) ?>">
                      <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
                      <textarea name="edited_note_text"><?= htmlspecialchars($note['text']) ?></textarea>
                      <button type="submit" name="edit_note">Save</button>
                      <button type="button" onclick="closePopup('editNotePopup-<?= $noteId ?>')">Cancel</button>
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
            <?php if ($isOwner): ?>
              <div class="invites-buttons">
                <button class="invites-open-popup-btn" onclick="openPopup('invitePopup')">+ Invite People</button>
                <button class="invites-open-popup-btn" onclick="openPopup('pendingPopup')">View Pending Invites</button>
              </div>
            <?php endif; ?>
          </div>
            <!-- Owner -->
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
        <?php if ($plan): ?>
          <form method="POST" class="remove-invite-form">
            <input type="hidden" name="plan_id"  value="<?= htmlspecialchars($planId) ?>">
            <input type="hidden" name="email_key" value="<?= htmlspecialchars($emailKey) ?>">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?= urlencode($email) ?>"
               target="_blank"  class="send-email-btn" title="Email  <?= htmlspecialchars($email) ?>">
              <i class="fas fa-envelope"></i>
            </a>
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
        <?php if ($plan): ?>
          <form method="POST" class="remove-invite-form">
            <input type="hidden" name="plan_id"  value="<?= htmlspecialchars($planId) ?>">
            <input type="hidden" name="email_key" value="<?= htmlspecialchars($emailKey) ?>">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?= urlencode($email) ?>"
               target="_blank" class="send-email-btn" title="Email <?= htmlspecialchars($email) ?>">
              <i class="fas fa-envelope"></i>
            </a>
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
                  <h3>Project Tasks</h3>
              </div>
          <div class="card-tasks">
          <?php if (!empty($userTasks)): ?>
            <?php foreach ($userTasks as $taskId => $task): ?>
                  <div class="task-card" onclick="showTaskDetails('<?= htmlspecialchars($taskId) ?>')">
                    <div class="task-header">
                      <form method="POST" class="task-form">
                        <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                        <input type="hidden" name="owner_uid" value="<?= $uid ?>">
                        <input type="hidden" name="task_id" value="<?= $taskId ?>">
                        <span class="task-name"><?= htmlspecialchars($task['name']) ?></span>
                        <?php 
                          if (!empty($task['due_date']) && !empty($task['due_time'])):
                            // parse into a Unix timestamp
                            $dueTs = strtotime($task['due_date'] . ' ' . $task['due_time']);
                        ?>
                          <span class="task-due">
                            (Due: <?= date('F j, Y', $dueTs) ?> at <?= date('g:i a', $dueTs) ?>)
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
                

            <!-- Updated TaskDetails layout: two-column card -->
  <div id="TaskDetails" class="tabcontent" style="display:none;">
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
    <p><strong></strong> <span id="taskDetailName"></span></p>
    <p><strong></strong> <span id="taskDetailDescription">—</span></p>
   
    <div id="fileContainer" class="announcement-files detail-files">
      <strong>Files:</strong>
      <!-- JS injects file buttons -->
    </div>
    <div id="urlContainer" class="announcement-website detail-urls">
      <strong>Website URLs:</strong>
      <!-- JS injects URL buttons -->
    </div>
  </div>

  <p class="task-card__meta">
      <span hidden id="taskDetailCompleted"></span><br>
      Due Date: <span id="taskDetailDueDate"></span><br>
      at <span id="taskDetailDueTime"></span>
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




  <div class="popup" id="notesPopup">
    <div class="popup-content">
    <h4>Add Notes</h4>
      <span class="close-popup" onclick="closePopup('notesPopup')">&times;</span>
      <form method="POST">
        <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
        <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($ownerUid) ?>">
        <textarea name="note_text" placeholder="Write your note..." required></textarea>
        <button type="submit" name="add_note">Add Note</button>
      </form>
    </div>
  </div>





  <script>
  function openPopup(id) {
    document.getElementById(id).style.display = 'block';
  }
  function closePopup(id) {
    document.getElementById(id).style.display = 'none';
  }
  function toggleInfoDropdown() {
    const dropdown = document.getElementById('infoDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
  }
  document.addEventListener('click', function(event) {
    const button = document.querySelector('.info-btn');
    const dropdown = document.getElementById('infoDropdown');
    if (!button.contains(event.target) && !dropdown.contains(event.target)) {
      dropdown.style.display = 'none';
    }
  });
  function toggleDropdown(id) {
    document
      .getElementById(id)
      .classList
      .toggle('show');
  }
  document.addEventListener('click', function(event) {
    // if the click isn’t inside an announcement _or_ a comment, hide all open menus
   if (      !event.target.closest('.announcement-actions') &&
      !event.target.closest('.comment-actions')
    ) {
      document.querySelectorAll('.dropdown-menu.show')
        .forEach(menu => menu.classList.remove('show'));
    }
  });
  function toggleComments(btn) {
    // Find the closest comment list (assuming the button is immediately after it)
    const commentList = btn.parentElement;           // <ul class="comment-list">
    const hiddenComments = commentList.querySelectorAll('.hidden-comment');
    const isVisible = btn.getAttribute('data-visible') === 'true';

    // Check the current state
    if (btn.getAttribute('data-visible') === 'true') {
      // Hide extra comments
      hiddenComments.forEach(li => {
    // when showing, clear the inline style so it falls back to default (display: list-item)
    li.style.display = isVisible ? 'none' : '';
  });
  btn.textContent = isVisible ? 'View All Comments' : 'Hide All Comments';
  btn.setAttribute('data-visible', isVisible ? 'false' : 'true');
    } else {
      // Show all extra comments
      hiddenComments.forEach(comment => {
        comment.style.display = 'flex';  // or "block" depending on your layout
      });
      btn.innerText = 'Hide All Comments';
      btn.setAttribute('data-visible', 'true');
    }
  }
  function showTaskDetails(taskId) {
      const task = tasks[taskId];
      if (!task) return alert("Task not found.");
    
        console.log("Populating details for", taskId, task);
    
        document.getElementById("taskDetailName").textContent =
          task.name || "—";
        document.getElementById("taskDetailCompleted").textContent =
          task.completed ? "Yes" : "No";
          document.getElementById('taskDetailDescription').textContent =
          task.description || '—';
    if (task.due_date && task.due_time) {
      // build an ISO‐ish string that Date can parse
      const iso = `${task.due_date}T${task.due_time}`;
      const dt  = new Date(iso);

      // format the date: "April 18, 2025"
      const optionsDate = { year: 'numeric', month: 'long', day: 'numeric' };
      const formattedDate = dt.toLocaleDateString('en-US', optionsDate);

      // format the time: "3:27 pm"
      const optionsTime = { hour: 'numeric', minute: '2-digit', hour12: true };
      const formattedTime = dt.toLocaleTimeString('en-US', optionsTime);

      document.getElementById("taskDetailDueDate").textContent = formattedDate;
      document.getElementById("taskDetailDueTime").textContent = formattedTime;
    } else {
      document.getElementById("taskDetailDueDate").textContent = 'N/A';
      document.getElementById("taskDetailDueTime").textContent = 'N/A';
    }
    
    
      const fileContainer = document.getElementById('fileContainer');
      fileContainer.innerHTML = ''; 
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
    
          fileContainer.appendChild(btn);
        });
      } else {
        fileContainer.innerHTML += '<p style="color:#777;font-style:italic;font-size:12px;">No files attached.</p>';
      }
    
    
      const urlContainer = document.getElementById('urlContainer');
      urlContainer.innerHTML = '';
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
    
          urlContainer.appendChild(btn);
        });
      } else {
        urlContainer.innerHTML += '<p style="color:#777;font-style:italic;font-size:12px;">No URLs provided.</p>';
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
      openTab(null, 'TaskDetails');
      
    }
    
      
      function closeTaskDetails() {
    
      const tasksTab = document.getElementById('tab-Tasks');
      openTab({ currentTarget: tasksTab }, 'Tasks');
    }

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
