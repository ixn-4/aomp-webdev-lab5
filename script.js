document.addEventListener("DOMContentLoaded", () => {
  
  document.addEventListener("submit", (e) => {
    if (e.target.classList.contains("delete-form")) {
      const confirmed = confirm("Are you sure you want to delete this list from the database?");
      if (!confirmed) {
        e.preventDefault();
      }
    }
  });
  
  document.addEventListener('click', (e) => {
    if (e.target.closest('.menu-btn')) {
      e.stopPropagation();
      const btn = e.target.closest('.menu-btn');
      const dropdown = btn.nextElementSibling;
      
      document.querySelectorAll('.dropdown-menu').forEach(menu => {
        if (menu !== dropdown) menu.classList.remove('show');
      });
      
      if (dropdown) dropdown.classList.toggle('show');
    } else {
      document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.classList.remove('show');
      });
    }
  });

  document.addEventListener('click', (e) => {
    const pinBtn = e.target.closest('.pin-list');
    if (pinBtn) {
      const card = pinBtn.closest('.list-card');
      const pinnedContainer = document.getElementById('pinned-container');
      const myListsContainer = document.getElementById('my-lists-container');
      if(card && pinnedContainer && myListsContainer) {
        const isPinned = card.parentElement === pinnedContainer;
        if (isPinned) {
          myListsContainer.appendChild(card);
          pinBtn.innerHTML = '<img src="images/pin.svg" alt="Pin" class="icon-sm"> Pin';
        } else {
          pinnedContainer.appendChild(card);
          pinBtn.innerHTML = '<img src="images/unpin.svg" alt="Unpin" class="icon-sm"> Unpin';
        }
      }
      const dropdown = pinBtn.closest('.dropdown-menu');
      if (dropdown) dropdown.classList.remove('show');
      updateListVisibility();
    }
  });

  const modal = document.getElementById('edit-modal');
  const closeModalBtn = document.getElementById('close-modal');
  const modalListId = document.getElementById('modal-list-id');
  const modalListName = document.getElementById('modal-list-name');
  const modalTaskList = document.getElementById('modal-task-list-items');
  const modalProgressText = document.getElementById('modal-progress-text');
  const modalTasksCount = document.getElementById('modal-tasks-count');
  const modalNewTaskInput = document.getElementById('modal-new-task');
  
  function closeModal() {
    if (modal) modal.classList.remove('active');
    if (modalTaskList) modalTaskList.innerHTML = '';
    if (modalNewTaskInput) modalNewTaskInput.value = '';
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('.edit-list')) {
      const editBtn = e.target.closest('.edit-list');
      const card = editBtn.closest('.list-card');
      if (!card) return;

      const rawData = card.getAttribute('data-list');
      if (!rawData) return;

      const listData = JSON.parse(rawData);
      console.log("I found the list:", listData);

      if (modalListId) modalListId.value = listData.id;
      if (modalListName) modalListName.value = listData.name;

      if (modalTaskList) {
        modalTaskList.innerHTML = '';
        let doneCount = 0;
        const tasks = listData.tasks || [];

        tasks.forEach((t) => {
          if (t.completed) doneCount++;
          const taskId = t.task_id || ('t_' + Math.random().toString(36).substr(2, 9));

          const li = document.createElement('li');
          li.className = 'modal-task-item';
          li.innerHTML = `
            <label class="custom-checkbox">
              <input type="checkbox" name="completed_tasks[]" value="${taskId}" ${t.completed ? 'checked' : ''}>
              <span class="checkmark"></span>
            </label>
            <div class="task-content">
              <input type="text" class="task-text-edit" name="tasks[${taskId}]" value="${t.task_name}" required>
            </div>
            <div class="task-actions">
              <button type="button" class="btn-action delete-task-btn" title="Remove Task">
                <img src="images/delete.svg" alt="Delete" class="icon-sm">
              </button>
            </div>
          `;
          modalTaskList.appendChild(li);
        });

        if (modalProgressText) modalProgressText.textContent = `${doneCount} of ${tasks.length} done`;
        if (modalTasksCount) modalTasksCount.textContent = `Current Tasks: ${tasks.length}`;
      }
      if (modal) modal.classList.add('active');
    }
  });

  if (modalTaskList) {
    modalTaskList.addEventListener('click', (e) => {
      const delBtn = e.target.closest('.delete-task-btn');
      if (delBtn) {
        // ADD THIS CONFIRMATION PROMPT
        if (confirm("Are you sure you want to delete this task? You still need to click Update to save.")) {
          delBtn.closest('.modal-task-item').remove();
          const remainingTasks = modalTaskList.querySelectorAll('.modal-task-item').length;
          if (modalTasksCount) modalTasksCount.textContent = `Current Tasks: ${remainingTasks}`;
        }
      }
    });
  }

  if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }

  const myListsContainer = document.getElementById('my-lists-container');
  const pinnedContainer = document.getElementById('pinned-container');
  const pinnedSection = document.getElementById('pinned-section');
  const emptyState = document.getElementById('empty-state');
  const listCountBadge = document.getElementById('list-count-badge');

  function updateListVisibility() {
    if (!myListsContainer || !emptyState || !listCountBadge) return;
    const normalListCount = myListsContainer.querySelectorAll('.list-card').length;
    const pinnedListCount = pinnedContainer ? pinnedContainer.querySelectorAll('.list-card').length : 0;
    const totalCount = normalListCount + pinnedListCount;

    listCountBadge.textContent = `${totalCount} List${totalCount !== 1 ? 's' : ''}`;

    if (totalCount === 0) {
      emptyState.classList.remove('hidden');
      myListsContainer.classList.add('hidden');
    } else {
      emptyState.classList.add('hidden');
      myListsContainer.classList.remove('hidden');
    }

    if (pinnedSection) {
      if (pinnedListCount === 0) pinnedSection.style.display = 'none';
      else pinnedSection.style.display = 'block';
    }
  }

  updateListVisibility();

  const emptyIcon = document.querySelector('.empty-icon');
  const listNameInput = document.getElementById('list-name'); 
  const createForm = document.getElementById('create-list-form'); 

  if (emptyIcon && listNameInput) {
    emptyIcon.addEventListener('click', () => {
      listNameInput.focus();
      listNameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }
  
  const addTaskBtn = document.getElementById('modal-add-task-btn');
  if (addTaskBtn) {
    addTaskBtn.addEventListener('click', (e) => {
      e.preventDefault(); 
      const taskName = modalNewTaskInput.value.trim();
      if (taskName === '') return;

      const taskId = 't_' + Math.random().toString(36).substr(2, 9);
      const li = document.createElement('li');
      li.className = 'modal-task-item';
      li.innerHTML = `
        <label class="custom-checkbox">
          <input type="checkbox" name="completed_tasks[]" value="${taskId}">
          <span class="checkmark"></span>
        </label>
        <div class="task-content">
          <input type="text" class="task-text-edit" name="tasks[${taskId}]" value="${taskName}" required>
        </div>
        <div class="task-actions">
          <button type="button" class="btn-action delete-task-btn" title="Remove Task">
            <img src="images/delete.svg" alt="Delete" class="icon-sm">
          </button>
        </div>
      `;
      modalTaskList.appendChild(li);
      modalNewTaskInput.value = '';
      
      const remainingTasks = modalTaskList.querySelectorAll('.modal-task-item').length;
      if (modalTasksCount) modalTasksCount.textContent = `Current Tasks: ${remainingTasks}`;
    });
  }
});