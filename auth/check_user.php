<?php
header("Content-Type: application/json");
session_start();

$uid = $_GET["uid"] ?? "";
if (!$uid) {
    echo json_encode(["error" => "UID not provided"]);
    exit();
}

$firebaseUrl = "https://ms-digitalplanner-default-rtdb.firebaseio.com/users/{$uid}.json";
$firebaseResponse = file_get_contents($firebaseUrl);
$userData = json_decode($firebaseResponse, true);

if ($userData) {
    $_SESSION["uid"]         = $uid;
    $_SESSION["email"]       = $userData["email"] ?? '';
    $_SESSION["photoURL"]    = $userData["photoURL"] ?? '../assets/img/default-avatar.png';
    $_SESSION["displayName"] = $userData["name"] ?? '';
    echo json_encode(["exists" => true, "redirect" => "../directives/dashboard.php"]);
} else {
    echo json_encode(["exists" => false]);
}
exit();
