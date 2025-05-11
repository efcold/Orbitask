<?php
session_start();

$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['uid'])) {
    $_SESSION['uid']         = $data['uid'];
    $_SESSION['displayName'] = $data['displayName'] ?? '';
    $_SESSION['email']       = $data['email'] ?? '';
    $_SESSION['photoURL']    = $data['photoURL'] ?? null;
  
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Missing UID"]);
}
