 <?php
// project_details.php - Displays comprehensive details for a single project, manages tasks, and project comments
require_once 'config.php'; // Includes database connection ($pdo) and session_start()
require_once 'helpers.php'; // Include our new helper functions

// --- Access Control: Only logged-in users can view this page ---
if (!isset($_SESSION['user_id'])) {
    redirect('auth_portal.php'); // Redirect to login if not logged in
}

$message = '';
$project_details = null;
$tasks = []; // Array to hold fetched tasks for this project
$comments = []; // Array to hold fetched comments for this project

// Get project_id from URL
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    $message = '<p style="color: red;">Invalid project ID.</p>';
} else {
    try {
        // --- Fetch Project Details & Authorization ---
        $stmt_project = $pdo->prepare("
            SELECT
                p.project_id,
                p.project_name,
                p.description,
                p.status,
                p.start_date,
                p.end_date,
                p.created_at,
                m.mentee_id AS project_owner_mentee_id,
                u_mentee.user_id AS mentee_user_id,
                u_mentee.email AS mentee_email,
                CONCAT(u_mentee.first_name, ' ', u_mentee.last_name, ' (', u_mentee.username, ')') AS mentee_full_name,
                GROUP_CONCAT(DISTINCT CONCAT(u_mentor.first_name, ' ', u_mentor.last_name) SEPARATOR ', ') AS assigned_mentor_names,
                GROUP_CONCAT(DISTINCT ma.mentor_user_id) AS assigned_mentor_user_ids
            FROM
                projects p
            JOIN
                mentees m ON p.project_owner_mentee_id = m.mentee_id
            JOIN
                users u_mentee ON m.user_id = u_mentee.user_id
            LEFT JOIN
                mentorship_assignments ma ON p.project_id = ma.project_id
            LEFT JOIN
                users u_mentor ON ma.mentor_user_id = u_mentor.user_id AND (SELECT r.role_name FROM roles r WHERE r.role_id = u_mentor.role_id) = 'Mentor'
            WHERE
                p.project_id = ?
            GROUP BY
                p.project_id
        ");
        $stmt_project->execute([$project_id]);
        $project_details = $stmt_project->fetch(PDO::FETCH_ASSOC);

        // --- Authorization Check (Refined for comment posting/editing) ---
        $is_project_owner = ($project_details && $_SESSION['role_name'] === 'Mentee' && $_SESSION['user_id'] == $project_details['mentee_user_id']);
        $is_assigned_mentor = ($project_details && $_SESSION['role_name'] === 'Mentor' && in_array($_SESSION['user_id'], explode(',', $project_details['assigned_mentor_user_ids'] ?? '')));
        $is_admin_or_coordinator = ($_SESSION['role_name'] === 'Administrator' || $_SESSION['role_name'] === 'Coordinator');

        $authorized_to_view = $is_project_owner || $is_assigned_mentor || $is_admin_or_coordinator;
        $can_manage_tasks = $authorized_to_view;
        $can_post_comment = $authorized_to_view;

        if (!$project_details || !$authorized_to_view) {
            $_SESSION['project_details_message'] = '<p style="color: red;">Project not found or you are not authorized to view this project.</p>';
            redirect('dashboard.php');
        }

        // --- Fetch all users (for task assignment dropdown) ---
        $available_users = [];
        $stmt_users = $pdo->query("SELECT user_id, first_name, last_name, username FROM users ORDER BY first_name, last_name");
        $available_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("PMMS Project Details Error (initial fetch/auth): " . $e->getMessage());
        $_SESSION['project_details_message'] = '<p style="color: red;">Error fetching project details from the database.</p>';
        redirect('dashboard.php');
    }
}

// --- Handle Task Creation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task']) && $can_manage_tasks) {
    $task_name = trim($_POST['task_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $assigned_to_user_id = (int)($_POST['assigned_to_user_id'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');

    $isValidDueDate = true;
    if (!empty($due_date) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $due_date)) {
        $isValidDueDate = false;
        $message = '<p style="color: red;">Due Date must be in YYYY-MM-DD format.</p>';
    }

    if ($isValidDueDate && empty($task_name)) {
        $message = '<p style="color: red;">Task name cannot be empty.</p>';
    } elseif ($isValidDueDate && $project_id <= 0) {
        $message = '<p style="color: red;">Invalid project ID for task creation.</p>';
    } elseif ($isValidDueDate) {
        try {
            $stmt_insert_task = $pdo->prepare("
                INSERT INTO tasks (project_id, task_name, description, assigned_to_user_id, due_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt_insert_task->execute([
                $project_id,
                $task_name,
                empty($description) ? null : $description,
                $assigned_to_user_id > 0 ? $assigned_to_user_id : null,
                empty($due_date) ? null : $due_date
            ]);
            $message = '<p style="color: green;">Task added successfully!</p>';

            // --- NEW: SEND EMAIL NOTIFICATION ON TASK ASSIGNMENT ---
            if ($assigned_to_user_id > 0) {
                $stmt_assigned_user = $pdo->prepare("SELECT email, first_name FROM users WHERE user_id = ?");
                $stmt_assigned_user->execute([$assigned_to_user_id]);
                $assigned_user = $stmt_assigned_user->fetch(PDO::FETCH_ASSOC);

                if ($assigned_user && !empty($assigned_user['email'])) {
                    $recipient = $assigned_user['email'];
                    $subject = "New Task Assigned: " . $task_name;
                    $email_body = "
                        <html>
                        <body>
                            <p>Hello " . htmlspecialchars($assigned_user['first_name']) . ",</p>
                            <p>A new task has been assigned to you on project <strong>" . htmlspecialchars($project_details['project_name']) . "</strong>:</p>
                            <p><strong>Task:</strong> " . htmlspecialchars($task_name) . "</p>
                            <p><strong>Description:</strong> " . nl2br(htmlspecialchars($description)) . "</p>
                            <p><strong>Due Date:</strong> " . htmlspecialchars($due_date ?? 'N/A') . "</p>
                            <p>You can view the project details <a href='http://localhost/pmms/project_details.php?id=" . $project_id . "'>here</a>.</p>
                            <p>Thank you,</p>
                            <p>Project Management System Team</p>
                        </body>
                        </html>
                    ";
                    if (!send_email($recipient, $subject, $email_body)) {
                        error_log("Failed to send task assignment email to user_id: " . $assigned_to_user_id);
                        $message .= '<p style="color: orange;">(Failed to send task assignment email)</p>';
                    }
                }
            }
            
        } catch (PDOException $e) {
            error_log("PMMS Project Details Error (add task): " . $e->getMessage());
            $message = '<p style="color: red;">Error adding task: ' . $e->getMessage() . '</p>';
        }
    }
}

// --- Handle Task Status Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status']) && $can_manage_tasks) {
    $task_id = (int)$_POST['task_id'];
    $new_status = trim($_POST['new_status'] ?? '');

    $allowed_statuses = ['Pending', 'In Progress', 'Completed', 'On Hold'];
    if (!in_array($new_status, $allowed_statuses)) {
        $message = '<p style="color: red;">Invalid task status provided.</p>';
    } else {
        try {
            $stmt_update_task_status = $pdo->prepare("UPDATE tasks SET status = ? WHERE task_id = ? AND project_id = ?");
            $stmt_update_task_status->execute([$new_status, $task_id, $project_id]);
            $message = '<p style="color: green;">Task status updated successfully!</p>';
        } catch (PDOException $e) {
            error_log("PMMS Project Details Error (update task status): " . $e->getMessage());
            $message = '<p style="color: red;">Error updating task status: ' . $e->getMessage() . '</p>';
        }
    }
}

// --- Handle Comment Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment']) && $can_post_comment) {
    $comment_text = trim($_POST['comment_text'] ?? '');

    if (empty($comment_text)) {
        $message = '<p style="color: red;">Comment cannot be empty.</p>';
    } elseif ($project_id <= 0) {
        $message = '<p style="color: red;">Invalid project ID for comment creation.</p>';
    } else {
        try {
            $stmt_insert_comment = $pdo->prepare("
                INSERT INTO project_comments (project_id, user_id, comment_text)
                VALUES (?, ?, ?)
            ");
            $stmt_insert_comment->execute([
                $project_id,
                $_SESSION['user_id'],
                $comment_text
            ]);
            $message = '<p style="color: green;">Comment added successfully!</p>';

            // --- NEW: SEND EMAIL NOTIFICATION ON NEW COMMENT ---
            // Only notify the project owner if the comment wasn't made by them
            if ($project_details['mentee_user_id'] !== $_SESSION['user_id'] && !empty($project_details['mentee_email'])) {
                $recipient = $project_details['mentee_email'];
                $subject = "New Comment on Your Project: " . $project_details['project_name'];
                $comment_author_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
                $email_body = "
                    <html>
                    <body>
                        <p>Hello " . htmlspecialchars($project_details['mentee_full_name']) . ",</p>
                        <p>A new comment has been posted on your project, <strong>" . htmlspecialchars($project_details['project_name']) . "</strong>.</p>
                        <p><strong>From:</strong> " . htmlspecialchars($comment_author_name) . "</p>
                        <p><strong>Comment:</strong> " . nl2br(htmlspecialchars($comment_text)) . "</p>
                        <p>You can view the project details and reply <a href='http://localhost/pmms/project_details.php?id=" . $project_id . "'>here</a>.</p>
                        <p>Thank you,</p>
                        <p>Project Management System Team</p>
                    </body>
                    </html>
                ";
                if (!send_email($recipient, $subject, $email_body)) {
                    error_log("Failed to send new comment email for project_id: " . $project_id);
                    $message .= '<p style="color: orange;">(Failed to send new comment email)</p>';
                }
            }

        } catch (PDOException $e) {
            error_log("PMMS Project Details Error (add comment): " . $e->getMessage());
            $message = '<p style="color: red;">Error adding comment: ' . $e->getMessage() . '</p>';
        }
    }
}

// --- Handle Comment Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = (int)$_POST['comment_id'];
    $comment_user_id = (int)$_POST['comment_user_id'];

    $is_comment_owner = ($comment_user_id === $_SESSION['user_id']);
    $can_delete_any_comment = ($is_admin_or_coordinator);

    if ($comment_id <= 0) {
        $message = '<p style="color: red;">Invalid comment ID for deletion.</p>';
    } elseif (!$is_comment_owner && !$can_delete_any_comment) {
        $message = '<p style="color: red;">You are not authorized to delete this comment.</p>';
    } else {
        try {
            $stmt_delete_comment = $pdo->prepare("DELETE FROM project_comments WHERE comment_id = ? AND project_id = ?");
            $stmt_delete_comment->execute([$comment_id, $project_id]);
            $message = '<p style="color: green;">Comment deleted successfully!</p>';
        } catch (PDOException $e) {
            error_log("PMMS Project Details Error (delete comment): " . $e->getMessage());
            $message = '<p style="color: red;">Error deleting comment: ' . $e->getMessage() . '</p>';
        }
    }
}

