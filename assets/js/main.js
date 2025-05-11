let taskArray = [];
function openPopup() { document.getElementById('popupForm').style.display = 'block'; }
function closePopup(){ document.getElementById('popupForm').style.display = 'none'; }
function addTask() {
  const name = document.getElementById('newTask').value.trim();
  const dueDate = document.getElementById('taskDueDate').value;
  const dueTime = document.getElementById('taskDueTime').value;

  if (!name || !dueDate || !dueTime) return;

  const task = {
    name,
    due_date: dueDate,
    due_time: dueTime
  };

  taskArray.push(task);
  document.getElementById('tasks_json').value = JSON.stringify(taskArray);

  const li = document.createElement('li');
  li.textContent = `${name} - Due: ${dueDate} ${dueTime}`;
  document.getElementById('taskList').appendChild(li);
  document.getElementById('newTask').value = '';
  document.getElementById('taskDueDate').value = '';
  document.getElementById('taskDueTime').value = '';
}


document.addEventListener("DOMContentLoaded", function () {
  const userData = JSON.parse(sessionStorage.getItem("userData"));
  if (userData && userData.photoURL) {
      document.getElementById("profilePic").src = userData.photoURL;
  } else {
      console.log("User photo not found in session storage.");
  }
});
