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
  // Fetch every invitation for the current user
  $userSnapshot = $database->getReference("users/{$uid}")->getValue();
  $photoURL = '../assets/img/pics/default-avatar.jpg'; // default if no photo
  
  if ($userSnapshot && !empty($userSnapshot['photoURL'])) {
      require __DIR__ . '/../includes/image_cache.php';
      $photoURL = getCachedProfileImage($userSnapshot['photoURL']);
  }
  $myEmailKey = sanitizeEmail($currentUserEmail);
  $plan = $database->getReference($plansRef)->getValue();
  $isOwner = isset($plan['creator']) && ($plan['creator'] === $uid);
  
  $accepted = [];
  if (!empty($plan['invited']) && is_array($plan['invited'])) {
      foreach ($plan['invited'] as $emailKey => $status) {
          if ($status === 'accepted') {
              $accepted[] = $emailKey;
          }
      }
  }
 
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
  $plan['tasks'] = $tasksWithUploads;
  
  $allowedToComment = $isOwner || in_array($myEmailKey, $accepted);
  $userProfileRef  = $database->getReference("users/{$uid}");
  $userProfile     = $userProfileRef->getValue() ?? [];
  $currentUserName = $userProfile['name'] ?? $currentUserEmail;
  $currentUserPhoto = htmlspecialchars($userSnapshot['photoURL'] ?? '');

  if (!$plan) {
      die("Plan not found.");
  }
  $invitationsRaw = $database
  ->getReference("invitations/{$myEmailKey}")
  ->getValue() ?: [];

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

  $ownerProfile   = $database->getReference("users/{$ownerUid}")->getValue() ?: [];
  $ownerEmailKey  = sanitizeEmail($ownerProfile['email'] ?? '');

  $ownerList      = [$ownerEmailKey => 'Owner'];


  $assistantAdmins = [];
  $collaborators   = [];
  foreach ($inviteeRoles as $emailKey => $trueRole) {
      if ($emailKey === $ownerEmailKey) {
          continue;                
      }
      $normalized = strtolower($trueRole);
      if ($normalized === 'assistant admin') {
          $assistantAdmins[$emailKey] = 'Assistant Admin';
      } else {
          $collaborators[$emailKey]   = $trueRole;
      }
  }
    // before the loop, define the base paths
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

  require __DIR__ . '/../handlers/plans/vmp.php';
  require __DIR__ . '/../handlers/plans/fetchplans.php';
  require_once __DIR__ . '/../popups/popups.php';
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
    <title>Orbitask - My Project</title>
  </head>
  <body>

  <div class="navbar">
          <div class="navbar-left">
            <button class="hamburger" onclick="toggleSidebar()">☰</button>
            <a href="dashboard.php">
            <img src="../assets/img/pics/Logotail.png" alt="Logo" class="logo-image1">
            <img src="../assets/img/pics/logotext.png" alt="Logo" class="logo-image2"> </a>
            <a href="#">> Project Details</a>
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
          <a href="dashboard.php">
            <img src="../assets/img/icons/homeicon.png" alt="" class="sidebar-icon">Home
          </a>
          <a href="calendar.php">
            <img src="../assets/img/icons/calendaricon.png" alt="" class="sidebar-icon">Calendar
          </a>
          <a href="#" class="sidebar-item no-border non-clickable <?= $current === 'viewplan.php' ? 'active' : '' ?>">
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

    <div class="banner" style="<?= $bannerStyle ?>">
              <div class="banner-left">
                <h1 class="plan-title"><?= htmlspecialchars($plan['title'] ?? 'Untitled Plan') ?></h1>
                <p class="plan-status"><strong>Status:</strong> <?= htmlspecialchars($plan['status'] ?? 'In Progress') ?></p>
                <p class="plan-role"><strong>Your Role:</strong> Creator</p>
              </div>
              <div class="banner-right">
                    <div class="banner-right-top">
                      <p><strong>Start Date:</strong> <?= htmlspecialchars($plan['start_date'] ?? '—') ?></p>
                      <p><strong>End Date:</strong> <?= htmlspecialchars($plan['end_date'] ?? '—') ?></p>
                    </div>
                    <div class="banner-right-bottom">
                            <div class="info-dropdown-container">
                            <button class="plus-btn" onclick="togglePlusDropdown()"><span>+</span></button>
                                <button class="info-btn" onclick="toggleInfoDropdown()"><span>i</span></button>
                                <div id="plusDropdown" class="dropdown-content plus-dropdown">
                                  <button class="dropdown-item" onclick="openPopup('announcementPopup')">Add Announcement</button>
                                  <button class="dropdown-item" onclick="openPopup('notesPopup')">Add Note</button>
                                </div>
                                <div id="infoDropdown" class="info-dropdown">
                                      <form method="POST">
                                        <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="status" onchange="this.form.submit()">
                                          <option value="In Progress" <?= (isset($plan['status']) && $plan['status'] === "In Progress") ? "selected" : "" ?>>In Progress</option>
                                          <option value="Completed" <?= (isset($plan['status']) && $plan['status'] === "Completed") ? "selected" : "" ?>>Completed</option>
                                        </select>
                                      </form>
                                      <button class="edit-btn" onclick="toggleNotes()">View Notes</button>
                                  <?php if ($isOwner): ?>
                                    <button class="edit-btn" onclick="openPopup('bannerPopup')">Edit Banner</button>
                                      <form method="POST" onsubmit="return confirm('Are you sure you want to delete this plan?');">
                                        <input type="hidden" name="delete_plan" value="1">
                                        <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                                        <button type="submit" class="delete-btn">Delete Plan</button>
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
                   
                        <?php if ($isOwner): ?>
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
                      <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($uid) ?>">
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
                        <input type="hidden" name="owner_uid" value="<?= htmlspecialchars($uid) ?>">
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
          <?php if ($isOwner): ?>
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
  <li class="section-title">Owner</li>
  <?php foreach ($ownerList as $emailKey => $_role): ?>
    <?php $email = str_replace('_','.',$emailKey); ?>
    <li>
      <span class="people-icon">✉</span>
      <span class="email-text"><?= htmlspecialchars($email) ?></span>
    </li>
  <?php endforeach; ?>

  <?php if (!empty($assistantAdmins)): ?>
    <li class="section-title">Assistant Admin</li>
    <?php foreach ($assistantAdmins as $emailKey => $role): ?>
      <?php $email = str_replace('_','.',$emailKey); ?>
      <li>
        <span class="people-icon">✉</span>
        <span class="email-text"><?= htmlspecialchars($email) ?></span>
        <?php if ($isOwner): ?>
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

  <?php if (!empty($collaborators)): ?>
    <li class="section-title">Collaborators</li>
    <?php foreach ($collaborators as $emailKey => $role): ?>
      <?php $email = str_replace('_','.',$emailKey); ?>
      <li>
        <span class="people-icon">✉</span>
        <span class="email-text"><?= htmlspecialchars($email) ?></span>
        <?php if ($isOwner): ?>
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
                <h3>Project Tasks</h3>
                <?php if ($isOwner): ?>
            <button class="tasks-open-popup-btn" onclick="openPopup('taskPopup')">+ Add Task</button>
            <?php endif; ?>
            </div>
            <div class="card-tasks">
              <?php if (!empty($plan['tasks'])): ?>
                
              <?php foreach ($plan['tasks'] as $taskId => $task): ?>
                <div class="task-card" onclick="showTaskDetails('<?= htmlspecialchars($taskId) ?>')">
                  <div class="task-header">
                    <form method="POST" class="task-form">
                      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                      <input type="hidden" name="owner_uid" value="<?= $uid ?>">
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
              </div>
              
              <div id="TaskDetails" class="tabcontent" style="display:none;">
              <div class="task-tab-header">
                        <h3 id="detailTitle" style="margin: 0;">Task Details</h3>
                      
                        <?php if ($isOwner): ?>
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
                          <form method="POST" class="inline-form" id="finishForm">
                          <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
                          <input type="hidden" name="owner_uid" value="<?= $uid ?>">
                          <input type="hidden" name="task_id" value="<?= htmlspecialchars($taskId) ?>">
                          <button type="submit" name="finish_task" id="finishTaskBtn">Complete/Not</button>
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

            <div class="announcement-files uploads-list" id="uploadsContainer">
              <h4 class="task-card__section-title">Uploads:</h4>
            </div>
          </li>
        </ul>


              </div>
                <!-- Uploads popup -->
                <div class="popup uploads-popup" id="uploadsPopup">
  <div class="popup-content elegant-popup">
    <button class="close-popup" onclick="closePopup('uploadsPopup')">&times;</button>
    <h4 class="popup-title" id="uploadsPopupTitle">Uploads</h4>

    <!-- thumbnails injected here -->
    <div id="uploadsPopupBody" class="uploads-grid"></div>

  </div>