// --- Handle Comment Editing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_comment_submit'])) {
    $comment_id = (int)$_POST['comment_id'];
    $updated_comment_text = trim($_POST['updated_comment_text'] ?? '');
    $comment_user_id = (int)$_POST['comment_user_id'];

    $is_comment_owner = ($comment_user_id === $_SESSION['user_id']);

    if ($comment_id <= 0 || empty($updated_comment_text)) {
        $message = '<p style="color: red;">Invalid comment ID or empty comment text for update.</p>';
    } elseif (!$is_comment_owner) {
        $message = '<p style="color: red;">You are not authorized to edit this comment.</p>';
    } else {
        try {
            $stmt_update_comment = $pdo->prepare("UPDATE project_comments SET comment_text = ?, created_at = CURRENT_TIMESTAMP WHERE comment_id = ? AND project_id = ? AND user_id = ?");
            $stmt_update_comment->execute([$updated_comment_text, $comment_id, $project_id, $_SESSION['user_id']]);
            $message = '<p style="color: green;">Comment updated successfully!</p>';
        } catch (PDOException $e) {
            error_log("PMMS Project Details Error (edit comment): " . $e->getMessage());
            $message = '<p style="color: red;">Error updating comment: ' . $e->getMessage() . '</p>';
        }
    }
}


