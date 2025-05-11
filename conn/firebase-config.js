document.addEventListener("DOMContentLoaded", function() {
    if (typeof firebase !== "undefined") {
const firebaseConfig = {
  apiKey: "AIzaSyD4wBVFweje8DEpj1wW0D1JHyvaWGOk76M",
  authDomain: "ms-digitalplanner.firebaseapp.com",
  databaseURL: "https://ms-digitalplanner-default-rtdb.firebaseio.com",
  projectId: "ms-digitalplanner",
  storageBucket: "ms-digitalplanner.firebasestorage.app",
  messagingSenderId: "1026841926541",
  appId: "1:1026841926541:web:d61e0e5f61da0c1d55fbc4",
  measurementId: "G-N7LE02NSZ9"
};
        firebase.initializeApp(firebaseConfig);
    }
});