</div>


          <div class="popup" id="assignPopup">
            <div class="popup-content elegant-popup">
              <span class="close-popup" onclick="closePopup('assignPopup')">&times;</span>
              <h4>Assign Task</h4>
              <form method="POST" id="assignForm">
                <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
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
                <input type="hidden" name="task_id" id="editTaskId" value="">

                <label>Name:</label>
                <input
                    type="text"
                    name="task_name"
                    required
                    value="<?= htmlspecialchars($task['name'] ?? '') ?>"
                  id="editTaskName">
                  <label for="editTaskDescription">Description:</label>
                  
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
                <input type="hidden" name="task_id" id="deleteTaskId" value="">
                <button type="submit" name="delete_task" class="deletebutton">Delete</button>
                <button type="button" class="cancelbutton" onclick="closePopup('deleteTaskModal')">Cancel</button>
              </form>
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
        // toggle dropdown on click
document.addEventListener('click', function(e) {
  const toggle = e.target.closest('.td-dropdown-toggle');
  if (toggle) {
    const dd = toggle.parentElement;
    dd.classList.toggle('open');
    return;
  }
  // click outside? close any open dropdown
  document.querySelectorAll('.task-detail-dropdown.open').forEach(dd => {
    if (!dd.contains(e.target)) dd.classList.remove('open');
  });
});
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
