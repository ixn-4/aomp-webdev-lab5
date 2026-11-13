<?php
require_once 'db.php';

$error_message ="";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // CREATE LIST
    if($_POST['action'] === 'create_list') {
        $list_name = trim($_POST['list_name'] ?? '');
        if (empty($list_name)) {
            $error_message = "List name cannot be empty.";
        } elseif (strlen($list_name) > 255) {
            $error_message = "List name is too long (maximum 255 characters).";
        } else {
            // check for duplicates using a Prepared Statement to prevent SQL injection
            $stmt = $pdo->prepare("SELECT id FROM lists WHERE name =?");
            $stmt->execute([$list_name]);

            // If fetch() returns true, it means a list with that name already exists
            if ($stmt->fetch()) {
                $error_message = "A list with this name already exists.";
            } else {
                // CREATE: Insert a new list into the database
                $stmt = $pdo->prepare("INSERT INTO lists (name) VALUES (?)");
                $stmt->execute([$list_name]);
                $success_message = "List created successfully!";
            }
        }
    }
  
    // UPDATE LIST
    if ($_POST['action'] === 'update_list') {
        $list_id = $_POST['list_id'] ?? '';
        $list_name = trim($_POST['list_name'] ?? '');
        $submitted_tasks = $_POST['tasks'] ?? [];
        $completed_task_ids = $_POST['completed_tasks'] ?? [];
        
        if (!empty($list_name)) {
            // A. Update List Name
            $stmt = $pdo->prepare("UPDATE lists SET name = ? WHERE id = ?");
            $stmt->execute([$list_name, $list_id]);
            
            // B. Handle Tasks
            $submitted_ids = [];
            foreach ($submitted_tasks as $task_id => $task_name) {
                $task_name = trim($task_name);
                if (empty($task_name)) continue;
                
                $is_completed = in_array($task_id, $completed_task_ids) ? 1 : 0;
                
                if (strpos($task_id, 't_') === 0) {
                    // Brand new task added via JS
                    $stmt = $pdo->prepare("INSERT INTO tasks (list_id, task_name, is_completed) VALUES (?, ?, ?)");
                    $stmt->execute([$list_id, $task_name, $is_completed]);
                    $submitted_ids[] = $pdo->lastInsertId(); // Get the new database ID
                } else {
                    // Existing task being updated
                    $stmt = $pdo->prepare("UPDATE tasks SET task_name = ?, is_completed = ? WHERE id = ? AND list_id = ?");
                    $stmt->execute([$task_name, $is_completed, $task_id, $list_id]);
                    $submitted_ids[] = $task_id;
                }
            }
            
            // C. Delete tasks that were removed from the modal
            if (count($submitted_ids) > 0) {
                $in_clause = implode(',', array_fill(0, count($submitted_ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE list_id = ? AND id NOT IN ($in_clause)");
                $stmt->execute(array_merge([$list_id], $submitted_ids));
            } else {
                // If no tasks remain in the modal, delete all tasks for this list
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE list_id = ?");
                $stmt->execute([$list_id]);
            }
            
            $success_message = "List and tasks updated successfully!";
        }
    }

    // DELETE LIST
    if ($_POST['action'] === 'delete_list') {
        $list_id = $_POST['list_id'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM lists WHERE id = ?");
        $stmt->execute([$list_id]);
        $success_message = "List deleted.";
    }

    // PIN / UNPIN LIST
    if ($_POST['action'] === 'pin_list' || $_POST['action'] === 'unpin_list') {
        $list_id = $_POST['list_id'] ?? '';
        $pin = ($_POST['action'] === 'pin_list') ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE lists SET is_pinned = ? WHERE id = ?");
        $stmt->execute([$pin, $list_id]);
        $success_message = "List pinned/unpinned!";
    }

    // ADD TASK
    if ($_POST['action'] === 'add_task') {
        $list_id = $_POST['list_id'] ?? '';
        $new_task = trim($_POST['new_task'] ?? '');
        if (!empty($new_task)) {
            $stmt = $pdo->prepare("INSERT INTO tasks (list_id, task_name) VALUES (?, ?)");
            $stmt->execute([$list_id, $new_task]);
            $success_message = "Task added successfully!";
        }
    }

    // TOGGLE TASK (Check/Uncheck)
    if ($_POST['action'] === 'toggle_task') {
        $task_id = $_POST['task_id'] ?? '';
        $stmt = $pdo->prepare("UPDATE tasks SET is_completed = NOT is_completed WHERE id = ?");
        $stmt->execute([$task_id]);
        $success_message = "Task status updated!";
    }

}

//fetch all lists from database
$stmt =$pdo->query("SELECT * FROM lists ORDER BY is_pinned DESC, name ASC");
$db_lists = $stmt->fetchALl();

$lists = [];

//attach list to each list
foreach ($db_lists as $list) {
  // fetch tasks for this specific list
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE list_id = ?");
    $stmt->execute([$list['id']]);
    $tasks = $stmt->fetchAll();

    foreach ($tasks as &$task) {
        $task['completed'] = $task['is_completed'];
        $task['task_id'] = $task['id'];
    }

    $list['tasks'] = $tasks;
    $lists[] = $list;
}

$pinnedLists = [];
$myLists = [];
foreach ($lists as $list) {
    if (!empty($list['is_pinned'])) {
        $pinnedLists[] = $list;
    } else {
        $myLists[] = $list;
    }
}

// SORT THEM
uasort($pinnedLists, fn($a, $b) => strcasecmp($a['name'], $b['name']));
uasort($myLists, fn($a, $b) => strcasecmp($a['name'], $b['name']));

// STATS FUNCTION
function getCardStats(&$tasks) {
    if (!is_array($tasks)) { $tasks = []; }
    $total = count($tasks);
    $done = 0;
    foreach ($tasks as $task) {
        if (!empty($task['completed'])) { $done++; }
    }
    $percent = ($total > 0) ? round(($done / $total) * 100) : 0;
    return ['total' => $total, 'done' => $done, 'percent' => $percent];
}

$totalAllLists = count($lists);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TOODUE - To-Do List</title>
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

  <main class="container">
    <section class="hero-section">
      <div class="hero-content">
        <h1 class="main-title">
          <img src="images/logo.svg" alt="TOODUE Logo" class="brand-logo"> TOODUE
        </h1>
        <p class="subtitle">A productive day keeps due dates away.</p>
      </div>
    </section>

    <section class="form-section">
       <?php if ($error_message): ?>
          <div style="color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #d32f2f;"><?= $error_message ?></div>
      <?php endif; ?>

      <?php if ($success_message): ?>
          <div style="color: #2e7d32; background: #e8f5e9; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #2e7d32; display: flex; justify-content: space-between; align-items: center;">
              <span><?= $success_message ?></span>
          </div>
      <?php endif; ?>

      <form method="POST" action="index.php" id="create-list-form" class="create-list-form">
        <input type="hidden" name="action" value="create_list">
        <div class="input-group">
          <label for="list-name">Create a new list</label>
          <input type="text" id="list-name" name="list_name" placeholder="Enter new list name" required value="<?= ($error_message && isset($_POST['list_name'])) ? htmlspecialchars($_POST['list_name']) : ''; ?>">
        </div>
        <button type="submit" class="btn-primary">Create List</button>
      </form>
    </section>

    <div class="lists-wrapper">
      <?php if (!empty($pinnedLists)): ?>
      <section class="lists-section" id="pinned-section">
        <div class="lists-header"><div class="lists-title-area"><h2>Pinned</h2></div></div>
        <div class="lists-grid" id="pinned-container">
          <?php foreach ($pinnedLists as $listIndex => $list): ?>
            <?php $stats = getCardStats($list['tasks']); ?>
            <div class="list-card" data-list='<?= json_encode($list, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
              <div class="card-header">
                <div class="card-title">
                  <h3><?= htmlspecialchars($list['name']) ?></h3>
                  <span><?= $stats['done'] ?> of <?= $stats['total'] ?> done (<?= $stats['percent'] ?>%)</span>
                </div>
                <div class="menu-container">
                  <button type="button" class="menu-btn"><img src="images/dots.svg" alt="Options"></button>
                  <div class="dropdown-menu">
                    <form method="POST" action="index.php" style="margin: 0; padding: 0;">
                      <input type="hidden" name="action" value="unpin_list">
                      <input type="hidden" name="list_id" value="<?= htmlspecialchars($list['id']) ?>">
                      <button type="submit" class="dropdown-item pin-list" style="width: 100%;"><img src="images/unpin.svg" alt="Unpin" class="icon-sm"> Unpin</button>
                    </form>
                    <button type="button" class="dropdown-item edit-list" style="width: 100%;"><img src="images/edit.svg" alt="Edit" class="icon-sm"> Edit</button>
                    <form method="POST" action="index.php" style="margin: 0; padding: 0;" class="delete-form">
                      <input type="hidden" name="action" value="delete_list">
                      <input type="hidden" name="list_id" value="<?= htmlspecialchars($list['id'], ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="dropdown-item delete-list" style="width: 100%;"><img src="images/delete.svg" alt="Delete" class="icon-sm"> Delete</button>
                    </form>
                  </div>
                </div>
              </div>
              <ul class="task-items">
                <?php if (empty($list['tasks'])): ?>
                  <li style="color: var(--text-light); font-size: 0.85rem; padding: 0.5rem; text-align: center; font-style: italic;">No tasks yet</li>
                <?php else: ?>
                  <?php foreach ($list['tasks'] as $task): ?>
                    <li class="task-item <?= (!empty($task['completed'])) || (isset($task['status']) && $task['status'] === 'completed') ? 'task-done' : '' ?>">
                      <form method="POST" action="index.php" style="margin: 0; display: flex; align-items: center; width: 100%;">
                        <input type="hidden" name="action" value="toggle_task">
                        <input type="hidden" name="list_id" value="<?= htmlspecialchars($list['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id'] ?? $task['task_id'], ENT_QUOTES, 'UTF-8') ?>">

                        <label class="custom-checkbox" style="margin: 0;">
                          <input type="checkbox" onchange="this.form.submit()" <?= (!empty($task['completed'])) || (isset($task['status']) && $task['status'] === 'completed') ? 'checked' : '' ?>>
                          <span class="checkmark"></span>
                          <span class="task-text"><?= htmlspecialchars($task['task_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                      </form>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="lists-section my-lists-section">
        <div class="lists-header">
          <div class="lists-title-area"><h2>My Lists</h2></div>
          <div class="lists-count" id="list-count-badge"><?= $totalAllLists ?> List<?= $totalAllLists !== 1 ? 's' : '' ?></div>
        </div>

        <div id="empty-state" class="empty-state <?= ($totalAllLists === 0) ? '' : 'hidden' ?>">
          <div class="empty-icon">+</div>
          <h3 class="empty-title">No Existing List</h3>
        </div>

        <div class="lists-grid <?= ($totalAllLists === 0) ? 'hidden' : '' ?>" id="my-lists-container">
          <?php foreach ($myLists as $list): ?>
            <?php $stats = getCardStats($list['tasks']); ?>
            <div class="list-card" data-list='<?= json_encode($list, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>'>
              <div class="card-header">
                <div class="card-title">
                  <h3><?= htmlspecialchars($list['name']) ?></h3>
                  <span><?= $stats['done'] ?> of <?= $stats['total'] ?> done (<?= $stats['percent'] ?>%)</span>
                </div>
                <div class="menu-container">
                  <button type="button" class="menu-btn"><img src="images/dots.svg" alt="Options"></button>
                  <div class="dropdown-menu">
                    <form method="POST" style="margin: 0;">
                      <input type="hidden" name="action" value="pin_list">
                      <input type="hidden" name="list_id" value="<?= htmlspecialchars($list['id'], ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="dropdown-item pin-list" style="width: 100%;"><img src="images/pin.svg" class="icon-sm"> Pin</button>
                    </form>
                    <button type="button" class="dropdown-item edit-list" style="width: 100%;"><img src="images/edit.svg" class="icon-sm"> Edit</button>
                    <form method="POST" action="index.php" style="margin: 0; padding: 0;" class="delete-form">
                      <input type="hidden" name="action" value="delete_list">
                      <input type="hidden" name="list_id" value="<?= htmlspecialchars($list['id'], ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="dropdown-item delete-list" style="width: 100%;"><img src="images/delete.svg" alt="Delete" class="icon-sm"> Delete</button>
                    </form>
                  </div>
                </div>
              </div>
              <ul class="task-items">
                <?php if (empty($list['tasks'])): ?>
                  <li style="color: var(--text-light); font-size: 0.85rem; padding: 0.5rem; text-align: center; font-style: italic;">No tasks yet</li>
                <?php else: ?>
                  <?php foreach ($list['tasks'] as $task): ?>
                    <li class="task-item <?= (!empty($task['completed'])) || (isset($task['status']) && $task['status'] === 'completed') ? 'task-done' : '' ?>">
                      <form method="POST" action="index.php" style="margin: 0; display: flex; align-items: center; width: 100%;">
                        <input type="hidden" name="action" value="toggle_task">
                        <input type="hidden" name="list_id" value="<?= htmlspecialchars($list['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id'] ?? $task['task_id'], ENT_QUOTES, 'UTF-8') ?>">
                        
                        <label class="custom-checkbox" style="margin: 0;">
                          <input type="checkbox" onchange="this.form.submit()" <?= (!empty($task['completed'])) || (isset($task['status']) && $task['status'] === 'completed') ? 'checked' : '' ?>>
                          <span class="checkmark"></span>
                          <span class="task-text"><?= htmlspecialchars($task['task_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                      </form>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </main>

  <div class="modal-overlay" id="edit-modal">
    <div class="edit-list-modal">
      <form method="POST" action="index.php" id="update-modal-form">
        <input type="hidden" name="action" value="update_list">
        <input type="hidden" name="list_id" id="modal-list-id" value="">

        <div class="modal-header">
          <div class="modal-header-left">
            <span class="modal-supertitle">EDIT LIST</span>
            <div class="modal-title-box">
              <input type="text" class="modal-title-input" id="modal-list-name" name="list_name" value="" required>
              <span class="modal-progress" id="modal-progress-text">0 of 0 done</span>
            </div>
          </div>
          <button class="btn-close" id="close-modal" type="button">X</button>
        </div>

        <div class="modal-add-task">
          <label>Add new task</label>
          <div class="add-task-row">
            <input type="text" id="modal-new-task" name="new_task" placeholder="Enter new task">
            <button type="button" class="btn-primary" id="modal-add-task-btn">+ Add</button>
          </div>
        </div>

        <div class="modal-tasks-container">
          <div class="modal-tasks-header"><span id="modal-tasks-count">Current Tasks: 0</span></div>
          <ul class="modal-task-list" id="modal-task-list-items">
          </ul>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn-primary btn-update" id="btn-update">Update</button>
        </div>
      </form>
    </div>
  </div>

  <script src="script.js"></script>
</body>
</html>