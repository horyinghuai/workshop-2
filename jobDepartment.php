<?php
// Include the database connection file
include 'connection.php';

// Check if the connection was successful (though connection.php handles the die() case)
if ($conn->connect_error) {
    // This line is mostly redundant if connection.php works, but good for safety
    die("Database connection failed in jobDepartment.php: " . $conn->connect_error);
}

// 1. SQL Query to fetch department data
$sql = "SELECT department_name, description FROM department ORDER BY department_name ASC";
$result = $conn->query($sql);

// Initialize a variable to hold the HTML for the table rows
$department_rows_html = '';

// 2. Check for results and build the table rows
if ($result->num_rows > 0) {
    // Loop through each row fetched from the database
    while($row = $result->fetch_assoc()) {
        // Use PHP to dynamically generate the HTML for each department row
        $department_rows_html .= '
            <div class="table-row">
                <div class="table-cell data">' . htmlspecialchars($row["department_name"]) . '</div>
                <div class="table-cell description data">' . htmlspecialchars($row["description"]) . '</div>
                <div class="table-cell action data">
                    <button class="edit-btn"><i class="fas fa-edit"></i> Edit</button>
                    <button class="delete-btn"><i class="fas fa-trash-alt"></i> Delete</button>
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
            <i class="fas fa-arrow-left"></i>
            <span>Back</span>
        </div>
        <h1 class="logo">Resume Reader</h1>
        <div class="header-right">
            <a href="#">Job Position</a>
            <a href="#">Department</a>
            <a href="#" class="logout">Log Out</a>
        </div>
    </header>

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

    <script>
$(document).ready(function() {
    // Function to handle the search logic
    function performSearch() {
        var searchTerm = $('#search-input').val(); // Get the value from the search input

        $.ajax({
            url: 'search_department.php', // The new file we created to handle the request
            type: 'POST',
            data: { search_term: searchTerm }, // Send the search term to the PHP file
            // Before sending (optional: for user feedback)
            beforeSend: function() {
                $('#department-list').html('<div class="table-row"><div class="table-cell data" style="width: 100%; text-align: center;">Searching...</div></div>');
            },
            // On success, update the content
            success: function(response) {
                // Replace the content of the department list with the new rows from the PHP file
                $('#department-list').html(response); 
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
});
</script>
</body>
</html>