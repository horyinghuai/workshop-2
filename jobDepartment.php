<?php
// Include the database connection file
include 'connection.php';

// Check if the connection was successful
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Redirect to login if user not logged in
if (!isset($_GET['email'])) {
    header('Location: login.php');
    exit();
}

$currentEmail = isset($_GET['email']) ? $_GET['email'] : '';

// --- ARCHIVE LOGIC ---
$isArchived = isset($_GET['view']) && $_GET['view'] === 'archive' ? 1 : 0;

// 1. SQL Query to fetch department data based on Archive Status
$sql = "SELECT * FROM department WHERE is_archived = $isArchived ORDER BY department_name ASC";
$result = $conn->query($sql);

// Initialize a variable to hold the HTML for the table rows
$department_rows_html = '';

// 2. Check for results and build the table rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Prepare data attributes for the Edit Modal (attached to Dept Name)
        $dataAttrs = 'data-id="' . $row["department_id"] . '" ' .
                     'data-name="' . htmlspecialchars($row["department_name"]) . '" ' .
                     'data-desc="' . htmlspecialchars($row["description"]) . '"';

        $department_rows_html .= '
            <div class="table-row">
                <div class="table-cell center-align"><input type="checkbox" name="dept_check" value="' . $row["department_id"] . '"></div>
                <div class="table-cell data clickable-dept" ' . $dataAttrs . ' onclick="openEditFromRow(this)">
                    ' . htmlspecialchars($row["department_name"]) . '
                </div>
                <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
            </div>';
    }
} else {
    $department_rows_html = '
        <div class="table-row no-data">
            <div class="table-cell data" style="grid-column: 1 / -1; text-align: center; justify-content:center;">No ' . ($isArchived ? 'archived' : 'active') . ' departments found.</div>
        </div>';
}

