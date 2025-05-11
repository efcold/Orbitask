<?php
session_start();
require __DIR__ . '/../includes/image_cache.php';

$originalPhotoURL = $_SESSION['photoURL'] ?? '../assets/img/default-avatar.png';
$photoURL = getCachedProfileImage($originalPhotoURL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if ($data && isset($data["uid"])) {
        $firebaseUrl = "https://ms-digitalplanner-default-rtdb.firebaseio.com/users/{$data['uid']}.json";
        $userData = json_encode([
          "uid"      => $data["uid"],
          "email"    => $data["email"],
          "name"     => $data["displayName"],
          "phone"    => $data["phone"],
          "address"  => $data["address"],
          "photoURL" => $data["photoURL"] ?? '' 
        ]);
              $options = [
            "http" => [
                "header"  => "Content-Type: application/json",
                "method"  => "PUT",
                "content" => $userData,
            ],
        ];
        $context  = stream_context_create($options);
        file_get_contents($firebaseUrl, false, $context);
              $_SESSION["uid"] = $data["uid"];
        $_SESSION["email"] = $data["email"];
        echo json_encode(["status" => "success"]);
        exit();
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid data"]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Confirm Details</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="../assets/css/confirmdetails.css">
    <link
      href="https://fonts.googleapis.com/css2?family=Afacad:wght@400;600;700&display=swap"
      rel="stylesheet"/>

  </head>
  <body>
    <div class="container">
      <div class="form-container">
        <img src="../assets/img/icons/success.png" alt="Success" class="success-icon" />
        <div id="profilePic" class="avatar" 
         style="width: 100px; height: 100px; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-size: 48px; font-weight: bold; margin-bottom: 20px;">
          <?php if ($photoURL !== '../assets/img/default-avatar.png'): ?>
            <img src="<?= htmlspecialchars($photoURL) ?>" style="width: 100%; height: 100%; border-radius: 50%;">
          <?php else: ?>
            <span id="avatarLetter"></span>
          <?php endif; ?>
        </div>   
        <p>You have successfully signed in with Google.</p>
        <h3>Confirm your details</h3>
        <form  id="confirmForm" method="POST">
        <input type="hidden" id="uid" name="uid" value="">
          <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="displayName"  required />
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"  readonly />
          </div>
          <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone"  required />
          </div>
          <div class="form-group">
            <label for="address">Address</label>
            <input type="text" id="address" name="address" required />
          </div>
          <div class="form-options">
            <input type="checkbox" id="terms" name="terms" />
            <label for="terms">Agree to the</label>
            <a href="#">Terms and Conditions</a>
          </div>
          <button type="submit" class="submit-btn">Confirm &amp; Continue</button>
        </form>
      </div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
      const userData = JSON.parse(sessionStorage.getItem("userData"));
    
      if (userData) {
        document.getElementById("uid").value = userData.uid;
        document.getElementById("name").value = userData.displayName;
        document.getElementById("email").value = userData.email;
    
        <?php if (!$photoURL || $photoURL === '../assets/img/default-avatar.png'): ?>
          if (userData.photoURL) {
            const profilePicDiv = document.getElementById("profilePic");
            profilePicDiv.innerHTML = `<img src="${userData.photoURL}" alt="Profile Picture" style="width: 100%; height: 100%; border-radius: 50%;">`;
          } else {
            const firstLetter = userData.displayName.charAt(0).toUpperCase();
            document.getElementById("avatarLetter").textContent = firstLetter;
          }
        <?php endif; ?>
      }
    
      document.getElementById("confirmForm").addEventListener("submit", function (event) {
        event.preventDefault();
        const formData = {
          uid: document.getElementById("uid").value,
          displayName: document.getElementById("name").value,
          email: document.getElementById("email").value,
          phone: document.getElementById("phone").value,
          address: document.getElementById("address").value,
          photoURL: userData.photoURL || ""
        };
    
        fetch("", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === "success") {
            window.location.href = "../directives/dashboard.php";
          } else {
            alert("Error saving data. Please try again.");
          }
        })
        .catch(error => console.error("Error:", error));
      });
    });
  </script>
  </body>
</html>
