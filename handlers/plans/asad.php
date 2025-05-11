<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    return;
}

if (isset($_POST['update_status'])) {
    $planId = $_POST['plan_id'];
    $ownerUid  = $_POST['owner_uid'];
    $newStatus = $_POST['status']; 
    $planRef = $database->getReference("users/{$ownerUid}/plans/{$planId}");
    $planRef->update([
        "status" => $newStatus
    ]);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
if (isset($_POST['edit_banner'])) {
    $planId = $_POST['plan_id'];
    $ownerUid  = $_POST['owner_uid'];
if (isset($_POST['banner']) && $_POST['banner'] === 'custom'
&& !empty($_POST['banner_color'])
) {
    $banner = $_POST['banner_color'];
}
elseif (!empty($_POST['banner']) && $_POST['banner'] !== 'custom') {
    $banner = $_POST['banner'];
}
else {
    $banner = '';
}


$newPlanRef = $database->getReference("users/{$ownerUid}/plans/{$planId}");
$newPlanRef->update([
    "banner"     => $banner,    
]);
header('Location: ' . $_SERVER['REQUEST_URI']);

exit();
}
if (isset($_POST['finish_task'])) {
    $planId   = $_POST['plan_id'];
    $taskId   = $_POST['task_id'];
    $ownerUid = $_POST['owner_uid'] ?? $uid;
    $planPath = "users/{$ownerUid}/plans/{$planId}/tasks/{$taskId}";
    $taskRef  = $database->getReference($planPath);
    $taskData = $taskRef->getValue();

    if ($taskData !== null) {
        $currentStatus = $taskData['completed'] ?? false;
        $taskRef->update(['completed' => !$currentStatus]);
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\OAuth;
use League\OAuth2\Client\Provider\Google;
/**
 * Send a notification email to all invited collaborators on a plan.
 *
 * @param string $ownerUid   The UID of the plan‐owner
 * @param string $planId     The plan ID
 * @param string $subject    Email subject
 * @param string $htmlBody   HTML body
 */
function notifyInvited(string $ownerUid, string $planId, string $subject, string $htmlBody): void {
    global $database;  
    $invitedRef = $database->getReference("users/{$ownerUid}/plans/{$planId}/invited");
    $invited    = $invitedRef->getValue() ?? [];

    if (empty($invited)) {
        return; 
    }
  $emails = [];
  foreach ($invited as $emailKey => $_role) {
      $realEmail = str_replace('_', '.', $emailKey);
      $emails[]   = $realEmail;
      error_log("[notifyInvited]   will e-mail “{$realEmail}” (key: {$emailKey})");
  }


    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->Port       = 587;
    $mail->SMTPSecure = 'tls';
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
    $mail->addReplyTo('orbitaskplanner@gmail.com', 'Orbitask Digital Planner');
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;

    foreach ($emails as $to) {
        try {
            $mail->clearAddresses();
            $mail->addAddress($to);
            $mail->send();
        } catch (Exception $e) {
            error_log("Notification to {$to} failed: {$mail->ErrorInfo}");
        }
    }
}
/**
 * Send a notification email to an arbitrary list of addresses
 * whenever they’re assigned to a task.
 *
  * @param string      $ownerUid      UID of the plan‐owner
 * @param string      $planId        Plan ID
 * @param string      $taskName      Human‐readable task name
 * @param string[]    $emails        List of real e-mail addresses
 * @param string      $subjectPrefix Full subject (will *not* get “: $taskName” appended)
 * @param string|null $htmlBody      Full HTML body (if null, use built-in template)
 */
function notifyAssigned(
    string $ownerUid,
    string $planId,
    string $taskName,
    array $emails,
    string $subjectPrefix = '',
    ?string $htmlBody = null
): void {
    if (empty($emails)) {
        return;
    }

    $validEmails = [];
    foreach ($emails as $raw) {
        $candidate = str_replace('_', '.', trim($raw));
        if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $validEmails[] = $candidate;
            error_log("[notifyAssigned] will e-mail “{$candidate}”");
        }
    }
    if (empty($validEmails)) {
        return;
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->Port       = 587;
    $mail->SMTPSecure = 'tls';
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
    $mail->addReplyTo('orbitaskplanner@gmail.com', 'Orbitask Digital Planner');
    $mail->isHTML(true);
    $fullSubject = $subjectPrefix;
    $body = $htmlBody ?? "
        <h3>Task Assigned: {$taskName}</h3>
        <p>Hello,</p>
        <p>You have been assigned the task <strong>“{$taskName}”</strong>.</p>
        <p><a href='https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$ownerUid}'>
          View it in Orbitask Digital Planner →
        </a></p>
        <p>Thanks!</p>
    ";
    foreach ($validEmails as $to) {
        try {
            $mail->clearAddresses();
            $mail->addAddress($to);
            $mail->Subject = $fullSubject;
            $mail->Body    = $body;
            $mail->send();
        } catch (Exception $e) {
            error_log("notifyAssigned failed for {$to}: {$mail->ErrorInfo}");
        }
    }
}
if (isset($_POST['add_task'])) {
    $planId    = $_POST['plan_id'];
    $ownerUid  = $_POST['owner_uid'];
    $taskName  = trim($_POST['task_name'] ?? '');
    $dueDate   = $_POST['due_date'] ?? '';
    $dueTime   = $_POST['due_time'] ?? '';
    $taskDescription = trim($_POST['task_description'] ?? '');
  $rawAssigned       = $_POST['assigned_to'] ?? [];
  $sanitizedAssigned = array_map('htmlspecialchars', $rawAssigned);

  if ($taskName !== '') {
      $taskData = [
          'name'        => htmlspecialchars($taskName),
          'due_date'    => htmlspecialchars($dueDate),
          'due_time'    => htmlspecialchars($dueTime),
          'completed'   => false,
          'description' => $taskDescription !== ''
                              ? htmlspecialchars($taskDescription)
                              : null,
          'assigned_to' => $sanitizedAssigned,
      ];

        $taskFileUrls = [];
        if (!empty($_FILES['task_files']['tmp_name']) && is_array($_FILES['task_files']['tmp_name'])) {        
            $baseUploadDir = __DIR__ . '/../uploads/tasks/' . $planId . '/';
            if (!is_dir($baseUploadDir)) {
                mkdir($baseUploadDir, 0755, true);
            }
            foreach ($_FILES['task_files']['tmp_name'] as $i => $tmpPath) {
                if (is_uploaded_file($tmpPath) && $_FILES['task_files']['error'][$i] === UPLOAD_ERR_OK) {
                  
                    $origName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES['task_files']['name'][$i]));
                    $filename = time() . "_" . $origName;
                    $destination = $baseUploadDir . $filename;
                    if (move_uploaded_file($tmpPath, $destination)) {
                      
                        $taskFileUrls[] = "/ms-projectmonitoring/handlers/uploads/tasks/{$planId}/{$filename}";
                    }
                }
            }
        }
        if (!empty($taskFileUrls)) {
            $taskData['files'] = $taskFileUrls;
        }

      
        if (isset($_POST['task_urls']) && is_array($_POST['task_urls'])) {
            $taskUrls = [];
            foreach ($_POST['task_urls'] as $url) {
                $sanitizedUrl = filter_var(trim($url), FILTER_SANITIZE_URL);
                if (!empty($sanitizedUrl)) {
                    $taskUrls[] = $sanitizedUrl;
                }
            }
            if (!empty($taskUrls)) {
                $taskData['website_urls'] = $taskUrls;
            }
        }

     
        $taskRef = $database
            ->getReference("users/{$ownerUid}/plans/{$planId}/tasks")
            ->push();
        $taskRef->set($taskData);

     
        $subject = "New Task Assigned: {$taskData['name']}";
        $viewUrl = "https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$uid}";
        $fullDesc    = $taskDescription !== '' ? htmlspecialchars($taskDescription) : 'No description provided.';
        $previewDesc = mb_strlen($fullDesc) > 120
            ? mb_substr($fullDesc, 0, 120) . '…'
            : $fullDesc;

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
  <style>
    body { margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f4; }
    .email-wrapper { width:100%; padding:20px; background:#f4f4f4; }
    .logo-container { text-align:center; margin-bottom:20px; }
    .logo { max-width:150px; height:auto; }
    .card {
      max-width:600px;
      margin:0 auto;
      background:#ffffff;
      border-radius:8px;
      overflow:hidden;
      box-shadow:0 2px 6px rgba(0,0,0,0.15);
    }
    .header {
      padding:16px;
      border-bottom:1px solid #eee;
      display:flex;
      align-items:center;
    }
    .author-photo {
      width:48px; height:48px;
      border-radius:50%;
      object-fit:cover;
      margin-right:12px;
    }
    .author-name {
      font-size:16px;
      font-weight:600;
      color:#333;
    }
    .body { padding:16px; color:#555; line-height:1.5; }
    .detail { margin-bottom:12px; }
    .button {
      display:inline-block;
      padding:10px 18px;
      background:#1a73e8;
      color:#fff;
      text-decoration:none;
      border-radius:4px;
      font-size:14px;
    }
    .footer {
      padding:12px 16px;
      font-size:12px;
      color:#888;
      text-align:center;
      border-top:1px solid #eee;
    }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="logo-container">
      <img src="https://lh3.googleusercontent.com/a/ACg8ocI-BAg75A37XiOE-p8OY3N7UFITfTrrPLAgg-LJz8V1UnYjTKkyXRoOgHWC96dGvO30QPHsWyqBjABBNXjb7Xmj2972ThI=s260-c-no" alt="Orbitask Digital Planner Logo" class="logo">
    </div>
    <div class="card">
      <div class="header">
        <img src="{$currentUserPhoto}" alt="{$currentUserName}" class="author-photo">
        <div class="author-name">{$currentUserName} assigned a new task</div>
      </div>
      <div class="body">
        <div class="detail"><strong>Task:</strong> {$taskData['name']}</div>
        <div class="detail"><strong>Due:</strong> {$taskData['due_date']} {$taskData['due_time']}</div>
        <div class="detail"><strong>Description:</strong> {$previewDesc}</div>
        <a href="{$viewUrl}" class="button">View Task →</a>
      </div>
      <div class="footer">
        You received this email because you are assigned to this plan.<br>
        © Orbitask
      </div>
    </div>
  </div>
</body>
</html>
HTML;

        notifyAssigned(
            $ownerUid,
            $planId,
            $taskData['name'],
            $rawAssigned,
            $subject,
            $html
        );
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

if (isset($_POST['edit_task'])) {
    $planId     = $_POST['plan_id'];
    $ownerUid  = $_POST['owner_uid'];
    $taskId     = $_POST['task_id'];
    $taskRef      = $database->getReference("users/{$ownerUid}/plans/{$planId}/tasks/{$taskId}");
    $existingTask = $taskRef->getValue() ?: [];

    $newName    = trim($_POST['task_name'] ?? '');
    $newDesc    = trim($_POST['task_description'] ?? '');
    $newDueDate = $_POST['due_date']  ?? '';
    $newDueTime = $_POST['due_time']  ?? '';

 
    $changes = [];
    if ($newName !== ($existingTask['name'] ?? '')) {
        $old = htmlspecialchars($existingTask['name'] ?? '');
        $changes[] = "Name: {$old} → {$newName}";
    }
    if ($newDesc !== ($existingTask['description'] ?? '')) {
      $oldDesc = htmlspecialchars($existingTask['description'] ?? '');
      $changes[] = "Description changed";
     }
    if (
        $newDueDate !== ($existingTask['due_date'] ?? '')
     || $newDueTime !== ($existingTask['due_time'] ?? '')
    ) {
        $oldDT = htmlspecialchars(($existingTask['due_date'] ?? '') . ' ' . ($existingTask['due_time'] ?? ''));
        $newDT = htmlspecialchars("{$newDueDate} {$newDueTime}");
        $changes[] = "Deadline: {$oldDT} → {$newDT}";
    }
    $rawAssigned = $_POST['assigned_to'] ?? [];
    if (empty($rawAssigned) && !empty($existingTask['assigned_to'])) {
        $rawAssigned = $existingTask['assigned_to'];
    }
    $sanitizedAssigned = array_map('htmlspecialchars', $rawAssigned);
   $taskData = [
    'name'         => htmlspecialchars($newName),
    'description'  => htmlspecialchars($newDesc),
    'due_date'     => htmlspecialchars($newDueDate),
    'due_time'     => htmlspecialchars($newDueTime),
    'assigned_to'  => $sanitizedAssigned,
];



  
    $newFileUrls = [];
    if (!empty($_FILES['task_files']['tmp_name']) && is_array($_FILES['task_files']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../uploads/tasks/' . $planId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        foreach ($_FILES['task_files']['tmp_name'] as $i => $tmpPath) {
            if (is_uploaded_file($tmpPath) && $_FILES['task_files']['error'][$i] === UPLOAD_ERR_OK) {
                $orig      = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES['task_files']['name'][$i]));
                $filename  = time() . "_" . $orig;
                $dest      = $uploadDir . $filename;
                if (move_uploaded_file($tmpPath, $dest)) {
                    $newFileUrls[] = "/ms-projectmonitoring/handlers/uploads/tasks/{$planId}/{$filename}";
                }
            }
        }
    }
    if (!empty($newFileUrls)) {
        
        $taskData['files'] = $newFileUrls;
    } elseif (isset($existingTask['files'])) {
        $taskData['files'] = $existingTask['files'];
    }

   
    if (!empty($_POST['task_urls']) && is_array($_POST['task_urls'])) {
        $clean = [];
        foreach ($_POST['task_urls'] as $url) {
            $u = filter_var(trim($url), FILTER_SANITIZE_URL);
            if ($u) $clean[] = $u;
        }
        $taskData['website_urls'] = $clean;
    } elseif (isset($existingTask['website_urls'])) {
        $taskData['website_urls'] = $existingTask['website_urls'];
    }


    
    $taskRef->update($taskData);
    if (!empty($changes)) {
        $subject = "Task Updated: {$newName}";
        $viewUrl = "https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$uid}";

        $bulletHtml = '<ul><li>' . implode('</li><li>', $changes) . '</li></ul>';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
  <style>
    body { margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f4; }
    .email-wrapper { width:100%; padding:20px; background:#f4f4f4; }
    .logo-container { text-align:center; margin-bottom:20px; }
    .logo { max-width:150px; height:auto; }
    .card { max-width:600px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.15); }
    .header { padding:16px; border-bottom:1px solid #eee; display:flex; align-items:center; }
    .author-photo { width:48px; height:48px; border-radius:50%; object-fit:cover; margin-right:12px; }
    .author-name { font-size:16px; font-weight:600; color:#333; }
    .body { padding:16px; color:#555; line-height:1.5; }
    .changes { margin-bottom:16px; }
    .button { display:inline-block; padding:10px 18px; background:#1a73e8; color:#fff; text-decoration:none; border-radius:4px; font-size:14px; }
    .footer { padding:12px 16px; font-size:12px; color:#888; text-align:center; border-top:1px solid #eee; }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="logo-container">
      <img src="https://lh3.googleusercontent.com/a/ACg8ocI-BAg75A37XiOE-p8OY3N7UFITfTrrPLAgg-LJz8V1UnYjTKkyXRoOgHWC96dGvO30QPHsWyqBjABBNXjb7Xmj2972ThI=s260-c-no" alt="Orbitask Digital Planner Logo" class="logo">
    </div>
    <div class="card">
      <div class="header">
        <img src="{$currentUserPhoto}" alt="{$currentUserName}" class="author-photo">
        <div class="author-name">{$currentUserName} updated a task</div>
      </div>
      <div class="body">
        <div class="changes">{$bulletHtml}</div>
        <a href="{$viewUrl}" class="button">View in Orbitask Digital Planner →</a>
      </div>
      <div class="footer">
        You received this email because you are assigned to this plan.<br>
        © Orbitask
      </div>
    </div>
  </div>
</body>
</html>
HTML;

        notifyAssigned(
            $ownerUid,
            $planId,
            $newName,
            $rawAssigned,
            $subject,
            $html
        );
    }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;

}

if (isset($_POST['delete_task'])) {
    $planId = $_POST['plan_id'];
    $taskId = $_POST['task_id'];
    $ownerUid  = $_POST['owner_uid'];

 
    $database
      ->getReference("users/{$ownerUid}/plans/{$planId}/tasks/{$taskId}")
      ->remove();

      header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
if (isset($_POST['assign_task'])) {
    $planId    = $_POST['plan_id'];
    $taskId    = $_POST['task_id'];
    $ownerUid  = $_POST['owner_uid'];

    $rawAssigned = $_POST['assigned_to'] ?? [];
    $sanitized   = array_map('htmlspecialchars', $rawAssigned);

    $taskRef = $database->getReference("users/{$ownerUid}/plans/{$planId}/tasks/{$taskId}");
    $taskRef->update([ "assigned_to" => $sanitized ]);
    $taskValue    = $taskRef->getValue();
    $taskName     = $taskValue['name'] ?? 'Your Task';
    $taskDesc     = $taskValue['description'] ?? 'No description provided.';
    $viewUrl      = "https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$uid}";
    $subject      = "Assigned to Task: {$taskName}";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
  <style>
    body { margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f4; }
    .email-wrapper { width:100%; padding:20px; background:#f4f4f4; }
    .logo-container { text-align:center; margin-bottom:20px; }
    .logo { max-width:150px; height:auto; }
    .card { max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.15); }
    .header { padding:16px; border-bottom:1px solid #eee; display:flex; align-items:center; }
    .author-photo { width:48px; height:48px; border-radius:50%; object-fit:cover; margin-right:12px; }
    .author-name { font-size:16px; font-weight:600; color:#333; }
    .body { padding:16px; color:#555; line-height:1.5; }
    .detail, .changes { margin-bottom:12px; }
    .button { display:inline-block; padding:10px 18px; background:#1a73e8; color:#fff; text-decoration:none; border-radius:4px; font-size:14px; }
    .footer { padding:12px 16px; font-size:12px; color:#888; text-align:center; border-top:1px solid #eee; }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="logo-container">
      <img src="https://lh3.googleusercontent.com/a/ACg8ocI-BAg75A37XiOE-p8OY3N7UFITfTrrPLAgg-LJz8V1UnYjTKkyXRoOgHWC96dGvO30QPHsWyqBjABBNXjb7Xmj2972ThI=s260-c-no" alt="Orbitask Digital Planner Logo" class="logo">
    </div>
    <div class="card">
      <div class="header">
        <img src="{$currentUserPhoto}" alt="{$currentUserName}" class="author-photo">
        <div class="author-name">{$currentUserName} assigned you a task</div>
      </div>
      <div class="body">
        <div class="detail"><strong>Task:</strong> {$taskName}</div>
        <div class="detail"><strong>Description:</strong> {$taskDesc}</div>
        <a href="{$viewUrl}" class="button">Check it in Orbitask Digital Planner →</a>
      </div>
      <div class="footer">
        You received this email because you are assigned to this plan.<br>
        © Orbitask
      </div>
    </div>
  </div>
</body>
</html>
HTML;

    notifyAssigned(
        $ownerUid,
        $planId,
        $taskName,
        $rawAssigned,
        $subject,
        $html
    );

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

if (isset($_POST['add_comment'])) {
    $planId = $_POST['plan_id'];
    $announcementId = $_POST['announcement_id'];
    $announcementOwnerUid = $_POST['owner_uid'];
    $commentText = trim($_POST['comment_text']);
    
    if (!empty($commentText)) {
      
        $commentRef = $database->getReference("users/{$announcementOwnerUid}/plans/{$planId}/announcements/{$announcementId}/comments")->push();
        $commentRef->set([
            'authorEmail'   => htmlspecialchars($currentUserEmail),
            'authorName'    => htmlspecialchars($currentUserName),
            'authorPhoto'   => htmlspecialchars($currentUserPhoto),
            'text'          => htmlspecialchars($commentText),
            'timestamp'     => time()
        ]);
    }
    
  
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
if (isset($_POST['edit_comment'])) {
    $planId          = $_POST['plan_id'];
    $announcementId  = $_POST['announcement_id'];
    $commentId       = $_POST['comment_id'];
    $commentOwnerUid = $_POST['owner_uid'] ?? $uid;
    $editedText      = trim($_POST['edited_comment_text']);

    if ($editedText !== '') {
        $commentPath = "users/{$commentOwnerUid}/plans/{$planId}/announcements/{$announcementId}/comments/{$commentId}";
        $database
          ->getReference($commentPath)
          ->update([
              'text'      => htmlspecialchars($editedText),
              'timestamp' => time(),  
          ]);
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
if (isset($_POST['delete_comment'])) {
    $planId          = $_POST['plan_id'];
    $announcementId  = $_POST['announcement_id'];
    $commentId       = $_POST['comment_id'];
    $commentOwnerUid = $_POST['owner_uid'] ?? $uid;

    $commentPath = "users/{$commentOwnerUid}/plans/{$planId}/announcements/{$announcementId}/comments/{$commentId}";
    $database->getReference($commentPath)->remove();

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

if (isset($_POST['invite_user'])) {
    $planId = $_POST['plan_id'];
    $planTitle = $database
    ->getReference("users/{$ownerUid}/plans/{$planId}/title")
    ->getValue() ?? 'your plan';
    $ownerUid = $_POST['owner_uid'];
    $inviteEmail = $_POST['invite_email'];
    $inviteRole = $_POST['invite_role']; 
    $inviteEmailKey = sanitizeEmail($inviteEmail);
    $invitedRef = $database->getReference("users/{$ownerUid}/plans/{$planId}/invited/{$inviteEmailKey}");
    $alreadyInvited = $invitedRef->getValue();

    if (!$alreadyInvited) {
        $invitedRef->set($inviteRole);
        $invitationData = [
            "plan_id" => $planId,
            "owner"   => $ownerUid,
            "role"    => $inviteRole,
            "email"   => $inviteEmail  
        ];
        $database->getReference("invitations/{$inviteEmailKey}")
            ->push()
            ->set($invitationData);


        $mail = new PHPMailer(true);
        
        try {
            
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->Port       = 587;
            $mail->SMTPSecure = 'tls';
            $mail->SMTPAuth   = true;
            
           
            $mail->AuthType   = 'XOAUTH2';
        
           
            $provider = new Google(
                [
                    'clientId'     => '530491108453-7kpslds8aj5nencleap23m3mic6c2gt0.apps.googleusercontent.com',      
                    'clientSecret' => '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', 
                ]
            );
        
            $mail->setOAuth(
                new OAuth(
                    [
                        'provider'     => $provider,
                        'clientId'     => '530491108453-7kpslds8aj5nencleap23m3mic6c2gt0.apps.googleusercontent.com',        
                        'clientSecret' => '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',   
                        'refreshToken' => '1//044R_UxD6FygCCgYIARAAGAQSNwF-L9Ir6nzrlEevkPrykwUFQv3SKk14TJBvAFoA5LXC2BG8Mez9jvV7v5XEd4isWR6IJtuAm9A',     
                        'userName'     => 'rosswellacabo2004@gmail.com',   
                    ]
                )
            );
        
        
            $mail->setFrom('orbitaskplanner@gmail.com', 'Orbitask Digital Planner');
            $mail->addAddress($inviteEmail);
            $mail->addReplyTo('orbitaskplanner@gmail.com', 'Orbitask Digital Planner');
        
           
            $mail->isHTML(true);
            $viewUrl = "https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$uid}";
            
            $mail->Body = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="UTF-8">
              <title>{$mail->Subject}</title>
              <style>
                body { margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f4; }
                .email-wrapper { width:100%; padding:20px; background:#f4f4f4; }
                .logo-container { text-align:center; margin-bottom:20px; }
                .logo { max-width:150px; height:auto; }
                .card {
                  max-width:600px;
                  margin:0 auto;
                  background:#ffffff;
                  border-radius:8px;
                  overflow:hidden;
                  box-shadow:0 2px 6px rgba(0,0,0,0.15);
                }
                .body {
                  padding:16px;
                  color:#555;
                  line-height:1.5;
                  text-align:center;
                }
                .plan-title {
                  font-size:18px;
                  font-weight:600;
                  color:#333;
                  margin-bottom:12px;
                }
                .button {
                  display:inline-block;
                  padding:10px 18px;
                  background:#1a73e8;
                  color:#fff;
                  text-decoration:none;
                  border-radius:4px;
                  font-size:14px;
                  margin-top:16px;
                }
                .footer {
                  padding:12px 16px;
                  font-size:12px;
                  color:#888;
                  text-align:center;
                  border-top:1px solid #eee;
                }
              </style>
            </head>
            <body>
              <div class="email-wrapper">
                <div class="logo-container">
                  <img src="https://lh3.googleusercontent.com/a/ACg8ocI-BAg75A37XiOE-p8OY3N7UFITfTrrPLAgg-LJz8V1UnYjTKkyXRoOgHWC96dGvO30QPHsWyqBjABBNXjb7Xmj2972ThI=s260-c-no" alt="Your Company Logo" class="logo">
                </div>
            
                <div class="card">
                  <div class="body">
                    <div class="plan-title">You have been invited to collaborate on:<br>
                      “{$planTitle}”
                    </div>
                    <a href="{$viewUrl}" class="button">View Plan</a>
                  </div>
                  <div class="footer">
                    You received this because someone shared a plan with you on Orbitask Digital Planner.<br>
                    © Orbitask
                  </div>
                </div>
              </div>
            </body>
            </html>
            HTML;
            
            $mail->send();
            echo 'Message has been sent successfully';
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['new_role'], $_POST['plan_id'], $_POST['email_key'])) {
        $newRole = $_POST['new_role'];
        $planId = $_POST['plan_id'];
        $emailKey = $_POST['email_key'];

        $database
          ->getReference("plans/{$planId}/invited/{$emailKey}");

        $invitationsRef = $database->getReference("invitations/{$emailKey}");
        $invitationRecords = $invitationsRef->getValue() ?: [];

        foreach ($invitationRecords as $key => $record) {
            if (
              ($record['plan_id'] ?? '') === $planId &&
              ($record['owner'] ?? '') === $ownerUid
            ) {
                $invitationsRef->getChild($key.'/role')->set($newRole);
            }
        }

        header("Location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
}

if (isset($_POST['remove_invite'])) {
    $planId = $_POST['plan_id'];
    $removeKey = $_POST['email_key']; 
    $ownerUid = $_POST['owner_uid'];


    $database
        ->getReference("users/{$ownerUid}/plans/{$planId}/invited/{$removeKey}")
        ->remove();

 
    $invitationsRef = $database->getReference("invitations/{$removeKey}");
    $invitations     = $invitationsRef->getValue();

    if (!empty($invitations) && is_array($invitations)) {
        foreach ($invitations as $inviteKey => $inviteData) {
        
            if (isset($inviteData['plan_id'], $inviteData['owner']) 
                && $inviteData['plan_id'] === $planId
                && $inviteData['owner']   === $ownerUid) {
                $database   
                    ->getReference("invitations/{$removeKey}/{$inviteKey}")
                    ->remove();
            }
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}


if (isset($_POST['add_announcement'])) {
    $planId = $_POST['plan_id'];
    $ownerUid = $_POST['owner_uid'];
    $announcementText = trim($_POST['announcement_text']);
    if (empty($announcementText)) {
        header("Location: viewplan.php?plan_id={$planId}&owner_uid={$ownerUid}");
        exit;
    }

  
    $announcementRef = $database
        ->getReference("users/{$ownerUid}/plans/{$planId}/announcements")
        ->push();
    $announcementKey = $announcementRef->getKey();

    $data = [
        'text'        => htmlspecialchars($announcementText),
        'timestamp'   => time(),
        'authorEmail' => $currentUserEmail,
        'authorName'  => htmlspecialchars($currentUserName),
        'authorPhoto' => htmlspecialchars($currentUserPhoto),
    ];
    $codeLang = trim($_POST['announcement_code_lang'] ?? '');
    $codeText = trim($_POST['announcement_code'] ?? '');


    if (!empty($codeText)) {

        $data['code']     = htmlspecialchars($codeText);
        $data['code_lang']= htmlspecialchars($codeLang);
    }
  
    $fileUrls = [];
    if (!empty($_FILES['announcement_files']['tmp_name']) && is_array($_FILES['announcement_files']['tmp_name'])) {
        $baseUploadDir = __DIR__ . '/../uploads/announcements/' . $planId . '/' . $announcementKey . '/';
        if (!is_dir($baseUploadDir)) {
            mkdir($baseUploadDir, 0755, true);
        }
        foreach ($_FILES['announcement_files']['tmp_name'] as $i => $tmpPath) {
            if (is_uploaded_file($tmpPath) && $_FILES['announcement_files']['error'][$i] === UPLOAD_ERR_OK) {
               
                $origName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES['announcement_files']['name'][$i]));
                $filename = time() . "_{$origName}";
                $destination = $baseUploadDir . $filename;

                if (move_uploaded_file($tmpPath, $destination)) {
               
                    $fileUrls[] = "/ms-projectmonitoring/handlers/uploads/announcements/{$planId}/{$announcementKey}/{$filename}";
                }
            }
        }
    }
 
    if (!empty($fileUrls)) {
        $data['files'] = $fileUrls;
    }


    if (isset($_POST['announcement_urls']) && is_array($_POST['announcement_urls'])) {
      
        $websiteUrls = array();
        foreach ($_POST['announcement_urls'] as $url) {
            $sanitizedUrl = filter_var(trim($url), FILTER_SANITIZE_URL);
            if (!empty($sanitizedUrl)) {
                $websiteUrls[] = $sanitizedUrl;
            }
        }
        if (!empty($websiteUrls)) {
            $data['website_urls'] = $websiteUrls;
        }
    }


    $announcementRef->set($data);
  $fullText    = $data['text'];
  $previewText = mb_strlen($fullText) > 120
      ? mb_substr($fullText, 0, 120) . '…'
      : $fullText;

  $viewUrl = "https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$uid}";

  $subject = " New Announcement by {$data['authorName']}";

  $html = <<<HTML
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>{$subject}</title>
    <style>
      body { margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f4; }
      .logo-container { text-align:center; margin-bottom:20px; }
      .logo { max-width:150px; height:auto; }
      .email-wrapper { width:100%; padding:20px; background:#f4f4f4; }
      .card {
        max-width:600px;
        margin:0 auto;
        background:#ffffff;
        border-radius:8px;
        overflow:hidden;
        box-shadow:0 2px 6px rgba(0,0,0,0.15);
      }
      .header {
        padding:16px;
        border-bottom:1px solid #eee;
        display:flex;
        align-items:center;
      }
      .author-photo {
        width:48px; height:48px;
        border-radius:50%;
        object-fit:cover;
        margin-right:12px;
      }
      .author-name {
        font-size:16px;
        font-weight:600;
        color:#333;
      }
      .body {
        padding:16px;
        color:#555;
        line-height:1.5;
      }
      .preview-text {
        font-size:14px;
        margin-bottom:16px;
      }
      .button {
        display:inline-block;
        padding:10px 18px;
        background:#1a73e8;
        color:#fff;
        text-decoration:none;
        border-radius:4px;
        font-size:14px;
      }
      .footer {
        padding:12px 16px;
        font-size:12px;
        color:#888;
        text-align:center;
        border-top:1px solid #eee;
      }
    </style>
  </head>
  <body>
    <div class="email-wrapper">
    <div class="logo-container">
          <img src="https://lh3.googleusercontent.com/a/ACg8ocI-BAg75A37XiOE-p8OY3N7UFITfTrrPLAgg-LJz8V1UnYjTKkyXRoOgHWC96dGvO30QPHsWyqBjABBNXjb7Xmj2972ThI=s260-c-no" alt="Your Company Logo" class="logo">
        </div>

      <div class="card">
        <div class="header">
          <img src="{$data['authorPhoto']}" alt="{$data['authorName']}" class="author-photo">
          <div class="author-name">{$data['authorName']} posted an announcement</div>
        </div>
        <div class="body">
          <p class="preview-text">{$previewText}</p>
          <a href="{$viewUrl}" class="button">View Full Announcement</a>
        </div>
        <div class="footer">
          You received this e-mail because you are invited to the plan.<br>
          © Orbitask
        </div>
      </div>
    </div>
  </body>
  </html>
  HTML;

  notifyInvited($ownerUid, $planId, $subject, $html);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();

}

/**
 * Recursively remove a directory and its files.
 *
 * @param string $dir The directory path.
 */
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $currentPath = $dir . DIRECTORY_SEPARATOR . $object;
                if (is_dir($currentPath)) {
                    rrmdir($currentPath);
                } else {
                    unlink($currentPath);
                }
            }
        }
        rmdir($dir);
    }
}


if (isset($_POST['delete_announcement'])) {
    $planId          = $_POST['plan_id'];
    $announcementId  = $_POST['announcement_id'];
    
    $annRef = $database
        ->getReference("users/{$ownerUid}/plans/{$planId}/announcements/{$announcementId}");
    $annRef->remove();

    $uploadDir = __DIR__ . '/../uploads/announcements/' . $planId . '/' . $announcementId . '/';
    if (is_dir($uploadDir)) {
        rrmdir($uploadDir);
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}


if (isset($_POST['edit_announcement'])) {
    $planId         = $_POST['plan_id'];
    $ownerUid = $_POST['owner_uid'];
    $announcementId = $_POST['announcement_id'];
    $newText        = trim($_POST['announcement_text']);
    
    $codeLang = trim($_POST['announcement_code_lang'] ?? '');
    $codeText = trim($_POST['announcement_code'] ?? '');

    $annRef = $database->getReference("users/{$ownerUid}/plans/{$planId}/announcements/{$announcementId}");
    
    $dataUpdate = [
        'text'      => htmlspecialchars($newText),
        'timestamp' => time()
    ];

            if ($codeText !== '') {
                $dataUpdate['code']      = $codeText;
                $dataUpdate['code_lang'] = $codeLang;
            } else {
       
                $dataUpdate['code']      = null;
                $dataUpdate['code_lang'] = null;
            }
 
    $fileUrlsEdit = [];
    if (!empty($_FILES['announcement_files']['tmp_name']) && is_array($_FILES['announcement_files']['tmp_name'])) {
        $baseUploadDir = __DIR__ . '/../uploads/announcements/' . $planId . '/' . $announcementId . '/';
        if (!is_dir($baseUploadDir)) {
            mkdir($baseUploadDir, 0755, true);
        }
        foreach ($_FILES['announcement_files']['tmp_name'] as $i => $tmpPath) {
            if (is_uploaded_file($tmpPath) && $_FILES['announcement_files']['error'][$i] === UPLOAD_ERR_OK) {
     
                $origName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES['announcement_files']['name'][$i]));
                $filename = time() . "_{$origName}";
                $destination = $baseUploadDir . $filename;
                if (move_uploaded_file($tmpPath, $destination)) {
                    $fileUrlsEdit[] = "/ms-projectmonitoring/handlers/uploads/announcements/{$planId}/{$announcementId}/{$filename}";
                }
            }
        }
    }
  
    if (!empty($fileUrlsEdit)) {
        $dataUpdate['files'] = $fileUrlsEdit;
    }


    if (isset($_POST['announcement_urls']) && is_array($_POST['announcement_urls'])) {
        $websiteUrlsEdit = [];
        foreach ($_POST['announcement_urls'] as $url) {
            $sanitizedUrl = filter_var(trim($url), FILTER_SANITIZE_URL);
            if (!empty($sanitizedUrl)) {
                $websiteUrlsEdit[] = $sanitizedUrl;
            }
        }
        if (!empty($websiteUrlsEdit)) {
            $dataUpdate['website_urls'] = $websiteUrlsEdit;
        } else {
  
            $dataUpdate['website_urls'] = null;
        }
    }


    $annRef->update($dataUpdate);


    $fullText    = $dataUpdate['text'];
    $previewText = mb_strlen($fullText) > 120
        ? mb_substr($fullText, 0, 120) . '…'
        : $fullText;

    $viewUrl = "https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$ownerUid}";

    $subject = " Announcement Updated by {$authorName}";

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>{$subject}</title>
      <style>
        body { margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f4; }
        .email-wrapper { width:100%; padding:20px; background:#f4f4f4; }
        .logo-container { text-align:center; margin-bottom:20px; }
        .logo { max-width:150px; height:auto; }
        .card {
          max-width:600px;
          margin:0 auto;
          background:#ffffff;
          border-radius:8px;
          overflow:hidden;
          box-shadow:0 2px 6px rgba(0,0,0,0.15);
        }
        .header {
          padding:16px;
          border-bottom:1px solid #eee;
          display:flex;
          align-items:center;
        }
        .author-photo {
          width:48px; height:48px;
          border-radius:50%;
          object-fit:cover;
          margin-right:12px;
        }
        .author-name {
          font-size:16px;
          font-weight:600;
          color:#333;
        }
        .body {
          padding:16px;
          color:#555;
          line-height:1.5;
        }
        .preview-text {
          font-size:14px;
          margin-bottom:16px;
        }
        .button {
          display:inline-block;
          padding:10px 18px;
          background:#1a73e8;
          color:#fff;
          text-decoration:none;
          border-radius:4px;
          font-size:14px;
        }
        .footer {
          padding:12px 16px;
          font-size:12px;
          color:#888;
          text-align:center;
          border-top:1px solid #eee;
        }
      </style>
    </head>
    <body>
      <div class="email-wrapper">
        <div class="logo-container">
          <img src="https://lh3.googleusercontent.com/a/ACg8ocI-BAg75A37XiOE-p8OY3N7UFITfTrrPLAgg-LJz8V1UnYjTKkyXRoOgHWC96dGvO30QPHsWyqBjABBNXjb7Xmj2972ThI=s260-c-no" alt="Your Company Logo" class="logo">
        </div>

        <div class="card">
          <div class="header">
            <img src="{$currentUserPhoto}" alt="{$currentUserName}" class="author-photo">
            <div class="author-name">{$currentUserName} updated an announcement</div>
          </div>
          <div class="body">
            <p class="preview-text">{$previewText}</p>
            <a href="{$viewUrl}" class="button">View Updated Announcement</a>
          </div>
          <div class="footer">
            You received this e-mail because you are invited to the plan.<br>
            © Orbitask
          </div>
        </div>
      </div>
    </body>
    </html>
    HTML;

    notifyInvited($ownerUid, $planId, $subject, $html);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

if (isset($_POST['add_note'])) {
    $planId   = $_POST['plan_id'];
    $ownerUid = $_POST['owner_uid'];
    $noteText = trim($_POST['note_text']);

    if (!empty($noteText)) {
    
        $noteRef = $database->getReference("users/{$ownerUid}/plans/{$planId}/notes")->push();
        $noteRef->set([
            'authorName'  => $currentUserName,  
            'authorPhoto' => $currentUserPhoto,     
            'text'        => htmlspecialchars($noteText),
            'timestamp'   => time(),
        ]);
    }
    $fullText    = htmlspecialchars($noteText);
    $previewText = mb_strlen($fullText) > 120
        ? mb_substr($fullText, 0, 120) . '…'
        : $fullText;

    $subject = " New Note by {$currentUserName}";
    $viewUrl = "https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$ownerUid}";

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>{$subject}</title>
      <style>
        body { margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f4; }
        .email-wrapper { width:100%; padding:20px; background:#f4f4f4; }
        .logo-container { text-align:center; margin-bottom:20px; }
        .logo { max-width:150px; height:auto; }
        .card {
          max-width:600px;
          margin:0 auto;
          background:#ffffff;
          border-radius:8px;
          overflow:hidden;
          box-shadow:0 2px 6px rgba(0,0,0,0.15);
        }
        .header {
          padding:16px;
          border-bottom:1px solid #eee;
          display:flex;
          align-items:center;
        }
        .author-photo {
          width:48px; height:48px;
          border-radius:50%;
          object-fit:cover;
          margin-right:12px;
        }
        .author-name {
          font-size:16px;
          font-weight:600;
          color:#333;
        }
        .body {
          padding:16px;
          color:#555;
          line-height:1.5;
        }
        .preview-text {
          font-size:14px;
          margin-bottom:16px;
        }
        .button {
          display:inline-block;
          padding:10px 18px;
          background:#1a73e8;
          color:#fff;
          text-decoration:none;
          border-radius:4px;
          font-size:14px;
        }
        .footer {
          padding:12px 16px;
          font-size:12px;
          color:#888;
          text-align:center;
          border-top:1px solid #eee;
        }
      </style>
    </head>
    <body>
      <div class="email-wrapper">
        <div class="logo-container">
          <img src="https://lh3.googleusercontent.com/a/ACg8ocI-BAg75A37XiOE-p8OY3N7UFITfTrrPLAgg-LJz8V1UnYjTKkyXRoOgHWC96dGvO30QPHsWyqBjABBNXjb7Xmj2972ThI=s260-c-no" alt="Your Company Logo" class="logo">
        </div>
        <div class="card">
          <div class="header">
            <img src="{$currentUserPhoto}" alt="{$currentUserName}" class="author-photo">
            <div class="author-name">{$currentUserName} added a note</div>
          </div>
          <div class="body">
            <p class="preview-text">{$previewText}</p>
            <a href="{$viewUrl}" class="button">See Note →</a>
          </div>
          <div class="footer">
            You received this e-mail because you are invited to the plan.<br>
            © Orbitask
          </div>
        </div>
      </div>
    </body>
    </html>
    HTML;

    notifyInvited($ownerUid, $planId, $subject, $html);
        header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

if (isset($_POST['delete_note'])) {
    $planId = $_POST['plan_id'];
    $noteId = $_POST['note_id'];
    $targetUid = $_POST['owner_uid'];

    $notePath = "users/{$targetUid}/plans/{$planId}/notes/{$noteId}";
    $database->getReference($notePath)->remove();

    header('Location: ' . $_SERVER['REQUEST_URI']);

    exit();
}


if (isset($_POST['edit_note'])) {
    $planId = $_POST['plan_id'];
    $noteId = $_POST['note_id'];
    $editedText = trim($_POST['edited_note_text']);
    $targetUid = $_POST['owner_uid'];

    if (!empty($editedText)) {
        $notePath = "users/{$targetUid}/plans/{$planId}/notes/{$noteId}";
        $database->getReference($notePath)->update([
            'text' => htmlspecialchars($editedText)
        ]);
    }
    $fullText    = htmlspecialchars($editedText);
    $previewText = mb_strlen($fullText) > 120
        ? mb_substr($fullText, 0, 120) . '…'
        : $fullText;

    $subject = "Note Edited by {$currentUserName}";
    $viewUrl = "https://orbitask.site/directives/viewplan.php?plan_id={$planId}&owner_uid={$ownerUid}";

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>{$subject}</title>
      <style>
        body { margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f4f4; }
        .email-wrapper { width:100%; padding:20px; background:#f4f4f4; }
        .logo-container { text-align:center; margin-bottom:20px; }
        .logo { max-width:150px; height:auto; }
        .card {
          max-width:600px;
          margin:0 auto;
          background:#ffffff;
          border-radius:8px;
          overflow:hidden;
          box-shadow:0 2px 6px rgba(0,0,0,0.15);
        }
        .header {
          padding:16px;
          border-bottom:1px solid #eee;
          display:flex;
          align-items:center;
        }
        .author-photo {
          width:48px; height:48px;
          border-radius:50%;
          object-fit:cover;
          margin-right:12px;
        }
        .author-name {
          font-size:16px;
          font-weight:600;
          color:#333;
        }
        .body {
          padding:16px;
          color:#555;
          line-height:1.5;
        }
        .preview-text {
          font-size:14px;
          margin-bottom:16px;
        }
        .button {
          display:inline-block;
          padding:10px 18px;
          background:#1a73e8;
          color:#fff;
          text-decoration:none;
          border-radius:4px;
          font-size:14px;
        }
        .footer {
          padding:12px 16px;
          font-size:12px;
          color:#888;
          text-align:center;
          border-top:1px solid #eee;
        }
      </style>
    </head>
    <body>
      <div class="email-wrapper">
        <div class="logo-container">
          <img src="https://lh3.googleusercontent.com/a/ACg8ocI-BAg75A37XiOE-p8OY3N7UFITfTrrPLAgg-LJz8V1UnYjTKkyXRoOgHWC96dGvO30QPHsWyqBjABBNXjb7Xmj2972ThI=s260-c-no" alt="Your Company Logo" class="logo">
        </div>
        <div class="card">
          <div class="header">
            <img src="{$currentUserPhoto}" alt="{$currentUserName}" class="author-photo">
            <div class="author-name">{$currentUserName} edited a note</div>
          </div>
          <div class="body">
            <p class="preview-text">{$previewText}</p>
            <a href="{$viewUrl}" class="button">See Updated Note →</a>
          </div>
          <div class="footer">
            You received this e-mail because you are invited to the plan.<br>
            © Orbitask
          </div>
        </div>
      </div>
    </body>
    </html>
    HTML;

    notifyInvited($ownerUid, $planId, $subject, $html);

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan'])) {
    $deletePlanId = $_POST['plan_id'];
    $ownerUid  = $_POST['owner_uid'];
    $planPath = "users/{$ownerUid}/plans/{$deletePlanId}";

    $database->getReference($planPath)->remove();    
    $invitedPath = "users/{$ownerUid}/plans/{$deletePlanId}/invited";
    $invitedUsers = $database->getReference($invitedPath)->getValue();

    if ($invitedUsers) {
        foreach ($invitedUsers as $emailKey => $invitedData) {
            $database->getReference("invitations/{$emailKey}")
                     ->getChildKeys()
                     ->then(function($inviteKeys) use ($deletePlanId, $database, $emailKey) {
                         foreach ($inviteKeys as $inviteKey) {
                             $database->getReference("invitations/{$emailKey}/{$inviteKey}")->remove();
                         }
                     });
        }
    }

    header("Location: dashboard.php");
    exit();
}
if (isset($_POST['add_task_upload'])) {
    $planId   = $_POST['plan_id'];
    $ownerUid = $_POST['owner_uid'];
    $taskId   = $_POST['task_id'];
    $myEmailKey = sanitizeEmail($_SESSION['email']);
    if (!empty($_FILES['task_files']['tmp_name'])) {
        $basePath   = __DIR__ . '/../../handlers/uploads/tasks-uploads/';
        $publicBase = '/ms-projectmonitoring/handlers/uploads/tasks-uploads/';
        if (!file_exists($basePath)) {
            mkdir($basePath, 0755, true);
        }
        foreach ($_FILES['task_files']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['task_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $orig   = basename($_FILES['task_files']['name'][$i]);
            $target = $basePath . time() . "_{$orig}";
            if (move_uploaded_file($tmpName, $target)) {
                $url = $publicBase . basename($target);
                $ref = $database
                  ->getReference("users/{$ownerUid}/plans/{$planId}/tasks/{$taskId}/uploads/{$myEmailKey}/files")
                  ->push();
                $ref->set($url);
            }
        }
    }

    $url = trim($_POST['task_url'] ?? '');
    if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
        $ref = $database
          ->getReference("users/{$ownerUid}/plans/{$planId}/tasks/{$taskId}/uploads/{$myEmailKey}/website_urls")
          ->push();
        $ref->set($url);
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
if (isset($_POST['unsubmit_task'])) {
    $planId     = $_POST['plan_id'];
    $ownerUid   = $_POST['owner_uid'];
    $taskId     = $_POST['task_id'];
    $myEmailKey = sanitizeEmail($_SESSION['email']);

    $database
      ->getReference("users/{$ownerUid}/plans/{$planId}/tasks/{$taskId}/uploads/{$myEmailKey}")
      ->remove();

      header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
