<?php
if (isset($_GET['fetch_notes'])) {
    $planId = isset($_GET['plan_id']) ? $_GET['plan_id'] : '';
    $targetUid = (isset($_GET['owner_uid']) && !empty($_GET['owner_uid'])) ? $_GET['owner_uid'] : $uid;

    if (empty($planId)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Plan ID is required.']);
        exit();
    }

    $notesRef = $database->getReference("users/{$targetUid}/plans/{$planId}/notes");
    $notesSnapshot = $notesRef->getSnapshot();
    $notesData = $notesSnapshot->getValue();
    $filteredNotes = [];

    if (!empty($notesData) && is_array($notesData)) {
        foreach ($notesData as $noteKey => $note) {
            $filteredNotes[] = [
                'authorName'  => isset($note['authorName']) ? $note['authorName'] : null,
                'authorPhoto' => isset($note['authorPhoto']) ? $note['authorPhoto'] : null,
                'text'        => isset($note['text']) ? $note['text'] : null,
                'timestamp'   => isset($note['timestamp']) ? $note['timestamp'] : null,
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['notes' => $filteredNotes]);
    exit();
}

if (isset($_GET['fetch_comments'])) {
    $planId = isset($_GET['plan_id']) ? $_GET['plan_id'] : '';
    $announcementId = isset($_GET['announcement_id']) ? $_GET['announcement_id'] : '';
    $announcementOwnerUid = isset($_GET['owner_uid']) && !empty($_GET['owner_uid']) ? $_GET['owner_uid'] : $uid;

    if (empty($planId) || empty($announcementId)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Both plan ID and announcement ID are required.']);
        exit();
    }

    $commentsRef = $database->getReference("users/{$announcementOwnerUid}/plans/{$planId}/announcements/{$announcementId}/comments");
    $commentsSnapshot = $commentsRef->getSnapshot();
    $commentsData = $commentsSnapshot->getValue();

    $filteredComments = [];

    if (!empty($commentsData) && is_array($commentsData)) {
        foreach ($commentsData as $commentKey => $comment) {
            $filteredComments[] = [
                'authorEmail' => isset($comment['authorEmail']) ? $comment['authorEmail'] : null,
                'authorName'  => isset($comment['authorName']) ? $comment['authorName'] : null,
                'authorPhoto' => isset($comment['authorPhoto']) ? $comment['authorPhoto'] : null,
                'text'        => isset($comment['text']) ? $comment['text'] : null,
                'timestamp'   => isset($comment['timestamp']) ? $comment['timestamp'] : null,
            ];
        }
    }
    if (!empty($filteredComments)) {
        usort($filteredComments, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });
    }

    header('Content-Type: application/json');
    echo json_encode(['comments' => $filteredComments]);
    exit();
}



?>