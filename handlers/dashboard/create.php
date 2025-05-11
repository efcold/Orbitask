<?php

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    return;
}
if (isset($_POST['create_plan'])) {
    $tasksArray = [];
    if (!empty($_POST['tasks_json'])) {
    $taskItems = json_decode($_POST['tasks_json'], true);
    if (is_array($taskItems)) {
        foreach ($taskItems as $task) {
            $tasksArray[uniqid()] = [
                "name"       => htmlspecialchars($task['name']),
                "due_date"   => htmlspecialchars($task['due_date']),
                "due_time"   => htmlspecialchars($task['due_time']),
                "completed"  => false
            ];
        }        
    }
}

    $startDate = $_POST['start_date']; 
    $endDate   = $_POST['end_date']; 

    $status = "In Progress";
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

    
    $newPlanRef = $database->getReference($plansRef)->push();
    $newPlanRef->set([
        "creator"    => $uid,
        "title"      => $_POST['title'],
        "start_date" => $startDate,
        "end_date"   => $endDate,
        "status"     => $status,
        "tasks"      => $tasksArray,
        "banner"     => $banner, 
        "invited"    => []
    ]);
    header("Location: dashboard.php");

    exit();
}



if (isset($_POST['accept_invite'])) {
    
    $inviteKey   = $_POST['invite_key']   ?? null;
    $planId      = $_POST['plan_id']      ?? null;
    $ownerUid    = $_POST['owner_uid']    ?? null;
    $invitedRole = trim((string)($_POST['invited_role'] ?? ''));

    if ($inviteKey && $planId && $ownerUid) {
        if ($invitedRole === '') {
            $invitationRef = $database->getReference("invitations/{$myEmailKey}/{$inviteKey}");
            $roleSnapshot  = $invitationRef->getChild('invited_role')->getSnapshot();
            $invitedRole   = trim((string)$roleSnapshot->getValue());
        }
        $database
            ->getReference("invitations/{$myEmailKey}/{$inviteKey}")
            ->update(['accepted' => true]);
        $database
            ->getReference("invitations/{$myEmailKey}/{$inviteKey}/ignored")
            ->remove();
        $database
            ->getReference("users/{$ownerUid}/plans/{$planId}/invited/{$myEmailKey}")
            ->set('accepted');
        if (strcasecmp($invitedRole, 'assistant admin') === 0) {
            $redirectUrl = "assistantadmin.php?plan_id=" . urlencode($planId)
                         . "&owner_uid=" . urlencode($ownerUid);
        } else {
            $redirectUrl = "invites.php?plan_id=" . urlencode($planId)
                         . "&owner_uid=" . urlencode($ownerUid);
        }
    } else {
        $redirectUrl = "invites.php";
    }

    header("Location: {$redirectUrl}");
    exit();
}


if (isset($_POST['ignore_invite'])) {
    $inviteKey = $_POST['invite_key'] ?? null;
    if ($inviteKey) {
        $invitationRef = $database->getReference("invitations/{$myEmailKey}/{$inviteKey}");
        $invitationRef->update(['ignored' => true]);
    }
    $viewUrl = "archive.php?plan_id=" . urlencode($planId) . "&owner_uid=" . urlencode($ownerUid);
    header("Location: " . $viewUrl);
    exit();
}

?>