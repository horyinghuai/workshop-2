<?php
// Include the database connection file
include 'connection.php';

// Check if the connection was successful (though connection.php handles the die() case)
if ($conn->connect_error) {
    // This line is mostly redundant if connection.php works, but good for safety
    die("Database connection failed in jobDepartment.php: " . $conn->connect_error);
}

// 1. SQL Query to fetch job data
$sql = "SELECT jp.*, d.department_name FROM job_position jp INNER JOIN department d ON jp.department_id = d.department_id ORDER BY d.department_name ASC;";
$result = $conn->query($sql);

// Initialize a variable to hold the HTML for the table rows
$job_rows_html = '';

// 2. Check for results and build the table rows
if ($result->num_rows > 0) {
    // Loop through each row fetched from the database
    while ($row = $result->fetch_assoc()) {
        // Use PHP to dynamically generate the HTML for each job row
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
    // Message if no job are found
    $job_rows_html = '
        <div class="table-row no-data">
            <div class="table-cell data" colspan="3">No departments found.</div>
        </div>';
}

// 3. Close the database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Reader - Departments</title>
    <link rel="stylesheet" href="jobPosition.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>

<body>
    <header class="header">
        <div class="header-left">
             <a href="dashboard.php" class="back-link">
            <i class="fas fa-chevron-left"></i> Back
        </a>
        </div>
        <h1 class="logo">Resume Reader</h1>
        <div class="header-right">
            <a href="#">Job Position</a>
            <a href="jobDepartment.php">Department</a>
            <a href="#" class="logout">Log Out</a>
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
                    <textarea id="jobDescriptionInput" name="description" placeholder="Job Description" rows="4" required></textarea>
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
                    <input type="text" id="othersInput" name="others" placeholder="Others" required>
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

                <div class="form-actions">
                    <button type="submit" class="btn btn-confirm" style="background-color: #3a7c7c;">Yes, Delete</button>
                    <button type="button" class="btn btn-cancel" id="cancelDeleteBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            // Function to handle the search logic
            function performSearch() {
                var searchTerm = $('#search-input').val(); // Get the value from the search input

                $.ajax({
                    url: 'search_job.php', // The new file we created to handle the request
                    type: 'POST',
                    data: {
                        search_term: searchTerm
                    }, // Send the search term to the PHP file
                    // Before sending (optional: for user feedback)
                    beforeSend: function() {
                        $('#job-list').html('<div class="table-row"><div class="table-cell data" style="width: 100%; text-align: center;">Searching...</div></div>');
                    },
                    // On success, update the content
                    success: function(response) {
                        // Replace the content of the department list with the new rows from the PHP file
                        $('#job-list').html(response);
                    },
                    // On error
                    error: function() {
                        alert('An error occurred during the search.');
                    }
                });


            }

            // Attach the search function to the button click event
            $('#search-btn').click(function() {
                performSearch();
            });

            // Optional: Also perform search when the user presses Enter in the input field
            $('#search-input').keypress(function(e) {
                if (e.which == 13) { // 13 is the Enter key code
                    performSearch();
                }
            });

            // Optional: Initial load function to show all results (this is already handled by PHP)
            // You could use this to reset the search if the input is cleared
            $('#search-input').on('keyup', function() {
                if ($(this).val().trim() === '') {
                    performSearch();
                }
            });

            populateDepartmentDropdown();
        });

        // Add jQuery functionality for the modal inside $(document).ready()
        // --- MODAL FUNCTIONALITY (ANIMATED) ---

        // Function to close the modal
        function closeModal() {
            // 1. Start the hide animation (revert to scale(0.9) and opacity 0)
            $('#jobModal').removeClass('modal-show');

            // 2. After the animation finishes (300ms), hide the modal fully using display: none
            setTimeout(function() {
                $('#jobModal').hide();
                // Clear form fields
                $('#jobForm')[0].reset();
                $('#jobId').val('');
            }, 300); // 300ms matches the CSS transition duration
        }

        // Function to open the modal
        function openModal() {
            // 1. Make the overlay visible immediately (sets display: flex)
            $('#jobModal').css('display', 'flex');

            // 2. After the browser renders 'display: flex', add the class to trigger the smooth transition
            // A small delay (or wrap in setTimeout(..., 10) or nextTick logic) is often needed to force the transition
            setTimeout(function() {
                $('#jobModal').addClass('modal-show');
            }, 10);
        }


        // Open Modal for Adding
        $('.add-job-btn').click(function() {
            $('#modalTitle').text('Add Job');
            $('#actionType').val('add');
            $('#confirmBtn').text('Confirm');
            openModal(); // Use the new function
        });

        // Open Modal for Editing 
        $('#job-list').on('click', '.edit-btn', function() {
            // 1. Get the parent row of the clicked button
            var $row = $(this).closest('.table-row');

            // 2. Get the unique ID from the button's data-id attribute
            var currentId = $(this).data('data-id');
            var currentDeptId = $(this).data('dept-id');

            // 3. Extract Department Name (It's the first .table-cell.data in the row)
            // We use .eq(0) to target the first cell and .text().trim() to clean the data
            var currentName = $row.find('.table-cell.data').eq(1).text().trim();

            // 4. Extract Description (It's the table-cell with class .description.data)
            var currentDesc = $row.find('.table-cell.description.data').text().trim();

            // 5. Extract Education
            var currentEd = $row.find('.table-cell.education.data').text().trim();

            // 6. Extract Skills 
            var currentSkills = $row.find('.table-cell.skills.data').text().trim();

            // 7. Extract Experience 
            var currentExp = $row.find('.table-cell.experience.data').text().trim();

            // 8. Extract Language 
            var currentLan = $row.find('.table-cell.language.data').text().trim();

            // 4. Extract Others
            var currentOthers = $row.find('.table-cell.others.data').text().trim();

            // --- Populate the modal fields ---

            // Hidden ID field (Crucial for the UPDATE query in crud_department.php)
            $('#jobId').val(currentId);

            // 🟢 Department Dropdown FIX: Set the value using the retrieved ID
            $('#departmentSelect').val(currentDeptId);

            // Visible fields
            $('#jobNameInput').val(currentName);
            $('#jobDescriptionInput').val(currentDesc);
            $('#educationInput').val(currentEd);
            $('#skillsInput').val(currentSkills);
            $('#experienceInput').val(currentExp);
            $('#languageInput').val(currentLan);
            $('#othersInput').val(currentOthers);

            // Set modal title and action type
            $('#modalTitle').text('Edit Job: ' + currentName);
            $('#actionType').val('edit');
            $('#confirmBtn').text('Save Changes');

            // Open the modal with animation
            openModal();
        });

        // Close Modal handlers
        $('#cancelBtn').click(closeModal);
        $('#jobModal').click(function(e) {
            if (e.target.id === 'jobModal') {
                closeModal();
            }
        });

        // Open Modal for Deleting (attached to buttons loaded via PHP)
        $('#job-list').on('click', '.delete-btn', function() {
            var $row = $(this).closest('.table-row');
            var currentId = $(this).data('id');
            var currentName = $row.find('.table-cell.data').eq(1).text().trim();

            // Populate the modal fields
            $('#deleteJobId').val(currentId);
            $('#jobToDeleteName').text(currentName);

            // Open the modal (using the flex display for centering and animation)
            $('#deleteModal').css('display', 'flex');
            setTimeout(function() {
                $('#deleteModal').addClass('modal-show');
            }, 10);
        });

        // Function to close the delete modal
        function closeDeleteModal() {
            $('#deleteModal').removeClass('modal-show');
            setTimeout(function() {
                $('#deleteModal').hide();
                // Clear the ID on close
                $('#deleteJobId').val('');
            }, 300);
        }

        // Close Delete Modal handlers
        $('#cancelDeleteBtn').click(closeDeleteModal);

        // Close modal if user clicks outside the content area (on the overlay)
        $('#deleteModal').click(function(e) {
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });

        // --- Function to fetch and populate the Department Dropdown ---
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

                // Loop through the results and add options
                response.forEach(dept => {
                    $select.append(`<option value="${dept.id}">${dept.name}</option>`);
                });

            } catch (error) {
                console.error("Error fetching departments:", error);
            }
        }

        // --- NOTIFICATION FUNCTIONALITY (Add this to the start of the $(document).ready function) ---

        function displayNotification() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');

            if (status && message) {
                const decodedMessage = decodeURIComponent(message);
                const $box = $('#notification-box');
                const $msg = $('#notification-message');

                $box.removeClass('success error'); // Clear previous classes
                $msg.text(decodedMessage);

                if (status === 'success') {
                    $box.addClass('success');
                } else if (status === 'error') {
                    $box.addClass('error');
                }

                $box.slideDown(300);

                // Remove the parameters from the URL after display (optional, keeps URL clean)
                history.replaceState(null, null, window.location.pathname);

                // Auto-hide after 5 seconds
                setTimeout(function() {
                    $box.slideUp(500);
                }, 5000);
            }
        }

        // Call the function on page load
        displayNotification();
    </script>


</body>

</html>