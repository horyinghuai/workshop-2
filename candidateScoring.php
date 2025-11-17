<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Resume Reader</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="candidateScoring.css" />
    <style>
    /* --- Modal & small UI styles added to your CSS file --- */

    /* Modal overlay */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.35);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1200;
        padding: 2rem;
    }

    .modal-overlay.visible { display: flex; }

    /* Resume viewer (iframe) modal */
    .resume-modal {
        width: 900px;
        max-width: calc(100% - 4rem);
        border-radius: 18px;
        background: transparent;
        padding: 1rem;
        box-shadow: 0 10px 40px rgba(0,0,0,0.35);
    }

    .resume-modal .inner {
        border-radius: 12px;
        background: white;
        padding: 1rem;
        height: 80vh;
        display: flex;
        flex-direction: column;
    }

    .resume-modal .modal-header {
        display: flex;
        justify-content: flex-end;
    }

    .resume-modal .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
    }

    .resume-modal iframe {
        border: none;
        width: 100%;
        flex: 1;
        border-radius: 8px;
    }

    /* Change Status modal (styling approximates the provided image) */
    .status-modal-wrapper {
        width: 680px;
        max-width: calc(100% - 4rem);
        border-radius: 20px;
        background: #9dc3c2; /* outer teal */
        padding: 1rem;
    }

    .status-modal {
        background: white;
        border-radius: 12px;
        padding: 2rem;
    }

    .status-modal h2 {
        text-align: center;
        font-size: 2rem;
        margin-bottom: 1.25rem;
        color: #1f3a3a;
    }

    .status-modal .form-group {
        margin-bottom: 2rem;
    }

    .status-modal label {
        font-weight: 700;
        font-size: 1.1rem;
        color: #1f3a3a;
        display: block;
        margin-bottom: .75rem;
    }

    .status-modal select {
        width: 100%;
        padding: 0.9rem 1rem;
        border-radius: 14px;
        border: 1px solid #d0dada;
        background: #d9eded;
        font-size: 1rem;
    }

    .status-modal .actions {
        display:flex;
        justify-content: space-between;
        gap: 1rem;
    }

    .status-modal .btn-confirm {
        background: #14c155; /* bright green */
        color: white;
        border: none;
        padding: .8rem 2rem;
        border-radius: 10px;
        font-size: 1.05rem;
        cursor: pointer;
    }

    .status-modal .btn-cancel {
        background: #df4747; /* red */
        color: white;
        border: none;
        padding: .8rem 2rem;
        border-radius: 10px;
        font-size: 1.05rem;
        cursor: pointer;
    }

    /* Delete Selected button style */
    #deleteSelectedBtn {
        display: none;
        margin-left: 1rem;
        padding: 0.6rem 1rem;
        border-radius: 8px;
        border: none;
        background: #e04b4b;
        color: white;
        font-weight: 600;
        cursor: pointer;
    }

    /* small responsive tweaks */
    @media (max-width: 768px) {
        .status-modal-wrapper { width: 92%; padding: .5rem; }
        .resume-modal { width: 92%; }
    }

    </style>
</head>
<body>
<?php
    // Capture current email for links
    $currentEmail = isset($_GET['email']) ? urlencode($_GET['email']) : '';
