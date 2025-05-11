<?php
ob_start();
session_start();

if (!isset($_SESSION["uid"])) {
    header("Location: ../auth/login.php");
    exit();
}
$currentUserEmail = $_SESSION['email'] ?? '';
$uid              = $_SESSION['uid'];

function sanitizeEmail(string $email): string {
    return str_replace(['.', '#', '$', '[', ']'], '_', $email);
}
$meKey    = strtolower(trim($currentUserEmail));      
$meSanKey = sanitizeEmail($meKey);                   

require __DIR__ . '/../vendor/autoload.php';
use Kreait\Firebase\Factory;

$factory  = (new Factory)
    ->withServiceAccount(__DIR__ . '/../ms-digitalplanner-firebase-adminsdk-fbsvc-dc1c731d47.json')
    ->withDatabaseUri('https://ms-digitalplanner-default-rtdb.firebaseio.com/');
$database = $factory->createDatabase();

$myPlansRef     = "users/{$uid}/plans";
$invitationsRef = "invitations/{$meSanKey}";

$myPlans     = $database->getReference($myPlansRef)->getValue()     ?? [];
$invitations = $database->getReference($invitationsRef)->getValue() ?? [];

$allEvents = [];

foreach ($myPlans as $planId => $plan) {
    // Plan itself
    $allEvents[] = [
        'title'   => $plan['title']      ?? 'Untitled',
        'start'   => $plan['start_date'] ?? '',
        'end'     => $plan['end_date']   ?? '',
        'plan_id' => $planId,
        'owner'   => $uid,
        'isOwner' => true,
    ];
    // Tasks
    foreach ($plan['tasks'] ?? [] as $taskId => $task) {
        if (empty($task['due_date'])) continue;
        $allEvents[] = [
            'title'           => '[Task] ' . ($task['name'] ?? 'Untitled Task'),
            'start'           => $task['due_date'],
            'allDay'          => true,
            'plan_id'         => $planId,
            'owner'           => $uid,
            'isOwner'         => true,
            'backgroundColor' => '#f0ad4e',
            'textColor'       => '#fff',
        ];
    }
}

foreach ($invitations as $inv) {
    if (empty($inv['plan_id']) || empty($inv['owner'])) continue;

    $planDetail = $database
        ->getReference("users/{$inv['owner']}/plans/{$inv['plan_id']}")
        ->getValue();
    if (!$planDetail) continue;

    $allEvents[] = [
        'title'   => $planDetail['title']      ?? 'Untitled',
        'start'   => $planDetail['start_date'] ?? '',
        'end'     => $planDetail['end_date']   ?? '',
        'plan_id' => $inv['plan_id'],
        'owner'   => $inv['owner'],
        'isOwner' => false,
    ];
    foreach ($planDetail['tasks'] ?? [] as $taskId => $task) {
        if (empty($task['due_date'])) continue;

        $raw = $task['assigned_to'] ?? null;
        $candidates = is_array($raw) ? $raw
                       : (is_string($raw) ? [$raw]
                       : []);

        $normalizedRaw   = array_map(fn($e) => strtolower(trim($e)), $candidates);
        $normalizedSanit = array_map(fn($e) => sanitizeEmail(strtolower(trim($e))), $candidates);

        error_log(sprintf(
            "[DEBUG TASK][Plan %s][Task %s] raw: %s | normRaw: %s | normSan: %s",
            $inv['plan_id'],
            $taskId,
            json_encode($candidates),
            json_encode($normalizedRaw),
            json_encode($normalizedSanit)
        ));

        if (in_array($meKey, $normalizedRaw,    true)
         || in_array($meSanKey, $normalizedSanit, true)
        ) {
            $allEvents[] = [
                'title'           => '[Task] ' . ($task['name'] ?? 'Untitled Task'),
                'start'           => $task['due_date'],
                'allDay'          => true,
                'plan_id'         => $inv['plan_id'],
                'owner'           => $inv['owner'],
                'isOwner'         => false,
                'backgroundColor' => '#5bc0de',
                'textColor'       => '#fff',
            ];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($allEvents);

if (isset($_GET['debug'])) {
    echo "\n\n<!-- DEBUG JSON:\n" . json_encode($allEvents, JSON_PRETTY_PRINT) . "\n-->";
}
