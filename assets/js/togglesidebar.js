function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;

  if (window.innerWidth <= 1024 ) {
    sidebar.classList.toggle('active');
    if (sidebar.classList.contains('active')) {
      body.style.paddingLeft = '270px'; 
    } else {
      body.style.paddingLeft = '0'; 
    }
  } else {
    sidebar.classList.toggle('collapsed');
    if (sidebar.classList.contains('collapsed')) {
      body.style.paddingLeft = '0'; 
    } else {
      body.style.paddingLeft = '270px'; 
    }
  }
}

window.addEventListener('click', function (e) {
  const sidebar = document.querySelector('.sidebar');
  const hamburger = document.querySelector('.hamburger');
  const body = document.body;

  if (window.innerWidth <= 1024) {
    if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
      sidebar.classList.remove('active');
      body.style.paddingLeft = '0'; 
    }
  }
});

let previousWidth = window.innerWidth; 
window.addEventListener('resize', function () {
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;
  const currentWidth = window.innerWidth;

  if (currentWidth > 768 && previousWidth <= 768) {

    sidebar.classList.remove('active'); 
    body.style.paddingLeft = '250px'; 
  } else if (currentWidth <= 768 && previousWidth > 768) {
    sidebar.classList.remove('collapsed'); 
    body.style.paddingLeft = '0'; 
  }

  previousWidth = currentWidth; 
});