?>
    <header class="header">
        <a href="dashboard.php?email=<?php echo $currentEmail; ?>" class="back-link">
            <i class="fas fa-chevron-left"></i> Back
        </a>
        <h1 class="header-title">Resume Reader</h1>
        <a href="logout.php" class="logout-link">Log Out</a>
    </header>

    <div class="main-content">
        <div class="filter-sidebar">
            <div class="filter-header">
                <h2>Filter</h2>
                <button class="reset-btn" id="resetFilters">Reset</button>
            </div>

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

            <div class="filter-group">
                <button class="filter-title" data-target="job-position-options">
                    Job Position <i class="fas fa-chevron-right"></i>
                </button>
                <div class="filter-options" id="job-position-options"></div>
            </div>

            <div class="filter-group">
                <button class="filter-title" data-target="department-options">
                    Department <i class="fas fa-chevron-right"></i>
                </button>
                <div class="filter-options" id="department-options"></div>
            </div>
        </div>

        <div class="content-area">
            <div class="top-controls">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <div class="dropdown-container">
                        <select class="dropdown-select" id="scoreDropdown">
                            <option value="All">All</option>
                            <option value="Education">Education</option>
                            <option value="Skills">Skills</option>
                            <option value="Experience">Experience</option>
                            <option value="Language">Language</option>
                        </select>
                    </div>
                    <!-- Delete selected appears to the right of the dropdown -->
                    <button id="deleteSelectedBtn"><i class="fas fa-trash"></i> Delete Selected Row</button>
                </div>

                <div class="search-container" style="margin-left:auto;">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search" id="searchInput">
                </div>
            </div>

            <div class="candidate-table-container">
                <table class="candidate-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Name</th>
                            <th>Applied Job Position</th>
                            <th>Department</th>
                            <th>Applied Date</th>
                            <th>Overall Score</th>
                            <th>Education Score</th>
                            <th>Skills Score</th>
                            <th>Experience Score</th>
                            <th>Language Score</th>
                            <th>Others Score</th>
                            <th>Status</th>

                            <!-- New columns to the right -->
                            <th>Original Resume</th>
                            <th>Formatted Resume</th>
                            <th>Report</th>
                            <th>Staff In Charge</th>
                        </tr>
                    </thead>
                    <tbody id="candidateTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Resume Viewer Modal -->
    <div id="resumeViewerOverlay" class="modal-overlay" role="dialog" aria-modal="true">
        <div class="resume-modal">
            <div class="inner">
                <div class="modal-header">
                    <button class="close-btn" id="closeResumeViewer">&times;</button>
                </div>
                <iframe id="resumeViewerIframe" src="" title="Resume Viewer"></iframe>
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div id="statusOverlay" class="modal-overlay" role="dialog" aria-modal="true">
        <div class="status-modal-wrapper">
            <div class="status-modal">
                <h2>Change Status</h2>
                <div class="form-group">
                    <label for="statusSelect">Change Status</label>
                    <select id="statusSelect">
                        <option value="Active">Active</option>
                        <option value="Hired">Hired</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="actions">
                    <button class="btn-confirm" id="confirmStatusBtn">Confirm</button>
                    <button class="btn-cancel" id="cancelStatusBtn">Cancel</button>
                </div>
            </div>
        </div>
    </div>

