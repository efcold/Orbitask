<?php

if (isset($_POST['add_comment'])) {
    $planId = $_POST['plan_id'];
    $announcementId = $_POST['announcement_id'];
    $announcementOwnerUid = $_POST['owner_uid'] ?? $uid;
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
    $planId         = $_POST['plan_id'];
    $announcementId = $_POST['announcement_id'];
    $commentId      = $_POST['comment_id'];
    $editedText     = trim($_POST['edited_comment_text']);
    $targetUid      = $_POST['owner_uid'] ?? $uid;

    if ($editedText !== '') {
        $commentPath = "users/{$targetUid}/plans/{$planId}/announcements/{$announcementId}/comments/{$commentId}";
        $database->getReference($commentPath)->update([
            'text' => htmlspecialchars($editedText)
        ]);
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

if (isset($_POST['delete_comment'])) {
    $planId         = $_POST['plan_id'];
    $announcementId = $_POST['announcement_id'];
    $commentId      = $_POST['comment_id'];
    $targetUid      = $_POST['owner_uid'] ?? $uid;

    $commentPath = "users/{$targetUid}/plans/{$planId}/announcements/{$announcementId}/comments/{$commentId}";
    $database->getReference($commentPath)->remove();

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
            'authorEmail' => $currentUserEmail, 
            'authorPhoto' => $currentUserPhoto,
            'text'        => htmlspecialchars($noteText),
            'timestamp'   => time(),
        ]);
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}

if (isset($_POST['edit_note'])) {
    $planId = $_POST['plan_id'];
    $noteId = $_POST['note_id'];
    $editedText = trim($_POST['edited_note_text']);
    $targetUid = $_POST['owner_uid'] ?? $uid;

    if (!empty($editedText)) {
        $notePath = "users/{$targetUid}/plans/{$planId}/notes/{$noteId}";
        $database->getReference($notePath)->update([
            'text' => htmlspecialchars($editedText)
        ]);
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
}
if (isset($_POST['delete_note'])) {
    $planId = $_POST['plan_id'];
    $noteId = $_POST['note_id'];
    $targetUid = $_POST['owner_uid'] ?? $uid;

    $notePath = "users/{$targetUid}/plans/{$planId}/notes/{$noteId}";
    $database->getReference($notePath)->remove();

    header('Location: ' . $_SERVER['REQUEST_URI']);
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

?>