// --- Fetch Tasks for the Project (after any form submissions) ---
if ($project_details) {
    try {
        $stmt_tasks = $pdo->prepare("
            SELECT
                t.task_id,
                t.task_name,
                t.description,
                t.status,
                t.due_date,
                t.created_at,
                t.updated_at,
                CONCAT(u.first_name, ' ', u.last_name, ' (', u.username, ')') AS assigned_to_name,
                t.assigned_to_user_id
            FROM
                tasks t
            LEFT JOIN
                users u ON t.assigned_to_user_id = u.user_id
            WHERE
                t.project_id = ?
            ORDER BY
                t.status ASC, t.due_date ASC, t.created_at DESC
        ");
        $stmt_tasks->execute([$project_id]);
        $tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PMMS Project Details Error (fetching tasks): " . $e->getMessage());
        $message = '<p style="color: red;">Error fetching tasks for this project: ' . $e->getMessage() . '</p>';
    }

    // --- Fetch Comments for the Project (after any form submissions) ---
    try {
        $stmt_comments = $pdo->prepare("
            SELECT
                pc.comment_id,
                pc.comment_text,
                pc.created_at,
                pc.user_id AS comment_author_user_id,
                u.first_name,
                u.last_name,
                u.username,
                r.role_name
            FROM
                project_comments pc
            JOIN
                users u ON pc.user_id = u.user_id
            JOIN
                roles r ON u.role_id = r.role_id
            WHERE
                pc.project_id = ?
            ORDER BY
                pc.created_at DESC
        ");
        $stmt_comments->execute([$project_id]);
        $comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PMMS Project Details Error (fetching comments): " . $e->getMessage());
        $message = '<p style="color: red;">Error fetching comments for this project: ' . $e->getMessage() . '</p>';
    }
}

// Check for and display messages from session
if (isset($_SESSION['project_details_message'])) {
    $message = $_SESSION['project_details_message'];
    unset($_SESSION['project_details_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMMS - Project Details</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 1000px; margin: 20px auto; }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        .message { margin-bottom: 20px; text-align: center; }
        .detail-group { margin-bottom: 15px; }
        .detail-group label { font-weight: bold; color: #555; display: block; margin-bottom: 5px; }
        .detail-group p { margin: 0; padding: 5px 0; border-bottom: 1px dashed #eee; }
        .back-link { margin-bottom: 20px; }
        .back-link a { color: #007bff; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            color: white;
            text-transform: capitalize;
        }
        /* Project status colors */
        .status-proposed { background-color: #ffc107; } /* yellow */
        .status-approved { background-color: #28a745; } /* green */
        .status-in-progress { background-color: #007bff; } /* blue */
        .status-completed { background-color: #6c757d; } /* grey */
        .status-rejected { background-color: #dc3545; } /* red */

        /* Task status colors */
        .task-status-Pending { background-color: #ffc107; }
        .task-status-In-Progress { background-color: #007bff; }
        .task-status-Completed { background-color: #28a745; }
        .task-status-On-Hold { background-color: #6c757d; }

        .add-task-form, .add-comment-form { background-color: #f9f9f9; padding: 20px; border-radius: 8px; margin-top: 20px; border: 1px solid #eee; }
        .add-task-form h3, .add-comment-form h3 { margin-top: 0; color: #333; }
        .add-task-form .form-group, .add-comment-form .form-group { margin-bottom: 10px; }
        .add-task-form label, .add-comment-form label { display: block; margin-bottom: 5px; font-weight: bold; }
        .add-task-form input[type="text"],
        .add-task-form textarea,
        .add-task-form input[type="date"],
        .add-task-form select,
        .add-comment-form textarea {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .add-task-form button, .add-comment-form button, .comment-actions button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        .add-task-form button:hover, .add-comment-form button:hover, .comment-actions button:hover { background-color: #0056b3; }
        .comment-actions button.delete { background-color: #dc3545; margin-left: 5px; }
        .comment-actions button.delete:hover { background-color: #c82333; }
        .comment-actions button.edit-save { background-color: #28a745; margin-left: 5px; }
        .comment-actions button.edit-save:hover { background-color: #218838; }
        .comment-actions button.edit-cancel { background-color: #6c757d; margin-left: 5px; }
        .comment-actions button.edit-cancel:hover { background-color: #5a6268; }

        .tasks-list, .comments-list { margin-top: 30px; }
        .tasks-list h3, .comments-list h3 { color: #333; margin-bottom: 15px; }
        .tasks-list table { width: 100%; border-collapse: collapse; }
        .tasks-list th, .tasks-list td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        .tasks-list th { background-color: #e9ecef; }
        .tasks-list .task-description {
            max-height: 60px;
            overflow-y: auto;
            font-size: 0.9em;
            color: #666;
        }
        .task-status-select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
            width: 100%;
        }

        /* Comments Styling */
        .comment-item {
            border: 1px solid #eee;
            background-color: #fcfcfc;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
        }
        .comment-meta {
            font-size: 0.9em;
            color: #777;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .comment-meta strong {
            color: #333;
        }
        .comment-meta span.role {
            font-style: italic;
            margin-left: 5px;
            color: #007bff;
        }
        .comment-text {
            line-height: 1.6;
            color: #444;
            white-space: pre-wrap; /* Preserve whitespace and line breaks */
            word-wrap: break-word; /* Break long words */
        }
        .comment-actions {
            margin-top: 10px;
            text-align: right;
        }
        .comment-actions button {
            padding: 5px 10px;
            font-size: 0.9em;
            margin-left: 5px;
            cursor: pointer;
        }
        .comment-actions button:first-child { margin-left: 0; }
        .edit-comment-textarea {
            width: calc(100% - 12px);
            padding: 5px;
            margin-top: 5px;
            margin-bottom: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function toggleEditMode(commentId) {
                const commentItem = document.getElementById('comment-item-' + commentId);
                const commentTextElement = commentItem.querySelector('.comment-text');
                const editForm = commentItem.querySelector('.edit-comment-form');
                const actionsDiv = commentItem.querySelector('.comment-actions');

                if (editForm.style.display === 'none' || editForm.style.display === '') {
                    commentTextElement.style.display = 'none';
                    editForm.style.display = 'block';
                    actionsDiv.querySelector('.edit-button').style.display = 'none';
                    actionsDiv.querySelector('.delete-button').style.display = 'none';
                    actionsDiv.querySelector('.edit-save-button').style.display = 'inline-block';
                    actionsDiv.querySelector('.edit-cancel-button').style.display = 'inline-block';
                    editForm.querySelector('textarea').focus();
                } else {
                    commentTextElement.style.display = 'block';
                    editForm.style.display = 'none';
                    actionsDiv.querySelector('.edit-button').style.display = 'inline-block';
                    actionsDiv.querySelector('.delete-button').style.display = 'inline-block';
                    actionsDiv.querySelector('.edit-save-button').style.display = 'none';
                    actionsDiv.querySelector('.edit-cancel-button').style.display = 'none';
                }
            }

            document.querySelectorAll('.edit-button').forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.dataset.commentId;
                    toggleEditMode(commentId);
                });
            });

            document.querySelectorAll('.edit-cancel-button').forEach(button => {
                button.addEventListener('click', function() {
                    const commentId = this.dataset.commentId;
                    toggleEditMode(commentId);
                });
            });

            document.querySelectorAll('.delete-button').forEach(button => {
                button.addEventListener('click', function(event) {
                    if (!confirm('Are you sure you want to delete this comment?')) {
                        event.preventDefault();
                    }
                });
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="javascript:history.back()">&larr; Back to Previous Page</a>
        </div>
        <h1>Project Details</h1>
        <?php echo $message; ?>

        <?php if ($project_details): ?>
            <div class="detail-group">
                <label>Project Name:</label>
                <p><?php echo htmlspecialchars($project_details['project_name']); ?></p>
            </div>
            <div class="detail-group">
                <label>Description:</label>
                <p><?php echo nl2br(htmlspecialchars($project_details['description'])); ?></p>
            </div>
            <div class="detail-group">
                <label>Status:</label>
                <p>
                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project_details['status'])); ?>">
                        <?php echo htmlspecialchars($project_details['status']); ?>
                    </span>
                </p>
            </div>
            <div class="detail-group">
                <label>Mentee (Project Owner):</label>
                <p><?php echo htmlspecialchars($project_details['mentee_full_name']); ?></p>
            </div>
            <div class="detail-group">
                <label>Assigned Mentor(s):</label>
                <p><?php echo htmlspecialchars($project_details['assigned_mentor_names'] ?? 'None'); ?></p>
            </div>
            <div class="detail-group">
                <label>Proposed Start Date:</label>
                <p><?php echo htmlspecialchars($project_details['start_date']); ?></p>
            </div>
            <div class="detail-group">
                <label>Proposed End Date:</label>
                <p><?php echo htmlspecialchars($project_details['end_date'] ?? 'N/A'); ?></p>
            </div>
            <div class="detail-group">
                <label>Submitted On:</label>
                <p><?php echo date('Y-m-d H:i', strtotime($project_details['created_at'])); ?></p>
            </div>

            <hr>

            <?php if ($can_manage_tasks): ?>
            <div class="add-task-form">
                <h3>Add New Task</h3>
                <form action="project_details.php?id=<?php echo $project_id; ?>" method="POST">
                    <div class="form-group">
                        <label for="task_name">Task Name:</label>
                        <input type="text" id="task_name" name="task_name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description (Optional):</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="assigned_to_user_id">Assign To (Optional):</label>
                        <select id="assigned_to_user_id" name="assigned_to_user_id">
                            <option value="">-- Select User --</option>
                            <?php foreach ($available_users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="due_date">Due Date (Optional):</label>
                        <input type="date" id="due_date" name="due_date">
                    </div>
                    <button type="submit" name="add_task">Add Task</button>
                </form>
            </div>
            <?php else: ?>
                <p style="text-align: center; margin-top: 20px;">You do not have permission to add tasks to this project.</p>
            <?php endif; ?>

            <div class="tasks-list">
                <h3>Project Tasks (<?php echo count($tasks); ?>)</h3>
                <?php if (empty($tasks)): ?>
                    <p>No tasks defined for this project yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Task Name</th>
                                <th>Description</th>
                                <th>Assigned To</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Created On</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                                    <td><div class="task-description"><?php echo nl2br(htmlspecialchars($task['description'] ?? 'N/A')); ?></div></td>
                                    <td><?php echo htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($task['due_date'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($can_manage_tasks): ?>
                                        <form action="project_details.php?id=<?php echo $project_id; ?>" method="POST" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                            <select name="new_status" class="task-status-select task-status-<?php echo str_replace(' ', '-', $task['status']); ?>" onchange="this.form.submit()">
                                                <option value="Pending" <?php echo ($task['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="In Progress" <?php echo ($task['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="Completed" <?php echo ($task['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                                <option value="On Hold" <?php echo ($task['status'] === 'On Hold') ? 'selected' : ''; ?>>On Hold</option>
                                            </select>
                                            <input type="hidden" name="update_task_status" value="1">
                                        </form>
                                        <?php else: ?>
                                            <span class="status-badge task-status-<?php echo str_replace(' ', '-', $task['status']); ?>"><?php echo htmlspecialchars($task['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($task['created_at'])); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($task['updated_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <hr>

            <div class="comments-list">
                <h3>Project Comments (<?php echo count($comments); ?>)</h3>
                <?php if ($can_post_comment): ?>
                <div class="add-comment-form">
                    <form action="project_details.php?id=<?php echo $project_id; ?>" method="POST">
                        <div class="form-group">
                            <label for="comment_text">Add New Comment:</label>
                            <textarea id="comment_text" name="comment_text" rows="4" required placeholder="Type your comment here..."></textarea>
                        </div>
                        <button type="submit" name="add_comment">Post Comment</button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (empty($comments)): ?>
                    <p style="margin-top: 20px;">No comments for this project yet.</p>
                <?php else: ?>
                    <div style="margin-top: 20px;">
                        <?php foreach ($comments as $comment):
                            $is_comment_owner = ($_SESSION['user_id'] === $comment['comment_author_user_id']);
                            $can_delete_any_comment = ($is_admin_or_coordinator);
                        ?>
                            <div class="comment-item" id="comment-item-<?php echo $comment['comment_id']; ?>">
                                <div class="comment-meta">
                                    <span>
                                        <strong><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></strong>
                                        (<span class="role"><?php echo htmlspecialchars($comment['role_name']); ?></span>)
                                    </span>
                                    <span><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></span>
                                </div>
                                <div class="comment-text">
                                    <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                                </div>
                                <form class="edit-comment-form" action="project_details.php?id=<?php echo $project_id; ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                    <input type="hidden" name="comment_user_id" value="<?php echo $comment['comment_author_user_id']; ?>">
                                    <textarea name="updated_comment_text" class="edit-comment-textarea" rows="4"><?php echo htmlspecialchars($comment['comment_text']); ?></textarea>
                                    <button type="submit" name="edit_comment_submit" class="edit-save-button">Save</button>
                                    <button type="button" class="edit-cancel-button">Cancel</button>
                                </form>

                                <?php if ($is_comment_owner || $can_delete_any_comment): ?>
                                <div class="comment-actions">
                                    <?php if ($is_comment_owner): ?>
                                        <button type="button" class="edit-button" data-comment-id="<?php echo $comment['comment_id']; ?>">Edit</button>
                                    <?php endif; ?>
                                    <form action="project_details.php?id=<?php echo $project_id; ?>" method="POST" style="display:inline;">
                                        <input type="hidden" name="comment_id" value="<?php echo $comment['comment_id']; ?>">
                                        <input type="hidden" name="comment_user_id" value="<?php echo $comment['comment_author_user_id']; ?>">
                                        <button type="submit" name="delete_comment" class="delete-button">Delete</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif (!$message): ?>
            <p style="text-align: center;">Please select a project to view its details.</p>
        <?php endif; ?>
    </div>
</body>
</html>