<?php
// Include the database connection file
include 'connection.php';

// Redirect to login if user not logged in
if (!isset($_GET['email'])) {
    header('Location: login.php');
    exit();
}

$current_email = $conn->real_escape_string($_GET['email']);

// Fetch user details based on email
$sql = "SELECT name FROM user WHERE email = '$current_email'";
$result = $conn->query($sql);

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $user_name = $row['name'];
} else {
    // Redirect to login if email is invalid
    header('Location: login.php');
    exit();
}

// Check if the connection was successful
if ($conn->connect_error) {
    die("Database connection failed in jobDepartment.php: " . $conn->connect_error);
}

// 1. SQL Query to fetch department data
$sql = "SELECT * FROM department ORDER BY department_name ASC";
$result = $conn->query($sql);

// Initialize a variable to hold the HTML for the table rows
$department_rows_html = '';

// 2. Check for results and build the table rows
if ($result->num_rows > 0) {
    // Loop through each row fetched from the database
    while ($row = $result->fetch_assoc()) {
        // Use PHP to dynamically generate the HTML for each department row
        $department_rows_html .= '
            <div class="table-row">
                <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                <div class="table-cell action data">
                    <button class="edit-btn" data-id="' . $row["department_id"] . '"><i class="fas fa-edit"></i> Edit</button>
                    <button class="delete-btn" data-id="' . $row["department_id"] . '"><i class="fas fa-trash-alt"></i> Delete</button>
                </div>
            </div>';
    }
} else {
    // Message if no departments are found
    $department_rows_html = '
        <div class="table-row no-data">
            <div class="table-cell data" colspan="3">No departments found.</div>
        </div>';
}

// 3. Close the database connection
$conn->close();

// --- CAPTURE CURRENT EMAIL FOR NAVIGATION ---
$currentEmail = isset($_GET['email']) ? $_GET['email'] : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Reader - Departments</title>
    <link rel="stylesheet" href="jobDepartment.css">
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
            <a href="jobPosition.php?email=<?php echo urlencode($currentEmail); ?>">Job Position</a>
            <a href="#">Department</a>
            <a href="logout.php" class="logout">Log Out</a>
        </div>
    </header>

    <div id="notification-box" class="notification-box" style="display: none;">
        <p id="notification-message"></p>
    </div>

    <main class="content-area">
        <button class="add-department-btn">Add Department</button>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search">
            <button class="search-btn" id="search-btn"><i class="fas fa-search"></i></button>
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
                <input type="hidden" id="emailInput" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">

                <input type="hidden" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">

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
            <h2 style="color: #dc3545; margin-bottom: 15px;">Confirm Deletion</h2>
            <p style="text-align: center; margin-bottom: 25px;">
                Are you sure you want to delete the department:
                <strong id="departmentToDeleteName"></strong>?
                This action cannot be undone.
            </p>

            <form id="deleteForm" action="cud_department.php" method="POST">
                <input type="hidden" name="action_type" value="delete">
                <input type="hidden" id="deleteDepartmentId" name="department_id" value="">
                <?php $currentEmail = isset($_GET['email']) ? $_GET['email'] : ''; ?>

                <input type="hidden" id="emailInput" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">

                <input  type="hidden" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>">

                <div class="form-actions">
                    <button type="submit" class="btn btn-confirm" style="background-color: #3a7c7c;">Yes, Delete</button>
                    <button type="button" class="btn btn-cancel" id="cancelDeleteBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            // Attach the search button click event
            $('#search-btn').click(function() {
                performRAGSearch();
            });

            // Attach the Enter keypress event
            $('#search-input').keypress(function(e) {
                if (e.which == 13) { // 13 is the Enter key code
                    performRAGSearch();
                }
            });

            // Initial load function/Reset on empty
            $('#search-input').on('keyup', function() {
                if ($(this).val().trim() === '') {
                    performRAGSearch();
                }
            });
        });

        // --- RAG SEARCH FUNCTION ---
        function performRAGSearch() {
            var nlQuery = $('#search-input').val().trim(); 
            
            // If query is empty, reload the page to reset the table
            if (nlQuery === "") {
                window.location.reload();
                return;
            }

            // Show loading state
            $('#department-list').html('<div class="table-row"><div class="table-cell data" style="grid-column: 1 / span 3; text-align: center;">Searching Semantically (RAG)...</div></div>');

            $.ajax({
                url: 'execute_rag_query_dept.php', // Points to the new Department RAG script
                type: 'POST',
                data: {
                    nl_query: nlQuery
                }, 
                success: function(response) {
                    $('#department-list').html(response);
                },
                error: function() {
                    $('#department-list').html('<div class="table-row"><div class="table-cell data" style="grid-column: 1 / span 3; text-align: center; color: red;">Network error during RAG process.</div></div>');
                }
            });
        }

        // --- MODAL FUNCTIONALITY (ANIMATED) ---

        // Function to close the modal
        function closeModal() {
            $('#departmentModal').removeClass('modal-show');
            setTimeout(function() {
                $('#departmentModal').hide();
                $('#departmentForm')[0].reset();
                $('#departmentId').val('');
            }, 300); 
        }

        // Function to open the modal
        function openModal() {
            $('#departmentModal').css('display', 'flex');
            setTimeout(function() {
                $('#departmentModal').addClass('modal-show');
            }, 10);
        }

        // Open Modal for Adding
        $('.add-department-btn').click(function() {
            $('#modalTitle').text('Add Department');
            $('#actionType').val('add');
            $('#confirmBtn').text('Confirm');
            openModal();
        });

        // Open Modal for Editing 
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

        // Close Modal handlers
        $('#cancelBtn').click(closeModal);
        $('#departmentModal').click(function(e) {
            if (e.target.id === 'departmentModal') {
                closeModal();
            }
        });

        // Open Modal for Deleting
        $('#department-list').on('click', '.delete-btn', function() {
            var $row = $(this).closest('.table-row');
            var currentId = $(this).data('id');
            var currentName = $row.find('.table-cell.data').eq(0).text().trim();

            $('#deleteDepartmentId').val(currentId);
            $('#departmentToDeleteName').text(currentName);

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
                $('#deleteDepartmentId').val('');
            }, 300);
        }

        // Close Delete Modal handlers
        $('#cancelDeleteBtn').click(closeDeleteModal);
        $('#deleteModal').click(function(e) {
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });

        // --- NOTIFICATION FUNCTIONALITY ---
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
                history.replaceState(null, null, window.location.pathname);

                setTimeout(function() {
                    $box.slideUp(500);
                }, 5000);
            }
        }
        displayNotification();
    </script>
</body>
</html>