$conn->close();
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
        /* Local overrides for layout controls */
        .top-controls { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        
        .bulk-btn {
            border: none; color: white; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 1rem; display: none; align-items: center; gap: 5px;
        }
        #archiveSelectedBtn { background-color: #fd7e14; }
        #archiveSelectedBtn:hover { background-color: #e36d0d; }
        #restoreSelectedBtn { background-color: #17a2b8; }
        #restoreSelectedBtn:hover { background-color: #138496; }
        #deleteSelectedBtn { background-color: #dc3545; }
        #deleteSelectedBtn:hover { background-color: #c82333; }
        
        .clickable-dept { cursor: pointer; color: #3a7c7c; font-weight: 600; text-decoration: underline; }
        .clickable-dept:hover { color: #2a5c5c; }
        
        .toggle-view-btn { padding: 10px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
        .toggle-view-btn:hover { background-color: #5a6268; }
        .toggle-view-btn.active-view { background-color: #3a7c7c; }

        .center-align { justify-content: center; }
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
        <div class="top-controls">
            <button class="add-department-btn">Add Department</button>

            <button id="archiveSelectedBtn" class="bulk-btn"><i class="fas fa-box-archive"></i> Archive Selected</button>
            <button id="restoreSelectedBtn" class="bulk-btn"><i class="fas fa-trash-restore"></i> Restore Selected</button>
            <button id="deleteSelectedBtn" class="bulk-btn"><i class="fas fa-trash"></i> Delete Selected</button>

            <div style="flex-grow: 1;"></div> 
            
            <a href="jobDepartment.php?email=<?php echo urlencode($currentEmail); ?>&view=<?php echo $isArchived ? 'active' : 'archive'; ?>" class="toggle-view-btn <?php echo $isArchived ? 'active-view' : ''; ?>">
                <i class="fas <?php echo $isArchived ? 'fa-list' : 'fa-archive'; ?>"></i> 
                <?php echo $isArchived ? 'View Active' : 'View Archive'; ?>
            </a>
        </div>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search">
            <button class="search-btn" id="search-btn"><i class="fas fa-search"></i></button>
        </div>

        <div class="department-table">
            <div class="table-row header-row">
                <div class="table-cell center-align"><input type="checkbox" id="selectAll"></div>
                <div class="table-cell">Department</div>
                <div class="table-cell description">Description</div>
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
                <input type="hidden" id="emailInput" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">
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

    <div id="bulkActionModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px; padding: 25px;">
            <h2 style="color: #dc3545; margin-bottom: 15px;" id="bulkModalTitle">Confirm Action</h2>
            <p style="text-align: center; margin-bottom: 25px;" id="bulkModalText">Are you sure?</p>

            <form id="bulkForm" action="cud_department.php" method="POST">
                <input type="hidden" id="bulkActionType" name="action_type" value="">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">
                <input type="hidden" name="view_type" value="<?php echo $isArchived ? 'archive' : 'active'; ?>">
                <div id="bulkIdsContainer"></div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-confirm" style="background-color: #3a7c7c;" id="bulkConfirmBtn">Yes</button>
                    <button type="button" class="btn btn-cancel" id="cancelBulkBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const isArchived = <?php echo $isArchived; ?>;

        $(document).ready(function() {
            attachCheckboxListeners();
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
                    attachCheckboxListeners();
                    updateButtonVisibility();
                },
                error: function() { alert('An error occurred during the search.'); }
            });
        }

        // --- CHECKBOX LOGIC ---
        function attachCheckboxListeners() {
            // Select All
            $('#selectAll').off('change').on('change', function() {
                $('input[name="dept_check"]').prop('checked', this.checked);
                updateButtonVisibility();
            });

            // Individual
            $('input[name="dept_check"]').off('change').on('change', function() {
                updateButtonVisibility();
                const all = $('input[name="dept_check"]').length;
                const checked = $('input[name="dept_check"]:checked').length;
                $('#selectAll').prop('checked', all > 0 && all === checked);
            });
        }

        function updateButtonVisibility() {
            const checkedCount = $('input[name="dept_check"]:checked').length;
            const hasSelection = checkedCount > 0;

            if (isArchived) {
                // Archive View: Show Restore & Delete
                $('#restoreSelectedBtn').css('display', hasSelection ? 'inline-flex' : 'none');
                $('#deleteSelectedBtn').css('display', hasSelection ? 'inline-flex' : 'none');
                $('#archiveSelectedBtn').hide();
            } else {
                // Active View: Show Archive & Delete
                $('#archiveSelectedBtn').css('display', hasSelection ? 'inline-flex' : 'none');
                $('#deleteSelectedBtn').css('display', hasSelection ? 'inline-flex' : 'none');
                $('#restoreSelectedBtn').hide();
            }
        }

        // --- MODAL & FORM LOGIC ---
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

        // Open Edit from click on Department Name
        window.openEditFromRow = function(element) {
            const id = element.dataset.id;
            const name = element.dataset.name;
            const desc = element.dataset.desc;

            $('#departmentId').val(id);
            $('#departmentNameInput').val(name);
            $('#departmentDescriptionInput').val(desc);
            $('#modalTitle').text('Edit Department: ' + name);
            $('#actionType').val('edit');
            $('#confirmBtn').text('Save Changes');
            openModal();
        };

        $('#cancelBtn').click(closeModal);
        $('#departmentModal').click(function(e) { if (e.target.id === 'departmentModal') closeModal(); });

        // --- BULK ACTIONS ---
        function openBulkModal(actionType) {
            const selected = [];
            $('input[name="dept_check"]:checked').each(function() {
                selected.push($(this).val());
            });
            if (selected.length === 0) return;

            $('#bulkActionType').val(actionType);
            $('#bulkIdsContainer').html('');
            selected.forEach(id => {
                $('#bulkIdsContainer').append(`<input type="hidden" name="ids[]" value="${id}">`);
            });

            const title = $('#bulkModalTitle');
            const text = $('#bulkModalText');
            const btn = $('#bulkConfirmBtn');

            if (actionType === 'delete') { // 'delete' = Archive (Soft Delete)
                title.text('Confirm Archive');
                text.text(`Archive ${selected.length} selected department(s)?`);
                btn.css('background-color', '#fd7e14');
            } else if (actionType === 'restore') {
                title.text('Confirm Restore');
                text.text(`Restore ${selected.length} selected department(s)?`);
                btn.css('background-color', '#17a2b8');
            } else if (actionType === 'permanent_delete') {
                title.text('Permanent Delete');
                text.text(`PERMANENTLY DELETE ${selected.length} selected department(s)? This cannot be undone.`);
                btn.css('background-color', '#dc3545');
            }

            $('#bulkActionModal').css('display', 'flex');
            setTimeout(() => $('#bulkActionModal').addClass('modal-show'), 10);
        }

        $('#archiveSelectedBtn').click(() => openBulkModal('delete'));
        $('#restoreSelectedBtn').click(() => openBulkModal('restore'));
        $('#deleteSelectedBtn').click(() => openBulkModal('permanent_delete'));

        $('#cancelBulkBtn').click(() => {
            $('#bulkActionModal').removeClass('modal-show');
            setTimeout(() => $('#bulkActionModal').hide(), 300);
        });

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
                urlParams.delete('status'); urlParams.delete('message');
                const newQuery = urlParams.toString();
                history.replaceState(null, null, window.location.pathname + (newQuery ? '?' + newQuery : ''));
                setTimeout(() => $box.slideUp(500), 5000);
            }
        }
        displayNotification();
    </script>
</body>
</html>