<script>
    // expose current email to JS
    const currentEmail = '<?php echo $currentEmail; ?>';

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
                        <label>
                            <input type="checkbox" name="job_position" value="${escapeHtml(job)}"> ${escapeHtml(job)}
                        </label>
                    </div>
                `;
            });

            const departmentOptions = document.getElementById('department-options');
            departmentOptions.innerHTML = ''; // Clear existing
            data.departments.forEach(dept => {
                departmentOptions.innerHTML += `
                    <div class="filter-option">
                        <label>
                            <input type="checkbox" name="department" value="${escapeHtml(dept)}"> ${escapeHtml(dept)}
                        </label>
                    </div>
                `;
            });
        } catch (error) {
            console.error('Error fetching dynamic filters:', error);
        }
    }

    // Escape helper to avoid XSS
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // --- Fetch Candidates Function ---
    async function fetchCandidates() {
        const selectedStatuses = Array.from(document.querySelectorAll('input[name="status"]:checked')).map(cb => cb.value);
        const selectedJobPositions = Array.from(document.querySelectorAll('input[name="job_position"]:checked')).map(cb => cb.value);
        const selectedDepartments = Array.from(document.querySelectorAll('input[name="department"]:checked')).map(cb => cb.value);
        const searchTerm = document.getElementById('searchInput').value;
        const sortBy = document.getElementById('scoreDropdown').value;

        const params = new URLSearchParams();
        selectedStatuses.forEach(s => params.append('status[]', s));
        selectedJobPositions.forEach(jp => params.append('job_position[]', jp));
        selectedDepartments.forEach(d => params.append('department[]', d));
        if (searchTerm) params.append('search', searchTerm);
        if (sortBy) params.append('sort_by', sortBy);

        try {
            const response = await fetch(`get_candidates.php?${params.toString()}`);
            const candidates = await response.json();

            const tableBody = document.getElementById('candidateTableBody');
            tableBody.innerHTML = ''; // Clear existing rows

            if (!Array.isArray(candidates) || candidates.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="16" style="text-align: center; padding: 2rem;">No candidates found matching your criteria.</td></tr>`;
                updateDeleteButtonVisibility();
                return;
            }

            candidates.forEach(candidate => {
                // Each button carries dataset attributes so we can open resume or navigate to report
                const row = document.createElement('tr');

                row.innerHTML = `
                    <td><input type="checkbox" name="candidate_check" value="${escapeHtml(candidate.id)}"></td>
                    <td>${escapeHtml(candidate.name)}</td>
                    <td>${escapeHtml(candidate.applied_job_position)}</td>
                    <td>${escapeHtml(candidate.department)}</td>
                    <td>${escapeHtml(candidate.applied_date)}</td>
                    <td>${escapeHtml(candidate.overall_score)}</td>
                    <td>${escapeHtml(candidate.education_score)}</td>
                    <td>${escapeHtml(candidate.skills_score)}</td>
                    <td>${escapeHtml(candidate.experience_score)}</td>
                    <td>${escapeHtml(candidate.language_score)}</td>
                    <td>${escapeHtml(candidate.others_score)}</td>
                    <td>
                        <button class="status-btn" data-candidate-id="${escapeHtml(candidate.id)}" data-current-status="${escapeHtml(candidate.status)}">
                            ${escapeHtml(candidate.status)}
                        </button>
                    </td>

                    <td>
                        <button class="btn-original" data-candidate-id="${escapeHtml(candidate.id)}" data-src="${escapeHtml(candidate.resume_original)}">Original Resume</button>
                    </td>
                    <td>
                        <button class="btn-formatted" data-candidate-id="${escapeHtml(candidate.id)}" data-src="${escapeHtml(candidate.resume_formatted)}">Formatted Resume</button>
                    </td>
                    <td>
                        <button class="btn-report" data-candidate-id="${escapeHtml(candidate.id)}">Report</button>
                    </td>
                    <td>${escapeHtml(candidate.staff_in_charge || '')}</td>
                `;

                tableBody.appendChild(row);
            });

            // add event listeners for new buttons / checkboxes
            attachRowEventListeners();
            updateDeleteButtonVisibility();

        } catch (error) {
            console.error('Error fetching candidates:', error);
            document.getElementById('candidateTableBody').innerHTML = `<tr><td colspan="16" style="text-align: center; color: red;">Error loading data. Please try again.</td></tr>`;
            updateDeleteButtonVisibility();
        }
    }

    function attachRowEventListeners() {
        // Individual checkboxes
        document.querySelectorAll('input[name="candidate_check"]').forEach(cb => {
            cb.removeEventListener('change', onRowCheckboxChange);
            cb.addEventListener('change', onRowCheckboxChange);
        });

        // Status buttons
        document.querySelectorAll('.status-btn').forEach(btn => {
            btn.removeEventListener('click', onStatusButtonClick);
            btn.addEventListener('click', onStatusButtonClick);
        });

        // Resume buttons
        document.querySelectorAll('.btn-original').forEach(btn => {
            btn.removeEventListener('click', onOpenResume);
            btn.addEventListener('click', onOpenResume);
        });
        document.querySelectorAll('.btn-formatted').forEach(btn => {
            btn.removeEventListener('click', onOpenResume);
            btn.addEventListener('click', onOpenResume);
        });

        // Report button - navigate to report.php with email and candidate_id
        document.querySelectorAll('.btn-report').forEach(btn => {
            btn.removeEventListener('click', onReportClick);
            btn.addEventListener('click', onReportClick);
        });

        // Select All checkbox handling: update checked states after rendering
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = false;
            selectAll.removeEventListener('change', onSelectAllChange);
            selectAll.addEventListener('change', onSelectAllChange);
        }
    }

    // --- Delete Selected Button Logic ---
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    deleteBtn.addEventListener('click', async () => {
        const selected = Array.from(document.querySelectorAll('input[name="candidate_check"]:checked')).map(cb => cb.value);
        if (!selected.length) return;

        if (!confirm(`Delete ${selected.length} selected row(s)? This action cannot be undone.`)) return;

        try {
            // send POST to delete_candidates.php
            const formData = new FormData();
            selected.forEach(id => formData.append('ids[]', id));

            const resp = await fetch('delete_candidates.php', {
                method: 'POST',
                body: formData
            });
            const result = await resp.json();
            if (result && result.success) {
                // refresh table
                await fetchCandidates();
            } else {
                alert('Failed to delete selected rows.');
            }
        } catch (err) {
            console.error(err);
            alert('Error deleting rows.');
        }
    });

    // update delete button visibility based on whether any checkbox is checked
    function updateDeleteButtonVisibility() {
        const anyChecked = document.querySelectorAll('input[name="candidate_check"]:checked').length > 0;
        deleteBtn.style.display = anyChecked ? 'inline-block' : 'none';
    }

    // on row checkbox change
    function onRowCheckboxChange() {
        updateDeleteButtonVisibility();
        // update selectAll state
        const all = document.querySelectorAll('input[name="candidate_check"]');
        const checked = document.querySelectorAll('input[name="candidate_check"]:checked');
        const selectAll = document.getElementById('selectAll');
        if (all.length) selectAll.checked = (checked.length === all.length);
    }

    function onSelectAllChange() {
        const checked = this.checked;
        document.querySelectorAll('input[name="candidate_check"]').forEach(cb => cb.checked = checked);
        updateDeleteButtonVisibility();
    }

    // --- Resume Viewer ---
    const resumeOverlay = document.getElementById('resumeViewerOverlay');
    const resumeIframe = document.getElementById('resumeViewerIframe');
    const closeResumeBtn = document.getElementById('closeResumeViewer');

    function onOpenResume(e) {
        const btn = e.currentTarget;
        const src = btn.dataset.src;
        if (!src) {
            alert('No resume file available.');
            return;
        }
        // If you store files in a protected folder, implement an endpoint to stream the file.
        resumeIframe.src = src;
        resumeOverlay.classList.add('visible');
    }

    closeResumeBtn.addEventListener('click', () => {
        resumeIframe.src = '';
        resumeOverlay.classList.remove('visible');
    });

    // also close modal when clicking overlay outside the modal
    resumeOverlay.addEventListener('click', (ev) => {
        if (ev.target === resumeOverlay) {
            resumeIframe.src = '';
            resumeOverlay.classList.remove('visible');
        }
    });

    // --- Report button handler ---
    function onReportClick(e) {
        const id = e.currentTarget.dataset.candidateId;
        if (!id) return;
        // Navigate to report page with email + candidate_id
        const url = `report.php?email=${currentEmail}&candidate_id=${encodeURIComponent(id)}`;
        window.location.href = url;
    }

    // --- Status change modal logic ---
    const statusOverlay = document.getElementById('statusOverlay');
    const statusSelect = document.getElementById('statusSelect');
    const confirmStatusBtn = document.getElementById('confirmStatusBtn');
    const cancelStatusBtn = document.getElementById('cancelStatusBtn');

    let currentStatusCandidateId = null;

    function onStatusButtonClick(e) {
        const btn = e.currentTarget;
        currentStatusCandidateId = btn.dataset.candidateId;
        const currentStatus = btn.dataset.currentStatus || 'Active';
        statusSelect.value = currentStatus;
        statusOverlay.classList.add('visible');
    }

    confirmStatusBtn.addEventListener('click', async () => {
        const newStatus = statusSelect.value;
        if (!currentStatusCandidateId) return;

        try {
            const formData = new FormData();
            formData.append('candidate_id', currentStatusCandidateId);
            formData.append('status', newStatus);

            const resp = await fetch('update_status.php', {
                method: 'POST',
                body: formData
            });
            const result = await resp.json();
            if (result && result.success) {
                statusOverlay.classList.remove('visible');
                currentStatusCandidateId = null;
                await fetchCandidates();
            } else {
                alert('Failed to update status.');
            }
        } catch (err) {
            console.error(err);
            alert('Error updating status.');
        }
    });

    cancelStatusBtn.addEventListener('click', () => {
        statusOverlay.classList.remove('visible');
        currentStatusCandidateId = null;
    });

    statusOverlay.addEventListener('click', (ev) => {
        if (ev.target === statusOverlay) {
            statusOverlay.classList.remove('visible');
            currentStatusCandidateId = null;
        }
    });

    // --- Event Listeners for Filters and Search ---
    document.getElementById('searchInput').addEventListener('input', debounce(fetchCandidates, 300));
    document.getElementById('scoreDropdown').addEventListener('change', fetchCandidates);

    document.getElementById('status-options').addEventListener('change', fetchCandidates);
    document.getElementById('job-position-options').addEventListener('change', fetchCandidates);
    document.getElementById('department-options').addEventListener('change', fetchCandidates);

    // Initial fetch when page loads
    document.addEventListener('DOMContentLoaded', async () => {
        await fetchDynamicFilters(); // Load dynamic checkboxes first
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

    // Debounce helper
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

</script>
</body>
</html>
