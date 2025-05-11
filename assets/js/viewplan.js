function openPopup(id) {
    document.getElementById(id).style.display = 'block';
    
  }
  function closePopup(id) {
    document.getElementById(id).style.display = 'none';
  }
  function toggleInfoDropdown() {
    const dropdown = document.getElementById('infoDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
  }
  document.addEventListener('click', function(event) {
    const button = document.querySelector('.info-btn');
    const dropdown = document.getElementById('infoDropdown');
    if (!button.contains(event.target) && !dropdown.contains(event.target)) {
      dropdown.style.display = 'none';
    }
  });
  function toggleDropdown(id) {
    document
      .getElementById(id)
      .classList
      .toggle('show');
  }
  document.addEventListener('click', function(event) {
   if (      !event.target.closest('.announcement-actions') &&
      !event.target.closest('.comment-actions')
    ) {
      document.querySelectorAll('.dropdown-menu.show')
        .forEach(menu => menu.classList.remove('show'));
    }
  });
  function toggleComments(btn) {
    const commentList = btn.parentElement;         
    const hiddenComments = commentList.querySelectorAll('.hidden-comment');
    const isVisible = btn.getAttribute('data-visible') === 'true';

    if (btn.getAttribute('data-visible') === 'true') {
      hiddenComments.forEach(li => {
    li.style.display = isVisible ? 'none' : '';
  });
  btn.textContent = isVisible ? 'View All Comments' : 'Hide All Comments';
  btn.setAttribute('data-visible', isVisible ? 'false' : 'true');
    } else {
      hiddenComments.forEach(comment => {
        comment.style.display = 'flex';  
      });
      btn.innerText = 'Hide All Comments';
      btn.setAttribute('data-visible', 'true');
    }
  }
  function openDeleteModal(planId, announcementId) {
    document.getElementById('modal-plan-id').value = planId;
    document.getElementById('modal-announcement-id').value = announcementId;
    document.getElementById('deleteModal').style.display = 'flex';
  }

  function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
  }
  function togglePlusDropdown() {
    const plusDropdown = document.getElementById("plusDropdown");
    plusDropdown.style.display = plusDropdown.style.display === "block" ? "none" : "block";
  }

  window.addEventListener('click', function(event) {
    const infoBtn = document.querySelector(".info-btn");
    const plusBtn = document.querySelector(".plus-btn");
    const infoDropdown = document.getElementById("infoDropdown");
    const plusDropdown = document.getElementById("plusDropdown");

    if (!infoBtn.contains(event.target) && !infoDropdown.contains(event.target)) {
      infoDropdown.style.display = "none";
    }
    if (!plusBtn.contains(event.target) && !plusDropdown.contains(event.target)) {
      plusDropdown.style.display = "none";
    }
  });
  document.getElementById('addWebsiteLinkBtn').addEventListener('click', function() {
      var container = document.getElementById('websiteLinksContainer');
      var newInput = document.createElement('input');
      newInput.type = 'url';
      newInput.name = 'announcement_urls[]';
      newInput.placeholder = "https://example.com";
      container.appendChild(newInput);
    });
    
    document.addEventListener("DOMContentLoaded", function() {
    var addEditBtn = document.getElementById("addWebsiteLinkBtn_edit");
    if (addEditBtn) {
      addEditBtn.addEventListener("click", function() {
        var container = document.getElementById("editWebsiteLinksContainer");
        var newInput = document.createElement("input");
        newInput.type = "url";
        newInput.name = "announcement_urls[]";
        newInput.placeholder = "https://example.com";

        if (addEditBtn.nextSibling) {
          container.insertBefore(newInput, addEditBtn.nextSibling);
        } else {
          container.appendChild(newInput);
        }
      });
    }
    
  });



  function addTaskUrl(taskId) {
    const container = document.getElementById(`editTaskUrlsContainer-${taskId}`);
    const input     = document.createElement('input');
    input.type      = 'url';
    input.name      = 'task_urls[]';
    input.placeholder = 'https://example.com';
    container.appendChild(input);
  
  }
  
     document.getElementById('addTaskUrlBtn').addEventListener('click', function() {
      var container = document.getElementById('editTaskUrlsContainer');
      var input = document.createElement('input');
      input.type = 'url';
      input.name = 'task_urls[]';
      input.placeholder = 'https://example.com';
      container.appendChild(input);
    });
  
    function showTaskDetails(taskId) {
    const task = tasks[taskId];
    if (!task) return alert("Task not found.");
  
      console.log("Populating details for", taskId, task);
  
      document.getElementById("taskDetailName").textContent =
        task.name || "—";
      document.getElementById('taskDetailDescription').textContent =
       task.description || '—';
      document.getElementById("taskDetailCompleted").textContent =
        task.completed ? "Yes" : "No";
      document.getElementById("taskDetailDueDate").textContent =
        task.due_date || "N/A";
      document.getElementById("taskDetailDueTime").textContent =
        task.due_time || "N/A";
  
        document.getElementById("editTaskId").value   = taskId;
        document.getElementById("editTaskName").value = task.name || '';
        document.getElementById("editTaskDescription").value = task.description || '';

        if (task.due_date && task.due_time) {
          const iso = `${task.due_date}T${task.due_time}`;
          const dt  = new Date(iso);
      
          const optionsDate = { year: 'numeric', month: 'long', day: 'numeric' };
          const formattedDate = dt.toLocaleDateString('en-US', optionsDate);
      
          const optionsTime = { hour: 'numeric', minute: '2-digit', hour12: true };
          const formattedTime = dt.toLocaleTimeString('en-US', optionsTime);
      
          document.getElementById("taskDetailDueDate").textContent = formattedDate;
          document.getElementById("taskDetailDueTime").textContent = formattedTime;
        } else {
          document.getElementById("taskDetailDueDate").textContent = 'N/A';
          document.getElementById("taskDetailDueTime").textContent = 'N/A';
        }
  
        
        document.getElementById("deleteTaskId").value = taskId;
  
      const assignedEl = document.getElementById("taskDetailAssignedTo");
      if (task.assigned_to && task.assigned_to.length) {
        assignedEl.textContent = task.assigned_to
          .map(e => e.replace(/_/g, "."))
          .join(", ");
      } else {
        assignedEl.textContent = "Unassigned";
      }
  
    const fileContainer = document.getElementById('fileContainer');
    fileContainer.innerHTML = ''; 
    if (task.files && task.files.length) {
      task.files.forEach(url => {
        const ext = url.split('.').pop().toLowerCase();
        const isImage = ['jpg','jpeg','png','gif'].includes(ext);
  
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'file-preview-btn';
        btn.onclick = () => window.open(url,'_blank');
  
        if (isImage) {
          const img = document.createElement('img');
          img.src = url;
          img.alt = 'Preview';
          img.className = 'file-thumbnail';
          btn.appendChild(img);
        } else {
          const icon = document.createElement('i');
          icon.className = 'fas fa-file file-icon';
          btn.appendChild(icon);
        }
  
        const span = document.createElement('span');
        span.className = 'file-name';
        span.innerText = url.split('/').pop();
        btn.appendChild(span);
  
        fileContainer.appendChild(btn);
      });
    } else {
      fileContainer.innerHTML += '<p style="color:#777;font-style:italic;">No files attached.</p>';
    }

    const uploadsContainer = document.getElementById('uploadsContainer');
uploadsContainer.innerHTML = '';
const uploads = task.uploads || {};

if (!Object.keys(uploads).length) {
  uploadsContainer.innerHTML = '<p style="color:#777;font-style:italic;">No uploads found.</p>';
} else {
  Object.entries(uploads).forEach(([email, items]) => {
    const card = document.createElement('div');
    card.className = 'uploader-card';
    card.dataset.email = email;

    const title = document.createElement('h5');
    title.textContent = email.replace(/_/g, '.');
    const count = document.createElement('span');
    count.className = 'upload-count';
    count.textContent = `${items.length} item${items.length > 1 ? 's' : ''}`;

    card.append(title, count);
    uploadsContainer.appendChild(card);

    card.addEventListener('click', () => {
      const body = document.getElementById('uploadsPopupBody');
      body.innerHTML = '';   
      document.getElementById('uploadsPopupTitle').textContent =
        `Uploads by ${email.replace(/_/g, '.')}`;

      items.forEach(item => {
        const { type, url } = item;
        const fileName = url.split('/').pop();
        const ext = fileName.split('.').pop().toLowerCase();

        const thumb = document.createElement('div');
        thumb.className = 'thumb';

        if (type === 'file' && ['jpg','jpeg','png','gif'].includes(ext)) {
          const img = document.createElement('img');
          img.src = url;
          img.alt = fileName;
          thumb.appendChild(img);
        } else {
          const icon = document.createElement('div');
          icon.className = 'icon fas fa-file';
          thumb.appendChild(icon);
        }

        const label = document.createElement('span');
        label.textContent = fileName;
        thumb.appendChild(label);

        thumb.addEventListener('click', () => window.open(url, '_blank'));

        body.appendChild(thumb);
      });

      openPopup('uploadsPopup');
    });
  });
}
    const urlContainer = document.getElementById('urlContainer');
    urlContainer.innerHTML = '';
    if (task.website_urls && task.website_urls.length) {
      task.website_urls.forEach(url => {
        const host = new URL(url).hostname;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'website-link-btn';
        btn.onclick = () => window.open(url,'_blank');
  
        const favicon = document.createElement('img');
        favicon.src = `https://www.google.com/s2/favicons?domain=${host}`;
        favicon.alt = '';
        favicon.className = 'website-favicon';
        btn.appendChild(favicon);
  
        const span = document.createElement('span');
        span.className = 'website-name';
        span.innerText = host;
        btn.appendChild(span);
  
        urlContainer.appendChild(btn);
      });
    } else {
      urlContainer.innerHTML += '<p style="color:#777;font-style:italic;">No URLs provided.</p>';
    }
    document.getElementById('assignFormTaskId').value = taskId;

  document.querySelectorAll('#assignCheckboxes input[type=checkbox]')
    .forEach(cb => {
      cb.checked = Array.isArray(task.assigned_to)
                 && task.assigned_to.includes(cb.value);
    });
    
    openTab(null, 'TaskDetails');
    
  }
  
    
    function closeTaskDetails() {
   
    const tasksTab = document.getElementById('tab-Tasks');
    openTab({ currentTarget: tasksTab }, 'Tasks');
  }
  function openAssignPopup() {
    openPopup('assignPopup');
  }

