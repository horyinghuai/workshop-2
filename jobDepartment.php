<?php
// Include the database connection file
include 'connection.php';

// Redirect to login if user not logged in
if (!isset($_GET['email'])) {
    header('Location: login.php');
    exit();
}

$current_email = $conn->real_escape_string($_GET['email']);

// Fetch user details
$sql = "SELECT name FROM user WHERE email = '$current_email'";
$result = $conn->query($sql);
if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $user_name = $row['name'];
} else {
    header('Location: login.php');
    exit();
}

if ($conn->connect_error) {
    die("Database connection failed in jobDepartment.php: " . $conn->connect_error);
}

// --- ARCHIVE LOGIC ---
$isArchived = isset($_GET['view']) && $_GET['view'] === 'archive' ? 1 : 0;
$viewParam = $isArchived ? '&view=archive' : '';

// 1. SQL Query to fetch department data based on is_archived
$sql = "SELECT * FROM department WHERE is_archived = $isArchived ORDER BY department_name ASC";
$result = $conn->query($sql);

$department_rows_html = '';

// 2. Check for results and build the table rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        if ($isArchived == 0) {
            // Active View
            $actions = '
                <button class="edit-btn" data-id="' . $row["department_id"] . '"><i class="fas fa-edit"></i> Edit</button>
                <button class="archive-btn" data-id="' . $row["department_id"] . '" style="background-color: #fd7e14;"><i class="fas fa-box-archive"></i> Archive</button>
            ';
        } else {
            // Archive View
            $actions = '
                <button class="restore-btn" data-id="' . $row["department_id"] . '" style="background-color: #17a2b8;"><i class="fas fa-trash-restore"></i> Restore</button>
                <button class="delete-permanent-btn" data-id="' . $row["department_id"] . '" style="background-color: #dc3545;"><i class="fas fa-trash"></i> Delete</button>
            ';
        }

        $department_rows_html .= '
            <div class="table-row">
                <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                <div class="table-cell action data" style="display:flex; gap:5px; justify-content:center;">
                    ' . $actions . '
                </div>
            </div>';
    }
} else {
    $department_rows_html = '
        <div class="table-row no-data">
            <div class="table-cell data" colspan="3" style="text-align: center;">No ' . ($isArchived ? 'archived' : 'active') . ' departments found.</div>
        </div>';
}

