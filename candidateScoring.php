<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Reader</title>
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Link to your CSS file -->
    <link rel="stylesheet" href="candidateScoring.css">
</head>
<body>

    <div class="header">
        <div class="header-left">
            <a href="register.php" class="back-btn">
                <i class="fas fa-chevron-left"></i> Back
            </a>
        </div>
        <div class="header-center">
            Resume Reader
        </div>
        <div class="header-right">
            <a href="logout.php" class="logout-btn">Log Out</a>
        </div>
    </div>

    <div class="main-content">
        <div class="filter-sidebar">
            <div class="filter-header">
                <h2>Filter</h2>
                <button class="reset-btn" id="resetFilters">Reset</button>
            </div>

            <!-- Status Filter (Static) -->
            <div class="filter-group">
                <button class="filter-title" data-target="status-options">
                    Status <i class="fas fa-chevron-right"></i>
                </button>
                <div class="filter-options" id="status-options">
                    <div class="filter-option">
                        <label>
                            <input type="checkbox" name="status" value="Active"> Active
                        </label>
                    </div>
                    <div class="filter-option">
                        <label>
                            <input type="checkbox" name="status" value="Hired"> Hired
                        </label>
                    </div>
                    <div class="filter-option">
                        <label>
                            <input type="checkbox" name="status" value="Rejected"> Rejected
                        </label>
                    </div>
                    <div class="filter-option">
                        <label>
                            <input type="checkbox" name="status" value="Cancelled"> Cancelled
                        </label>
                    </div>
                </div>
            </div>

            <!-- Job Position Filter (Dynamic) -->
            <div class="filter-group">
                <button class="filter-title" data-target="job-position-options">
                    Job Position <i class="fas fa-chevron-right"></i>
                </button>
                <div class="filter-options" id="job-position-options">
                    <!-- Checkboxes will be loaded here by JavaScript -->
                </div>
            </div>

            <!-- Department Filter (Dynamic) -->
            <div class="filter-group">
                <button class="filter-title" data-target="department-options">
                    Department <i class="fas fa-chevron-right"></i>
                </button>
                <div class="filter-options" id="department-options">
                    <!-- Checkboxes will be loaded here by JavaScript -->
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="top-controls">
                <div class="dropdown-container">
                    <select class="dropdown-select" id="scoreDropdown">
                        <option value="All">All</option>
                        <option value="Education">Education</option>
                        <option value="Skills">Skills</option>
                        <option value="Experience">Experience</option>
                        <option value="Achievements">Achievements</option>
                        <option value="Language">Language</option>
                    </select>
                </div>
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search" id="searchInput">
                </div>
            </div>

            <div class="candidate-table-container">
                <table class="candidate-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"> Check</th>
                            <th>Name</th>
                            <th>Applied Job Position</th>
                            <th>Department</th>
                            <th>Applied Date</th>
                            <th>Overall Score</th>
                            <th>Education Score</th>
                            <th>Skills Score</th>
                            <th>Experience Score</th>
                            <th>Achievements Score</th>
                            <th>Language Score</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="candidateTableBody">
                        <!-- Candidate rows will be loaded here by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // --- Filter Toggle Logic ---
        document.querySelectorAll('.filter-title').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const targetOptions = document.getElementById(targetId);
                this.classList.toggle('active'); // Toggles the arrow icon
                targetOptions.classList.toggle('visible');
            });
        });

        // --- Fetch Dynamic Filter Options (Job Positions & Departments) ---
        async function fetchDynamicFilters() {
            try {
                const response = await fetch('get_filters.php');
                const data = await response.json();

                const jobPositionOptions = document.getElementById('job-position-options');
                jobPositionOptions.innerHTML = ''; // Clear existing
                data.job_positions.forEach(job => {
                    jobPositionOptions.innerHTML += `
                        <div class="filter-option">
                            <button class="filter-button" onclick="filterByJob('${job}')">${job}</button>
                        </div>
                    `;
                });

                const departmentOptions = document.getElementById('department-options');
                departmentOptions.innerHTML = ''; // Clear existing
                data.departments.forEach(dept => {
                    departmentOptions.innerHTML += `
                        <div class="filter-option">
                            <button class="filter-button" onclick="filterByDepartment('${dept}')">${dept}</button>
                        </div>
                    `;
                });
            } catch (error) {
                console.error('Error fetching dynamic filters:', error);
            }
        }

        function filterByJob(jobName) {
            const params = new URLSearchParams();
            params.append('job_position', jobName);
            fetchCandidates(params);
        }

        function filterByDepartment(departmentName) {
            const params = new URLSearchParams();
            params.append('department', departmentName);
            fetchCandidates(params);
        }

        // --- Fetch Candidates Function ---
        async function fetchCandidates(extraParams = null) {
            const selectedStatuses = Array.from(document.querySelectorAll('input[name="status"]:checked')).map(cb => cb.value);
            const selectedJobPositions = Array.from(document.querySelectorAll('input[name="job_position"]:checked')).map(cb => cb.value);
            const selectedDepartments = Array.from(document.querySelectorAll('input[name="department"]:checked')).map(cb => cb.value);
            const searchTerm = document.getElementById('searchInput').value;
            const sortBy = document.getElementById('scoreDropdown').value;

            const params = new URLSearchParams();
            selectedStatuses.forEach(s => params.append('status[]', s));
            selectedJobPositions.forEach(jp => params.append('job_position[]', jp));
            selectedDepartments.forEach(d => params.append('department[]', d));
            if (searchTerm) {
                params.append('search', searchTerm);
            }
            if (sortBy) {
                params.append('sort_by', sortBy);
            }
            if (extraParams) {
                for (const [key, value] of extraParams) {
                    params.append(key, value);
                }
            }

            try {
                const response = await fetch(`get_candidates.php?${params.toString()}`);
                const candidates = await response.json();
                
                const tableBody = document.getElementById('candidateTableBody');
                tableBody.innerHTML = ''; // Clear existing rows

                if (candidates.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="12" style="text-align: center; padding: 2rem;">No candidates found matching your criteria.</td></tr>`;
                    return;
                }

                candidates.forEach(candidate => {
                    const row = `
                        <tr>
                            <td><input type="checkbox" name="candidate_check" value="${candidate.id}"></td>
                            <td>${candidate.name}</td>
                            <td>${candidate.applied_job_position}</td>
                            <td>${candidate.department}</td>
                            <td>${candidate.applied_date}</td>
                            <td>${candidate.overall_score}</td>
                            <td>${candidate.education_score}</td>
                            <td>${candidate.skills_score}</td>
                            <td>${candidate.experience_score}</td>
                            <td>${candidate.achievements_score}</td>
                            <td>${candidate.language_score}</td>
                            <td><span class="status-badge status-${candidate.status}">${candidate.status}</span></td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            } catch (error) {
                console.error('Error fetching candidates:', error);
                document.getElementById('candidateTableBody').innerHTML = `<tr><td colspan="12" style="text-align: center; color: red;">Error loading data. Please try again.</td></tr>`;
            }
        }

        // --- Event Listeners for Filters and Search ---
        document.getElementById('searchInput').addEventListener('input', fetchCandidates);
        document.getElementById('scoreDropdown').addEventListener('change', fetchCandidates);
        
        // Initial fetch when page loads
        document.addEventListener('DOMContentLoaded', () => {
            fetchDynamicFilters(); // Load dynamic checkboxes first
            fetchCandidates(); // Then load candidates
        });

        // --- Reset Filters ---
        document.getElementById('resetFilters').addEventListener('click', () => {
            // Uncheck all checkboxes
            document.querySelectorAll('.filter-sidebar input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            // Clear search input
            document.getElementById('searchInput').value = '';
            // Reset dropdown to "All"
            document.getElementById('scoreDropdown').value = 'All';
            // Close all filter options
            document.querySelectorAll('.filter-title.active').forEach(btn => btn.click());
            // Re-fetch candidates with reset filters
            fetchCandidates();
        });

        // --- Select All Checkbox (Optional, but useful) ---
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="candidate_check"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

    </script>

</body>
</html>