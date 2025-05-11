

<div class="popup" id="pendingPopup">
<div class="popup-content elegant-popup">
<span class="close-popup" onclick="closePopup('pendingPopup')">&times;</span>
    <h4>Pending Invites</h4>
    <?php
      $pending = [];
      if (!empty($plan['invited']) && is_array($plan['invited'])) {
          foreach ($plan['invited'] as $emailKey => $status) {
              if ($status !== 'accepted') {
                  $pending[$emailKey] = $status;
              }
          }
      }
    ?>
    <ul class="pending-list">
      <?php if (!empty($pending)): ?>
        <?php foreach ($pending as $emailKey => $status): ?>
          <li><?= htmlspecialchars(str_replace('_', '.', $emailKey)) ?> — <em><?= htmlspecialchars($status) ?></em></li>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No pending invites.</p>
      <?php endif; ?>
    </ul>
  </div>
</div>

<div class="banner-popup" id="bannerPopup" style="display: none;">
  <div class="banner-popup__content">
    <span class="banner-popup__close" onclick="closeBannerPopup()">&times;</span>
    <h4 class="banner-popup__title">Select a Banner</h4>

    <form id="bannerForm" method="POST">
      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">

      <div class="banner-popup__group">
        <label class="banner-popup__label">Banner:</label>
        <div class="banner-popup__options">
          <?php for ($i = 1; $i <= 6; $i++): ?>
            <label class="banner-popup__option">
              <input type="radio" name="banner" value="banner<?= $i ?>.jpg" <?= $i === 1 ? 'checked' : '' ?>>
              <img src="<?= $bannerImgPath ?>banner<?= $i ?>.jpg" alt="Banner <?= $i ?>">
            </label>
          <?php endfor; ?>

          <label class="banner-popup__option banner-popup__option--color">
            <input type="radio" name="banner" id="bannerCustomRadio" value="custom">
            Custom Color:
            <input 
              type="color" 
              id="bannerColorPicker" 
              name="banner_color" 
              value="#FF5733" 
              disabled 
              title="Pick a custom color"
            >
          </label>
        </div>
      </div>

      <div class="banner-popup__actions">
        <button type="submit" name="edit_banner" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>


<div class="popup" id="announcementPopup">
  <div class="popup-content elegant-popup">
    <h4>Create an Announcement</h4>
    <span class="close-popup" onclick="closePopup('announcementPopup')">&times;</span>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
      
      <textarea name="announcement_text" placeholder="Write your announcement..." required></textarea>
      <label for="announcement_code_lang">Code Language:</label>
  <select name="announcement_code_lang" id="announcement_code_lang">
    <option value="">– none –</option>
    <option value="php">PHP</option>
    <option value="javascript">JavaScript</option>
    <option value="python">Python</option>
    <option value="html">HTML</option>
    <option value="css">CSS</option>
  </select>

  <label for="announcement_code">Include Code Snippet:</label>
  <textarea
    name="announcement_code"
    id="announcement_code"
    placeholder="Paste your code here…"
    style="font-family: monospace; min-height: 100px;" ></textarea>
      <label for="announcement_files">Attach Files:</label>
      <input 
        type="file" 
        name="announcement_files[]" 
        id="announcement_files" 
        accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" 
        multiple
      >

<label>Website Links:</label>
<div id="websiteLinksContainer" class="url-input-stack">
<button type="button" id="addWebsiteLinkBtn" class="add-link-btn">Add</button>
  <input type="url" name="announcement_urls[]" placeholder="https://example.com">
</div>
      
      <button type="submit" name="add_announcement" class="submit-btn">Post Announcement</button>
    </form>
  </div>
</div>


<div class="popup" id="notesPopup">
  <div class="popup-content elegant-popup">
    <h4>Add Note</h4>
    <span class="close-popup" onclick="closePopup('notesPopup')">&times;</span>
    <form method="POST">
      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
      <textarea name="note_text" placeholder="Write your note..." required></textarea>
      <button type="submit" name="add_note" class="submit-btn">Add Note</button>
    </form>
  </div>
</div>

<div class="popup" id="taskPopup">
  <div class="popup-content elegant-popup">
    <span class="close-popup" onclick="closePopup('taskPopup')">&times;</span>
    <h4>Add New Task</h4>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
      
      <div class="form-group">
        <label for="task_name">Task Name</label>
        <input type="text" id="task_name" name="task_name" required>
      </div>
      <div class="form-group">
      <label for="task_description">Description </label>
      <textarea id="task_description"
                name="task_description"
                rows="3"
                placeholder="A few details about this task…"></textarea>
    </div>
      <div class="form-group-inline">
        <div>
          <label for="due_date">Due Date</label>
          <input type="date" id="due_date" name="due_date" required>
        </div>
        <div>
          <label for="due_time">Due Time</label>
          <input type="time" id="due_time" name="due_time" required>
        </div>
      </div>
      
      <div class="form-group">
        <label>Assign To</label>
        <?php if (!empty($accepted)): ?>
          <?php foreach ($accepted as $emailKey): ?>
            <div class="checkbox-group">
              <label>
                <input type="checkbox" name="assigned_to[]" value="<?= htmlspecialchars($emailKey) ?>">
                <?= htmlspecialchars(str_replace('_', '.', $emailKey)) ?>
              </label>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No accepted collaborators</p>
        <?php endif; ?>
      </div>
      
      <div class="form-group">
        <label for="task_files">Attach Files / Photos</label>
        <input type="file" id="task_files" name="task_files[]"
               accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
               multiple>
      </div>
      
      <div class="form-group">
        <label>Website Links</label>
        <div id="task-websiteLinksContainer" class="url-input-stack">
          <input type="url" name="task_urls[]" placeholder="https://example.com">
          <button type="button" id="task-addWebsiteLinkBtn" class="add-link-btn">Add</button>
        </div>
      </div>
      
      <button type="submit" name="add_task" class="submit-btn">Add Task</button>
    </form>
  </div>
</div>


<div id="deleteModal" class="popup ">
  <div class="popup-content-delete">
    <h3>Delete this announcement?</h3>
    <p>You can't undo this action.</p>
    <form method="POST">
      <input type="hidden" name="plan_id" id="modal-plan-id">
      <input type="hidden" name="announcement_id" id="modal-announcement-id">
      <button type="submit" name="delete_announcement" class="deletebutton">Yes, Delete</button>
      <button type="button" class="cancelbutton" onclick="closeDeleteModal()">Cancel</button>
    </form>
  </div>
</div>

<div class="popup" id="invitePopup">
<div class="popup-content elegant-popup">
<span class="close-popup" onclick="closePopup('invitePopup')">&times;</span>
    <h4>Invite Collaborator</h4>
    <form method="POST">
      <input type="hidden" name="plan_id" value="<?= htmlspecialchars($planId) ?>">
      <div class="form-group">
        <label for="invite_email">Email</label>
        <input type="email" id="invite_email" name="invite_email" placeholder="user@example.com" required>
      </div>
      <div class="form-group">
        <label for="invite_role">Role</label>
        <select id="invite_role" name="invite_role">
          <option value="collaborator" selected>Collaborator</option>
          <option value="assistant admin">Assistant Admin</option>
        </select>
      </div>
      <button type="submit" name="invite_user">Send Invite</button>
    </form>
  </div>
</div>

      