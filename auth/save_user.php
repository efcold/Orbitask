<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"));

    if (isset($data->uid)) {
        $firebaseUrl = "https://ms-digitalplanner-default-rtdb.firebaseio.com/users/{$data->uid}.json";

        $userData = json_encode([
            "uid" => $data->uid,
            "email" => $data->email,
            "name" => $data->displayName,
            "phone" => $data->phone,
            "address" => $data->address,
            "photoURL" => $data->photoURL ?? ""
        ]);
        
        $ch = curl_init($firebaseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $userData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        curl_exec($ch);
        curl_close($ch);

        echo json_encode(["status" => "success"]);
    }
}
?>
