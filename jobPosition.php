<?php
// Include the database connection file
include 'connection.php';

// Check if the connection was successful (though connection.php handles the die() case)
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Redirect to login if user not logged in
if (!isset($_GET['email'])) {
    header('Location: login.php');
    exit();
}

// 1. SQL Query to fetch job data
$sql = "SELECT jp.*, d.department_name FROM job_position jp INNER JOIN department d ON jp.department_id = d.department_id ORDER BY d.department_name ASC;";
$result = $conn->query($sql);

// Initialize a variable to hold the HTML for the table rows
$job_rows_html = '';

// 2. Check for results and build the table rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $job_rows_html .= '
            <div class="table-row">
                <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                <div class="table-cell data">' . htmlspecialchars($row["job_name"]) . '</div>
                <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                <div class="table-cell education data">' . htmlspecialchars($row["education"]) . '</div>
                <div class="table-cell skills data">' . htmlspecialchars($row["skills"]) . '</div>
                <div class="table-cell experience data">' . htmlspecialchars($row["experience"]) . '</div>
                <div class="table-cell language data">' . htmlspecialchars($row["language"]) . '</div>
                <div class="table-cell others data">' . htmlspecialchars($row["others"]) . '</div>
                <div class="table-cell action data">
                    <button class="edit-btn" data-id="' . $row["job_id"] . '"data-dept-id="' . $row["department_id"] . '"><i class="fas fa-edit"></i> Edit</button>
                    <button class="delete-btn" data-id="' . $row["job_id"] . '"><i class="fas fa-trash-alt"></i> Delete</button>
                </div>
            </div>';
    }
} else {
    $job_rows_html = '
        <div class="table-row no-data">
            <div class="table-cell data" colspan="9" style="text-align: center;">No jobs found.</div>
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
    <title>Resume Reader | Job Positions</title>
    <link rel="stylesheet" href="jobPosition.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
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
        <button class="add-job-btn">Add Job</button>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search">
            <button class="search-btn" id="search-btn"><i class="fas fa-search"></i></button>
        </div>
        <div class="table-scroll-wrapper">
            <div class="job-table">
                <div class="table-row header-row">
                    <div class="table-cell">Department</div>
                    <div class="table-cell job">Job Positon</div>
                    <div class="table-cell description">Description</div>
                    <div class="table-cell education">Education</div>
                    <div class="table-cell skills">Skills</div>
                    <div class="table-cell experience">Experience</div>
                    <div class="table-cell language">Language</div>
                    <div class="table-cell others">Others</div>
                    <div class="table-cell action">Action</div>
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
                <?php $currentEmail = isset($_GET['email']) ? $_GET['email'] : ''; ?>

                <input type="hidden" id="emailInput" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">
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

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px; padding: 25px;">
            <h2 style="color: #dc3545; margin-bottom: 15px;">Confirm Deletion</h2>
            <p style="text-align: center; margin-bottom: 25px;">
                Are you sure you want to delete the position:
                <strong id="jobToDeleteName"></strong>?
                This action cannot be undone.
            </p>

            <form id="deleteForm" action="cud_job.php" method="POST">
                <input type="hidden" name="action_type" value="delete">
                <input type="hidden" id="deleteJobId" name="job_id" value="">
                <?php $currentEmail = isset($_GET['email']) ? $_GET['email'] : ''; ?>
                <input type="hidden" id="emailInput" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">

                <div class="form-actions">
                    <button type="submit" class="btn btn-confirm" style="background-color: #3a7c7c;">Yes, Delete</button>
                    <button type="button" class="btn btn-cancel" id="cancelDeleteBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            populateDepartmentDropdown();

            // Handle Search Button Click
            $('#search-btn').click(function() {
                performSearch();
            });

            // Handle Enter Key in Search Input
            $('#search-input').keypress(function(e) {
                if (e.which == 13) { 
                    performSearch();
                }
            });

            // Handle Reset (Empty Input)
            $('#search-input').on('keyup', function() {
                if ($(this).val().trim() === '') {
                    performSearch();
                }
            });
        });

        // --- STANDARD SEARCH FUNCTION ---
        function performSearch() {
            var searchTerm = $('#search-input').val(); 

            $.ajax({
                url: 'search_job.php', // Points to normal SQL search
                type: 'POST',
                data: {
                    search_term: searchTerm
                }, 
                success: function(response) {
                    $('#job-list').html(response);
                },
                error: function() {
                    alert('An error occurred during the search.');
                }
            });
        }

        // --- MODAL FUNCTIONALITY ---
        function closeModal() {
            $('#jobModal').removeClass('modal-show');
            setTimeout(function() {
                $('#jobModal').hide();
                $('#jobForm')[0].reset();
                $('#jobId').val('');
            }, 300); 
        }

        function openModal() {
            $('#jobModal').css('display', 'flex');
            setTimeout(function() {
                $('#jobModal').addClass('modal-show');
            }, 10);
        }

        // Add Job
        $('.add-job-btn').click(function() {
            $('#modalTitle').text('Add Job');
            $('#actionType').val('add');
            $('#confirmBtn').text('Confirm');
            openModal();
        });

        // Edit Job
        $('#job-list').on('click', '.edit-btn', function() {
            var $row = $(this).closest('.table-row');
            var currentId = $(this).data('id');
            var currentDeptId = $(this).data('dept-id');
            var currentName = $row.find('.table-cell.data').eq(1).text().trim();
            var currentDesc = $row.find('.table-cell.description.data').text().trim();
            var currentEd = $row.find('.table-cell.education.data').text().trim();
            var currentSkills = $row.find('.table-cell.skills.data').text().trim();
            var currentExp = $row.find('.table-cell.experience.data').text().trim();
            var currentLan = $row.find('.table-cell.language.data').text().trim();
            var currentOthers = $row.find('.table-cell.others.data').text().trim();

            $('#jobId').val(currentId);
            $('#departmentSelect').val(currentDeptId);
            $('#jobNameInput').val(currentName);
            $('#jobDescriptionInput').val(currentDesc);
            $('#educationInput').val(currentEd);
            $('#skillsInput').val(currentSkills);
            $('#experienceInput').val(currentExp);
            $('#languageInput').val(currentLan);
            $('#othersInput').val(currentOthers);

            $('#modalTitle').text('Edit Job: ' + currentName);
            $('#actionType').val('edit');
            $('#confirmBtn').text('Save Changes');

            openModal();
        });

        // Close Modal
        $('#cancelBtn').click(closeModal);
        $('#jobModal').click(function(e) {
            if (e.target.id === 'jobModal') {
                closeModal();
            }
        });

        // Delete Modal
        $('#job-list').on('click', '.delete-btn', function() {
            var $row = $(this).closest('.table-row');
            var currentId = $(this).data('id');
            var currentName = $row.find('.table-cell.data').eq(1).text().trim();

            $('#deleteJobId').val(currentId);
            $('#jobToDeleteName').text(currentName);

            $('#deleteModal').css('display', 'flex');
            setTimeout(function() {
                $('#deleteModal').addClass('modal-show');
            }, 10);
        });

        function closeDeleteModal() {
            $('#deleteModal').removeClass('modal-show');
            setTimeout(function() {
                $('#deleteModal').hide();
                $('#deleteJobId').val('');
            }, 300);
        }

        $('#cancelDeleteBtn').click(closeDeleteModal);
        $('#deleteModal').click(function(e) {
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });

        // Populate Dropdown
        async function populateDepartmentDropdown() {
            try {
                const response = await $.ajax({
                    url: 'get_department.php',
                    type: 'GET',
                    dataType: 'json'
                });
                const $select = $('#departmentSelect');
                $select.empty();
                $select.append('<option value="">-- Select Department --</option>');
                response.forEach(dept => {
                    $select.append(`<option value="${dept.id}">${dept.name}</option>`);
                });
            } catch (error) {
                console.error("Error fetching departments:", error);
            }
        }

        // Notifications
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

                if (status === 'success') {
                    $box.addClass('success');
                } else if (status === 'error') {
                    $box.addClass('error');
                }

                $box.slideDown(300);
                urlParams.delete('status');
                urlParams.delete('message');
                const newQueryString = urlParams.toString();
                const newUrl = window.location.pathname + (newQueryString ? '?' + newQueryString : '');
                history.replaceState(null, null, newUrl);

                setTimeout(function() {
                    $box.slideUp(500);
                }, 5000);
            }
        }
        displayNotification();
    </script>
</body>
</html>