$conn->close();
$currentEmail = isset($_GET['email']) ? $_GET['email'] : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Reader - Departments <?php echo $isArchived ? '(Archive)' : ''; ?></title>
    <link rel="stylesheet" href="jobDepartment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        .archive-btn, .restore-btn, .delete-permanent-btn {
            border: none; color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.9em; display: inline-flex; align-items: center; gap: 5px;
        }
        .archive-btn:hover { background-color: #e36d0d !important; }
        .restore-btn:hover { background-color: #138496 !important; }
        .delete-permanent-btn:hover { background-color: #c82333 !important; }

        .toggle-view-btn {
            padding: 8px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin-right: 10px; font-size: 0.9rem;
        }
        .toggle-view-btn:hover { background-color: #5a6268; }
        .toggle-view-btn.active-view { background-color: #3a7c7c; }
    </style>
</head>

<body>
    <header class="header">
        <div class="header-left">
            <a href="dashboard.php?email=<?php echo urlencode($currentEmail); ?>" class="back-link">
                <i class="fas fa-chevron-left"></i> Back
            </a>
        </div>
        <h1 class="logo">Resume Reader</h1>
        <div class="header-right">
            <a href="jobPosition.php?email=<?php echo urlencode($currentEmail); ?>">Job Position</a>
            <a href="#">Department</a>
            <a href="logout.php" class="logout">Log Out</a>
        </div>
    </header>

    <div id="notification-box" class="notification-box" style="display: none;">
        <p id="notification-message"></p>
    </div>

    <main class="content-area">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <div>
                <button class="add-department-btn">Add Department</button>
                <a href="jobDepartment.php?email=<?php echo urlencode($currentEmail); ?>&view=<?php echo $isArchived ? 'active' : 'archive'; ?>" class="toggle-view-btn <?php echo $isArchived ? 'active-view' : ''; ?>">
                    <i class="fas <?php echo $isArchived ? 'fa-list' : 'fa-archive'; ?>"></i> 
                    <?php echo $isArchived ? 'View Active' : 'View Archive'; ?>
                </a>
            </div>
            <div class="search-bar" style="margin-left:auto;">
                <input type="text" id="search-input" placeholder="Search">
                <button class="search-btn" id="search-btn"><i class="fas fa-search"></i></button>
            </div>
        </div>

        <div class="department-table">
            <div class="table-row header-row">
                <div class="table-cell">Department</div>
                <div class="table-cell description">Description</div>
                <div class="table-cell action">Action</div>
            </div>

            <div id="department-list">
                <?php echo $department_rows_html; ?>
            </div>
        </div>
    </main>

    <div id="departmentModal" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modalTitle"></h2>
            <form id="departmentForm" action="cud_department.php" method="POST">
                <input type="hidden" id="departmentId" name="department_id" value="">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">
                <input type="hidden" name="view_type" value="<?php echo $isArchived ? 'archive' : 'active'; ?>">
                <input type="hidden" id="actionType" name="action_type" value="">

                <div class="form-group">
                    <label for="departmentNameInput">Department Name:</label>
                    <input type="text" id="departmentNameInput" name="department_name" placeholder="Department Name" required>
                </div>

                <div class="form-group">
                    <label for="departmentDescriptionInput">Description:</label>
                    <textarea id="departmentDescriptionInput" name="description" placeholder="Department Description" rows="4" ></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-confirm" id="confirmBtn"></button>
                    <button type="button" class="btn btn-cancel" id="cancelBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px; padding: 25px;">
            <h2 style="color: #dc3545; margin-bottom: 15px;" id="actionModalTitle">Confirm Action</h2>
            <p style="text-align: center; margin-bottom: 25px;" id="actionModalText">
                Are you sure?
            </p>

            <form id="deleteForm" action="cud_department.php" method="POST">
                <input type="hidden" id="deleteActionType" name="action_type" value="">
                <input type="hidden" id="deleteDepartmentId" name="department_id" value="">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">
                <input type="hidden" name="view_type" value="<?php echo $isArchived ? 'archive' : 'active'; ?>">

                <div class="form-actions">
                    <button type="submit" class="btn btn-confirm" style="background-color: #3a7c7c;" id="actionConfirmBtn">Yes</button>
                    <button type="button" class="btn btn-cancel" id="cancelDeleteBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const isArchived = <?php echo $isArchived; ?>;

        $(document).ready(function() {
            $('#search-btn').click(performSearch);
            $('#search-input').keypress(function(e) { if (e.which == 13) performSearch(); });
            $('#search-input').on('keyup', function() { if ($(this).val().trim() === '') performSearch(); });
        });

        function performSearch() {
            var searchTerm = $('#search-input').val(); 
            $.ajax({
                url: 'search_department.php', 
                type: 'POST',
                data: {
                    search_term: searchTerm,
                    is_archived: isArchived
                }, 
                success: function(response) {
                    $('#department-list').html(response);
                },
                error: function() {
                    alert('An error occurred during the search.');
                }
            });
        }

        function closeModal() {
            $('#departmentModal').removeClass('modal-show');
            setTimeout(function() { $('#departmentModal').hide(); $('#departmentForm')[0].reset(); $('#departmentId').val(''); }, 300); 
        }
        function openModal() { $('#departmentModal').css('display', 'flex'); setTimeout(() => $('#departmentModal').addClass('modal-show'), 10); }

        $('.add-department-btn').click(function() {
            $('#modalTitle').text('Add Department');
            $('#actionType').val('add');
            $('#confirmBtn').text('Confirm');
            openModal();
        });

        $('#department-list').on('click', '.edit-btn', function() {
            var $row = $(this).closest('.table-row');
            var currentId = $(this).data('id');
            var currentName = $row.find('.table-cell.data').eq(0).text().trim();
            var currentDesc = $row.find('.table-cell.description.data').text().trim();

            $('#departmentId').val(currentId);
            $('#departmentNameInput').val(currentName);
            $('#departmentDescriptionInput').val(currentDesc);
            $('#modalTitle').text('Edit Department: ' + currentName);
            $('#actionType').val('edit');
            $('#confirmBtn').text('Save Changes');
            openModal();
        });

        $('#cancelBtn').click(closeModal);
        $('#departmentModal').click(function(e) { if (e.target.id === 'departmentModal') closeModal(); });

        // --- ACTION MODAL ---
        function openActionModal(id, name, type) {
            $('#deleteDepartmentId').val(id);
            const titleElem = $('#actionModalTitle');
            const textElem = $('#actionModalText');
            const confirmBtn = $('#actionConfirmBtn');
            const inputType = $('#deleteActionType');

            if (type === 'archive') {
                titleElem.text('Confirm Archive');
                textElem.html(`Are you sure you want to archive <strong>${name}</strong>?`);
                confirmBtn.text('Archive');
                confirmBtn.css('background-color', '#fd7e14');
                inputType.val('delete');
            } else if (type === 'restore') {
                titleElem.text('Confirm Restore');
                textElem.html(`Restore <strong>${name}</strong> to active list?`);
                confirmBtn.text('Restore');
                confirmBtn.css('background-color', '#17a2b8');
                inputType.val('restore');
            } else if (type === 'permanent_delete') {
                titleElem.text('Permanent Delete');
                textElem.html(`PERMANENTLY delete <strong>${name}</strong>? This cannot be undone.`);
                confirmBtn.text('Delete Permanently');
                confirmBtn.css('background-color', '#dc3545');
                inputType.val('permanent_delete');
            }

            $('#deleteModal').css('display', 'flex');
            setTimeout(() => $('#deleteModal').addClass('modal-show'), 10);
        }

        $('#department-list').on('click', '.archive-btn', function() {
            var $row = $(this).closest('.table-row');
            var currentId = $(this).data('id');
            var currentName = $row.find('.table-cell.data').eq(0).text().trim();
            openActionModal(currentId, currentName, 'archive');
        });

        $('#department-list').on('click', '.restore-btn', function() {
            var $row = $(this).closest('.table-row');
            var currentId = $(this).data('id');
            var currentName = $row.find('.table-cell.data').eq(0).text().trim();
            openActionModal(currentId, currentName, 'restore');
        });

        $('#department-list').on('click', '.delete-permanent-btn', function() {
            var $row = $(this).closest('.table-row');
            var currentId = $(this).data('id');
            var currentName = $row.find('.table-cell.data').eq(0).text().trim();
            openActionModal(currentId, currentName, 'permanent_delete');
        });

        function closeDeleteModal() {
            $('#deleteModal').removeClass('modal-show');
            setTimeout(function() { $('#deleteModal').hide(); $('#deleteDepartmentId').val(''); }, 300);
        }
        $('#cancelDeleteBtn').click(closeDeleteModal);
        $('#deleteModal').click(function(e) { if (e.target.id === 'deleteModal') closeDeleteModal(); });

        function displayNotification() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            if (status && message) {
                const decodedMessage = decodeURIComponent(message);
                const $box = $('#notification-box');
                const $msg = $('#notification-message');
                $box.removeClass('success error'); 
                $msg.text(decodedMessage);
                if (status === 'success') $box.addClass('success'); else if (status === 'error') $box.addClass('error');
                $box.slideDown(300);
                history.replaceState(null, null, window.location.pathname);
                setTimeout(() => $box.slideUp(500), 5000);
            }
        }
        displayNotification();
    </script>
</body>
</html>