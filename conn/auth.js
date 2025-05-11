document.addEventListener("DOMContentLoaded", function () {
  const auth = firebase.auth();
  const provider = new firebase.auth.GoogleAuthProvider();
  provider.addScope('profile');
  
  document.getElementById("googleLoginBtn").addEventListener("click", function () {
    auth.signInWithPopup(provider)
      .then(result => {
        const user = result.user;
        let photoURL = user.photoURL || (user.providerData.length > 0 ? user.providerData[0].photoURL : "");
  
        console.log("User Photo URL:", photoURL); 
  
        const userData = {
          uid: user.uid,
          email: user.email,
          displayName: user.displayName,
          photoURL: photoURL 
        };
  
        sessionStorage.setItem("userData", JSON.stringify(userData));
  
        fetch(`../auth/check_user.php?uid=${encodeURIComponent(user.uid)}`)
          .then(response => response.json())
          .then(data => {
            if (data.exists) {
              fetch("../auth/set_session.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(userData)
              })
              .then(res => res.json())
              .then(sessionData => {
                if (sessionData.status === "success") {
                  window.location.href = "../directives/dashboard.php";
                } else {
                  console.error("Session update error:", sessionData.message);
                }
              });
            } else {
              fetch("../auth/set_session.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(userData)
              })
              .then(res => res.json())
              .then(sessionData => {
                if (sessionData.status === "success") {
                  window.location.href = "../auth/user_confirmation.php?uid=" + encodeURIComponent(user.uid);
                } else {
                  console.error("Session update error:", sessionData.message);
                }
              });
            }
          })
          .catch(error => console.error("Error checking user:", error));
      })
      .catch(error => console.error("Error during Google sign-in:", error));
  });
});
