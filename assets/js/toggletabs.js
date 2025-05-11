function openTab(evt, tabName) {
  var tabcontent = document.getElementsByClassName("tabcontent");
  for (var i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  
  var tablinks = document.getElementsByClassName("tablinks");
  for (var i = 0; i < tablinks.length; i++) {
    tablinks[i].classList.remove("active");
  }
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.classList.add("active");

  var banner = document.querySelector(".banner");
  if (tabName === "Cards") {
    banner.style.display = "block";
  } else {
    banner.style.display = "none";
  }
  
}
function openSubTab(evt, subTabId) {
  const tasksPanel = evt.currentTarget.closest('#Tasks');

  tasksPanel
    .querySelectorAll('.subtabcontent')
    .forEach(panel => panel.style.display = 'none');

  tasksPanel
    .querySelectorAll('.subtablinks')
    .forEach(btn => btn.classList.remove('active'));

  document.getElementById(subTabId).style.display = 'block';
  evt.currentTarget.classList.add('active');
}
