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

// 1. SQL Query to fetch job data based on Archive Status
$sql = "SELECT jp.*, d.department_name 
        FROM job_position jp 
        INNER JOIN department d ON jp.department_id = d.department_id 
        WHERE jp.is_archived = $isArchived
        ORDER BY d.department_name ASC;";

$result = $conn->query($sql);

$job_rows_html = '';

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Prepare data attributes for the Edit Modal
        $dataAttrs = 'data-id="' . $row["job_id"] . '" ' .
                     'data-dept-id="' . $row["department_id"] . '" ' .
                     'data-job-name="' . htmlspecialchars($row["job_name"]) . '" ' .
                     'data-desc="' . htmlspecialchars($row["description"]) . '" ' .
                     'data-edu="' . htmlspecialchars($row["education"]) . '" ' .
                     'data-skills="' . htmlspecialchars($row["skills"]) . '" ' .
                     'data-exp="' . htmlspecialchars($row["experience"]) . '" ' .
                     'data-lang="' . htmlspecialchars($row["language"]) . '" ' .
                     'data-others="' . htmlspecialchars($row["others"]) . '"';

        $job_rows_html .= '
            <div class="table-row">
                <div class="table-cell center-align"><input type="checkbox" name="job_check" value="' . $row["job_id"] . '"></div>
                <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                <div class="table-cell data clickable-job" ' . $dataAttrs . ' onclick="openEditFromRow(this)">
                    ' . htmlspecialchars($row["job_name"]) . ' </i>
                </div>
                <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                <div class="table-cell education data">' . htmlspecialchars($row["education"]) . '</div>
                <div class="table-cell skills data">' . htmlspecialchars($row["skills"]) . '</div>
                <div class="table-cell experience data">' . htmlspecialchars($row["experience"]) . '</div>
                <div class="table-cell language data">' . htmlspecialchars($row["language"]) . '</div>
                <div class="table-cell others data">' . htmlspecialchars($row["others"]) . '</div>
            </div>';
    }
} else {
    // UPDATED: Used grid-column: 1 / -1 to span all columns in CSS Grid
    $job_rows_html = '
        <div class="table-row no-data">
            <div class="table-cell data" style="grid-column: 1 / -1; text-align: center; justify-content:center;">No ' . ($isArchived ? 'archived' : 'active') . ' jobs found.</div>
        </div>';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Reader | Job Positions <?php echo $isArchived ? '(Archive)' : ''; ?></title>
    <link rel="stylesheet" href="jobPosition.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        /* Local overrides */
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
        
        .clickable-job { cursor: pointer; color: #3a7c7c; font-weight: 600; text-decoration: underline; }
        .clickable-job:hover { color: #2a5c5c; }
        
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
            <a href="#">Job Position</a>
            <a href="jobDepartment.php?email=<?php echo urlencode($currentEmail); ?>">Department</a>
            <a href="logout.php" class="logout">Log Out</a>
        </div>
    </header>

    <div id="notification-box" class="notification-box" style="display: none;">
        <p id="notification-message"></p>
    </div>

    <main class="content-area">
        <div class="top-controls">
            <button class="add-job-btn">Add Job</button>
            
            <button id="archiveSelectedBtn" class="bulk-btn"><i class="fas fa-box-archive"></i> Archive Selected</button>
            <button id="restoreSelectedBtn" class="bulk-btn"><i class="fas fa-trash-restore"></i> Restore Selected</button>
            <button id="deleteSelectedBtn" class="bulk-btn"><i class="fas fa-trash"></i> Delete Selected</button>

            <div style="flex-grow: 1;"></div> 
            <a href="jobPosition.php?email=<?php echo urlencode($currentEmail); ?>&view=<?php echo $isArchived ? 'active' : 'archive'; ?>" class="toggle-view-btn <?php echo $isArchived ? 'active-view' : ''; ?>">
                <i class="fas <?php echo $isArchived ? 'fa-list' : 'fa-archive'; ?>"></i> 
                <?php echo $isArchived ? 'View Active' : 'View Archive'; ?>
            </a>
        </div>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search">
            <button class="search-btn" id="search-btn"><i class="fas fa-search"></i></button>
        </div>

        <div class="table-scroll-wrapper">
            <div class="job-table">
                <div class="table-row header-row">
                    <div class="table-cell center-align"><input type="checkbox" id="selectAll"></div>
                    <div class="table-cell">Department</div>
                    <div class="table-cell job">Job Position</div>
                    <div class="table-cell description">Description</div>
                    <div class="table-cell education">Education</div>
                    <div class="table-cell skills">Skills</div>
                    <div class="table-cell experience">Experience</div>
                    <div class="table-cell language">Language</div>
                    <div class="table-cell others">Others</div>
                </div>

                <div id="job-list">
                    <?php echo $job_rows_html; ?>
                </div>
            </div>
        </div>
    </main>

    <div id="jobModal" class="modal-overlay">
        <div class="modal-content">
            <h2 id="modalTitle"></h2>
            <form id="jobForm" action="cud_job.php" method="POST">
                <input type="hidden" id="jobId" name="job_id" value="">
                <input type="hidden" id="emailInput" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">
                <input type="hidden" name="view_type" value="<?php echo $isArchived ? 'archive' : 'active'; ?>">
                <input type="hidden" id="actionType" name="action_type" value="">

                <div class="form-group">
                    <label for="departmentSelect">Department Name:</label>
                    <select id="departmentSelect" name="department_id" required>
                        <option value="">-- Select Department --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="jobNameInput">Job Name:</label>
                    <input type="text" id="jobNameInput" name="job_name" placeholder="Job Name" required>
                </div>

                <div class="form-group">
                    <label for="jobDescriptionInput">Description:</label>
                    <textarea id="jobDescriptionInput" name="description" placeholder="Job Description" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <label for="educationInput">Education:</label>
                    <input type="text" id="educationInput" name="education" placeholder="Education" required>
                </div>
                <div class="form-group">
                    <label for="skillsInput">Skills:</label>
                    <input type="text" id="skillsInput" name="skills" placeholder="Skills" required>
                </div>
                <div class="form-group">
                    <label for="experienceInput">Experience:</label>
                    <input type="text" id="experienceInput" name="experience" placeholder="Experience" required>
                </div>
                <div class="form-group">
                    <label for="languageInput">Language:</label>
                    <input type="text" id="languageInput" name="language" placeholder="Language" required>
                </div>
                <div class="form-group">
                    <label for="othersInput">Others:</label>
                    <input type="text" id="othersInput" name="others" placeholder="Others">
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

            <form id="bulkForm" action="cud_job.php" method="POST">
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
            populateDepartmentDropdown();
            attachCheckboxListeners();
            $('#search-btn').click(performSearch);
            $('#search-input').keypress(function(e) { if (e.which == 13) performSearch(); });
            $('#search-input').on('keyup', function() { if ($(this).val().trim() === '') performSearch(); });
        });

        function performSearch() {
            var searchTerm = $('#search-input').val(); 
            $.ajax({
                url: 'search_job.php',
                type: 'POST',
                data: {
                    search_term: searchTerm,
                    is_archived: isArchived
                }, 
                success: function(response) {
                    $('#job-list').html(response);
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
                $('input[name="job_check"]').prop('checked', this.checked);
                updateButtonVisibility();
            });

            // Individual
            $('input[name="job_check"]').off('change').on('change', function() {
                updateButtonVisibility();
                // Update "Select All" status
                const all = $('input[name="job_check"]').length;
                const checked = $('input[name="job_check"]:checked').length;
                $('#selectAll').prop('checked', all > 0 && all === checked);
            });
        }

        function updateButtonVisibility() {
            const checkedCount = $('input[name="job_check"]:checked').length;
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

        // --- ADD/EDIT MODAL ---
        function closeModal() {
            $('#jobModal').removeClass('modal-show');
            setTimeout(function() { $('#jobModal').hide(); $('#jobForm')[0].reset(); $('#jobId').val(''); }, 300); 
        }
        function openModal() { $('#jobModal').css('display', 'flex'); setTimeout(() => $('#jobModal').addClass('modal-show'), 10); }

        $('.add-job-btn').click(function() {
            $('#modalTitle').text('Add Job');
            $('#actionType').val('add');
            $('#confirmBtn').text('Confirm');
            openModal();
        });

        // Open Edit Modal from Row Click
        window.openEditFromRow = function(element) {
            const id = element.dataset.id;
            const deptId = element.dataset.deptId;
            const name = element.dataset.jobName;
            const desc = element.dataset.desc;
            const edu = element.dataset.edu;
            const skills = element.dataset.skills;
            const exp = element.dataset.exp;
            const lang = element.dataset.lang;
            const others = element.dataset.others;

            $('#jobId').val(id);
            $('#departmentSelect').val(deptId);
            $('#jobNameInput').val(name);
            $('#jobDescriptionInput').val(desc);
            $('#educationInput').val(edu);
            $('#skillsInput').val(skills);
            $('#experienceInput').val(exp);
            $('#languageInput').val(lang);
            $('#othersInput').val(others);

            $('#modalTitle').text('Edit Job: ' + name);
            $('#actionType').val('edit');
            $('#confirmBtn').text('Save Changes');
            openModal();
        };

        $('#cancelBtn').click(closeModal);
        $('#jobModal').click(function(e) { if (e.target.id === 'jobModal') closeModal(); });

        // --- BULK ACTIONS ---
        function openBulkModal(actionType) {
            const selected = [];
            $('input[name="job_check"]:checked').each(function() {
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
                text.text(`Archive ${selected.length} selected job(s)?`);
                btn.css('background-color', '#fd7e14');
            } else if (actionType === 'restore') {
                title.text('Confirm Restore');
                text.text(`Restore ${selected.length} selected job(s)?`);
                btn.css('background-color', '#17a2b8');
            } else if (actionType === 'permanent_delete') {
                title.text('Permanent Delete');
                text.text(`PERMANENTLY DELETE ${selected.length} selected job(s)? This cannot be undone.`);
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

        async function populateDepartmentDropdown() {
            try {
                const response = await $.ajax({ url: 'get_department.php', type: 'GET', dataType: 'json' });
                const $select = $('#departmentSelect');
                $select.empty();
                $select.append('<option value="">-- Select Department --</option>');
                response.forEach(dept => { $select.append(`<option value="${dept.id}">${dept.name}</option>`); });
            } catch (error) { console.error("Error fetching departments:", error); }
        }

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