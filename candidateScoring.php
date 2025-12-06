<?php
session_start();
// Include connection
include 'connection.php';

// Redirect to login if user not logged in
if (!isset($_GET['email'])) { 
    header('Location: login.php'); 
    exit(); 
}
$currentEmail = $_GET['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Resume Reader | Candidate Scoring</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="candidateScoring.css" />
    <style>
    /* --- Modal & UI styles --- */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.35); display: none;
        align-items: center; justify-content: center; z-index: 1200; padding: 2rem;
    }
    .modal-overlay.visible { display: flex; }

    .edit-modal-wrapper {
        background: #fff; padding: 30px; border: 30px solid #9fc2c6; border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); width: 90%; max-width: 650px;
        box-sizing: border-box; opacity: 0; transform: scale(0.9);
        transition: opacity 0.3s ease-out, transform 0.3s ease-out;
    }
    .modal-overlay.visible .edit-modal-wrapper { opacity: 1; transform: scale(1); }
    .edit-modal-wrapper h2 { text-align: center; margin-bottom: 20px; font-size: 1.5em; color: #333; }
    .edit-modal-wrapper .form-group { margin-bottom: 15px; }
    .edit-modal-wrapper label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
    .edit-modal-wrapper input[type="text"], .edit-modal-wrapper input[type="email"], .edit-modal-wrapper textarea {
        width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;
        box-sizing: border-box; background-color: #d9ebec; font-family: 'Inter', sans-serif;
    }
    
    /* Form Actions */
    .edit-modal-wrapper .form-actions { display: flex; justify-content: space-between; gap: 8px; margin-top: 25px; }
    .edit-modal-wrapper button { padding: 10px; border: none; border-radius: 4px; cursor: pointer; flex: 1; font-weight: bold; color: white; }

    .btn-confirm { background-color: #28a745; }
    .btn-cancel { background-color: #6c757d; }
    
    /* Action Buttons */
    .btn-action-archive { background-color: #fd7e14; }
    .btn-action-restore { background-color: #17a2b8; }
    .btn-action-delete { background-color: #dc3545; }
    
    .btn-action-archive:hover { background-color: #e36d0d; }
    .btn-action-restore:hover { background-color: #138496; }
    .btn-action-delete:hover { background-color: #c82333; }

    .clickable-name { color: #3a7c7c; font-weight: 700; text-decoration: underline; cursor: pointer; transition: color 0.2s; }
    .clickable-name:hover { color: #2e6c73; }

    /* Resume Viewer */
    .resume-modal { width: 900px; max-width: calc(100% - 4rem); border-radius: 18px; background: transparent; padding: 1rem; box-shadow: 0 10px 40px rgba(0,0,0,0.35); }
    .resume-modal .inner { border-radius: 12px; background: white; padding: 1rem; height: 80vh; display: flex; flex-direction: column; }
    .resume-modal .modal-header { display: flex; justify-content: flex-end; }
    .resume-modal .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
    .resume-modal iframe { border: none; width: 100%; flex: 1; border-radius: 8px; }

    /* Status Modal */
    .status-modal-wrapper { width: 680px; max-width: calc(100% - 4rem); border-radius: 20px; background: #9fc2c6; padding: 1.5rem; height: 400px; }
    .status-modal { background: white; border-radius: 12px; padding: 2rem; }
    .status-modal h2 { text-align: center; font-size: 2rem; margin-bottom: 1.25rem; color: #1f3a3a; }
    .status-modal .form-group { margin-bottom: 2rem; }
    .status-modal label { font-weight: 700; font-size: 1.1rem; color: #1f3a3a; display: block; margin-bottom: 1rem; margin-top: 2.5rem; }
    .status-modal select { width: 100%; padding: 0.9rem 1rem; border-radius: 14px; border: 1px solid #d0dada; background: #d9eded; font-size: 1rem; }
    .status-modal .actions { display:flex; justify-content: space-between; gap: 1rem; }
    .status-modal .btn-confirm { background: #14c155; color: white; border: none; padding: .8rem 2rem; border-radius: 10px; font-size: 1.05rem; cursor: pointer; width: 40%; margin-top: 3rem; margin-left: 1rem; }
    .status-modal .btn-cancel { background: #df4747; color: white; border: none; padding: .8rem 2rem; border-radius: 10px; font-size: 1.05rem; cursor: pointer; width: 40%; margin-top: 3rem; margin-right: 1rem; }

    /* Bulk Buttons */
    #archiveSelectedBtn { display: none; margin-left: 1rem; padding: 0.6rem 1rem; border-radius: 8px; border: none; background: #fd7e14; color: white; font-weight: 600; cursor: pointer; }
    #restoreSelectedBtn { display: none; margin-left: 1rem; padding: 0.6rem 1rem; border-radius: 8px; border: none; background: #17a2b8; color: white; font-weight: 600; cursor: pointer; }
    #permanentDeleteSelectedBtn { display: none; margin-left: 1rem; padding: 0.6rem 1rem; border-radius: 8px; border: none; background: #dc3545; color: white; font-weight: 600; cursor: pointer; }

    #compareSelectedBtn { 
        display: none; 
        margin-left: 1rem; 
        padding: 0.6rem 1rem; 
        border-radius: 8px; 
        border: none; 
        background: #2196F3; 
        color: white; 
        font-weight: 600; 
        cursor: pointer; 
    }
    #compareSelectedBtn:hover { background: #1976D2; }

    /* Archive Toggle Button */
    #toggleArchiveBtn {
        margin-left: 1rem; padding: 0.6rem 1rem; border-radius: 8px; border: none;
        background: #6c757d; color: white; font-weight: 600; cursor: pointer;
    }
    #toggleArchiveBtn.active-view { background: #3a7c7c; }

    .btn-accept { background: #28a745; color: white; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; }
    .btn-reject { background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; }
    .btn-restore { background: #17a2b8; color: white; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; margin-left: 5px; }

    /* Loading Popup */
    .loading-popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1300; }
    .loading-popup-content { background: white; padding: 25px 40px; border-radius: 12px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2); width: 300px; }
    .loading-spinner { border: 5px solid #f3f3f3; border-top: 5px solid #3a7c7c; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px auto; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .progress-text { font-weight: bold; color: #3a7c7c; margin-top: 10px; font-size: 1.2rem; }

    @media (max-width: 768px) { .status-modal-wrapper { width: 92%; padding: .5rem; } .resume-modal { width: 92%; } .edit-modal-wrapper { width: 95%; border-width: 15px; } }
    </style>
</head>
<body>
<?php
    $currentEmail = isset($_GET['email']) ? urlencode($_GET['email']) : '';
?>
    <header class="header">
        <a href="dashboard.php?email=<?php echo $currentEmail; ?>" class="back-link"><i class="fas fa-chevron-left"></i> Back</a>
        <h1 class="header-title">Resume Reader</h1>
        <a href="logout.php" class="logout-link">Log Out</a>
    </header>

    <div class="main-content">
        <div class="filter-sidebar">
            <div class="filter-header"><h2>Filter</h2><button class="reset-btn" id="resetFilters">Reset</button></div>
            <div class="filter-group">
                <button class="filter-title" data-target="status-options">Status <i class="fas fa-chevron-right"></i></button>
                <div class="filter-options" id="status-options">
                    <div class="filter-option"><label><input type="checkbox" name="status" value="Active"> Active</label></div>
                    <div class="filter-option"><label><input type="checkbox" name="status" value="Hired"> Hired</label></div>
                    <div class="filter-option"><label><input type="checkbox" name="status" value="Rejected"> Rejected</label></div>
                    <div class="filter-option"><label><input type="checkbox" name="status" value="Cancelled"> Cancelled</label></div>
                </div>
            </div>
            <div class="filter-group">
                <button class="filter-title" data-target="job-position-options">Job Position <i class="fas fa-chevron-right"></i></button>
                <div class="filter-options" id="job-position-options"></div>
            </div>
            <div class="filter-group">
                <button class="filter-title" data-target="department-options">Department <i class="fas fa-chevron-right"></i></button>
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
                    <button id="toggleArchiveBtn"><i class="fas fa-archive"></i> View Archive</button>
                    <button id="compareSelectedBtn"><i class="fas fa-balance-scale"></i> Compare</button>
                    
                    <button id="archiveSelectedBtn"><i class="fas fa-box-archive"></i> Archive</button>
                    <button id="restoreSelectedBtn"><i class="fas fa-trash-restore"></i> Restore</button>
                    <button id="permanentDeleteSelectedBtn"><i class="fas fa-trash"></i> Delete</button>
                </div>
                <div class="search-container" style="margin-left:auto;">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" placeholder="Search" id="searchInput">
                </div>
            </div>
                <h2 id="tableTitle" style="margin: 0 0 10px 10px; color: #3a7c7c;">Active Candidates</h2>
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
                            <th>Outreach</th>
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

    <div id="editCandidateModal" class="modal-overlay">
        <div class="edit-modal-wrapper">
            <h2>Edit Candidate</h2>
            <form id="editCandidateForm">
                <input type="hidden" id="edit_candidate_id" name="candidate_id">
                <input type="hidden" name="action_type" value="update">
                <div class="form-group">
                    <label for="edit_name">Name</label><input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_gender">Gender</label><input type="text" id="edit_gender" name="gender">
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label><input type="email" id="edit_email" name="email">
                </div>
                <div class="form-group">
                    <label for="edit_contact">Contact Number</label><input type="text" id="edit_contact" name="contact_number">
                </div>
                <div class="form-group">
                    <label for="edit_address">Address</label><textarea id="edit_address" name="address" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-confirm" id="btnUpdateCandidate">Confirm</button>
                    
                    <button type="button" class="btn-middle-action" id="btnArchiveRestoreCandidate">Archive</button>
                    
                    <button type="button" class="btn-action-delete" id="btnDeletePermanentCandidate">Delete</button>

                    <button type="button" class="btn-cancel" id="btnCloseEditModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div id="resumeViewerOverlay" class="modal-overlay">
        <div class="resume-modal">
            <div class="inner">
                <div class="modal-header"><button class="close-btn" id="closeResumeViewer">&times;</button></div>
                <iframe id="resumeViewerIframe" src="" title="Resume Viewer"></iframe>
            </div>
        </div>
    </div>

    <div id="statusOverlay" class="modal-overlay">
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

    <div id="outreachModal" class="modal-overlay">
        <div class="modal-content" style="width: 600px; max-width:90%; background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.35);">
            <h2 id="outreachTitle" style="text-align: center; margin-bottom: 1.5rem; color: #1f3a3a;">Generate Email</h2>
            <div id="dateGroup" class="form-group" style="display:none; margin-bottom: 1rem;">
                <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Interview Date & Time</label>
                <input type="datetime-local" id="interviewDate" style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 6px;">
            </div>
            <div id="previewArea" class="form-group" style="margin-bottom: 1.5rem; display: none;">
                <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Email Preview</label>
                <input type="text" id="emailSubject" placeholder="Subject" style="width:100%; margin-bottom:10px; padding:10px; border: 1px solid #ccc; border-radius: 6px; font-weight:bold;">
                <textarea id="emailBody" rows="10" style="width:100%; padding:10px; border: 1px solid #ccc; border-radius: 6px; resize: vertical;"></textarea>
            </div>
            <div id="aiLoading" style="display:none; text-align:center; color:#3a7c7c; margin:10px;">
                <i class="fas fa-spinner fa-spin"></i> Generating email...
            </div>
            <div class="actions" style="margin-top:20px; display:flex; justify-content:space-between; gap: 10px;">
                <button id="btnGenerate" style="background:#3a7c7c; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; flex: 1;">Generate Draft</button>
                <button id="btnSendEmail" style="display:none; background:#28a745; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; flex: 1;">Send Email</button>
                <button onclick="document.getElementById('outreachModal').classList.remove('visible')" style="background:#dc3545; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; flex: 1;">Cancel</button>
            </div>
        </div>
    </div>

    <div id="sendingPopup" class="loading-popup-overlay">
        <div class="loading-popup-content">
            <div class="loading-spinner"></div>
            <h3>Processing...</h3>
            <div class="progress-text" id="sendingProgress">0%</div>
        </div>
    </div>

<script>
    const currentEmail = '<?php echo $currentEmail; ?>';
    let allCandidates = [];
    let isArchiveView = false; // Toggle state

    // --- Archive Toggle ---
    document.getElementById('toggleArchiveBtn').addEventListener('click', function() {
        isArchiveView = !isArchiveView;
        this.innerHTML = isArchiveView ? '<i class="fas fa-list"></i> View Active' : '<i class="fas fa-archive"></i> View Archive';
        this.classList.toggle('active-view', isArchiveView);
        document.getElementById('tableTitle').innerText = isArchiveView ? "Archived Candidates" : "Active Candidates";
        
        // Reset bulk selection
        document.getElementById('selectAll').checked = false;
        
        fetchCandidates();
    });

    // --- Filter UI Logic ---
    document.querySelectorAll('.filter-title').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetOptions = document.getElementById(targetId);
            this.classList.toggle('active');
            targetOptions.classList.toggle('visible');
        });
    });

    // --- Fetch Dynamic Filters ---
    async function fetchDynamicFilters() {
        try {
            const response = await fetch('get_filters.php');
            const data = await response.json();
            const jobPositionOptions = document.getElementById('job-position-options');
            jobPositionOptions.innerHTML = '';
            data.job_positions.forEach(job => {
                jobPositionOptions.innerHTML += `<div class="filter-option"><label><input type="checkbox" name="job_position" value="${escapeHtml(job)}"> ${escapeHtml(job)}</label></div>`;
            });
            const departmentOptions = document.getElementById('department-options');
            departmentOptions.innerHTML = '';
            data.departments.forEach(dept => {
                departmentOptions.innerHTML += `<div class="filter-option"><label><input type="checkbox" name="department" value="${escapeHtml(dept)}"> ${escapeHtml(dept)}</label></div>`;
            });
        } catch (error) { console.error('Error fetching dynamic filters:', error); }
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // --- Fetch Candidates ---
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
        
        // Send Archive Flag
        params.append('is_archived', isArchiveView ? 1 : 0);

        try {
            const response = await fetch(`get_candidates.php?${params.toString()}`);
            allCandidates = await response.json();

            const tableBody = document.getElementById('candidateTableBody');
            tableBody.innerHTML = '';

            if (!Array.isArray(allCandidates) || allCandidates.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="17" style="text-align: center; padding: 2rem;">No candidates found.</td></tr>`;
                updateButtonVisibility();
                return;
            }

            allCandidates.forEach(candidate => {
                let outreachContent = '';
                const outreachStatus = candidate.outreach_status || null;

                if (!outreachStatus && !isArchiveView) {
                     outreachContent = `
                        <div style="display:flex; gap:5px;" id="outreach-btns-${candidate.candidate_id}">
                            <button class="btn-accept" title="Schedule Interview" onclick="openOutreach('${escapeHtml(candidate.candidate_id)}', '', 'accept')"><i class="fas fa-check"></i></button>
                            <button class="btn-reject" title="Reject Candidate" onclick="openOutreach('${escapeHtml(candidate.candidate_id)}', '', 'reject')"><i class="fas fa-times"></i></button>
                        </div>
                    `;
                } else if(outreachStatus) {
                    let colorStyle = 'color: #333;';
                    if (outreachStatus.includes('Scheduled')) colorStyle = 'color: #28a745;';
                    else if (outreachStatus.includes('Rejected')) colorStyle = 'color: #dc3545;';
                    outreachContent = `<span style="font-weight:600; font-size:0.9rem; ${colorStyle}">${escapeHtml(outreachStatus)}</span>`;
                }

                // Status Logic
                let statusCell = '';
                if(isArchiveView) {
                    statusCell = `<span style="font-weight:bold; color:#666;">${escapeHtml(candidate.status)}</span>`;
                } else {
                    statusCell = `<button class="status-btn" data-candidate-id="${escapeHtml(candidate.candidate_id)}" data-current-status="${escapeHtml(candidate.status)}">${escapeHtml(candidate.status)}</button>`;
                }

                // Name and Restore Button logic (List Icon)
                let nameCell = `<span class="clickable-name" onclick="openEditCandidate(${candidate.candidate_id})">${escapeHtml(candidate.name)}</span>`;

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="checkbox" name="candidate_check" value="${escapeHtml(candidate.candidate_id)}"></td>
                    <td>${nameCell}</td>
                    <td>${escapeHtml(candidate.applied_job_position)}</td>
                    <td>${escapeHtml(candidate.department)}</td>
                    <td>${escapeHtml(candidate.applied_date)}</td>
                    <td>${escapeHtml(candidate.score_overall)}</td>
                    <td>${escapeHtml(candidate.score_education)}</td>
                    <td>${escapeHtml(candidate.score_skills)}</td>
                    <td>${escapeHtml(candidate.score_experience)}</td>
                    <td>${escapeHtml(candidate.score_language)}</td>
                    <td>${escapeHtml(candidate.score_others)}</td>
                    <td>${statusCell}</td>
                    <td>${outreachContent}</td>
                    <td><button class="btn-original" data-src="${escapeHtml(candidate.resume_original)}">Original Resume</button></td>
                    <td><button class="btn-formatted" data-id="${escapeHtml(candidate.candidate_id)}">Formatted Resume</button></td>
                    <td><button class="btn-report" data-candidate-id="${escapeHtml(candidate.candidate_id)}">Report</button></td>
                    <td>${escapeHtml(candidate.staff_in_charge || '')}</td>
                `;
                tableBody.appendChild(row);
            });

            attachRowEventListeners();
            updateButtonVisibility();

        } catch (error) {
            console.error('Error fetching candidates:', error);
            document.getElementById('candidateTableBody').innerHTML = `<tr><td colspan="17" style="text-align: center; color: red;">Error loading data.</td></tr>`;
        }
    }

    // --- Edit Candidate ---
    function openEditCandidate(id) {
        // Updated to use candidate_id
        const candidate = allCandidates.find(c => c.candidate_id == id);
        if (!candidate) return;
        document.getElementById('edit_candidate_id').value = candidate.candidate_id;
        document.getElementById('edit_name').value = candidate.name;
        document.getElementById('edit_gender').value = candidate.gender || '';
        document.getElementById('edit_email').value = candidate.email || '';
        document.getElementById('edit_contact').value = candidate.contact_number || '';
        document.getElementById('edit_address').value = candidate.address || '';
        
        // --- Configure Middle Button (Archive/Restore) ---
        const btnMiddle = document.getElementById('btnArchiveRestoreCandidate');
        const newBtnMiddle = btnMiddle.cloneNode(true);
        btnMiddle.parentNode.replaceChild(newBtnMiddle, btnMiddle);
        
        if(isArchiveView) {
            newBtnMiddle.innerText = "Restore";
            newBtnMiddle.className = "btn-middle-action btn-action-restore"; 
            newBtnMiddle.onclick = function() { restoreCandidateFromModal(candidate.candidate_id); };
        } else {
            newBtnMiddle.innerText = "Archive";
            newBtnMiddle.className = "btn-middle-action btn-action-archive"; 
            newBtnMiddle.onclick = function() { archiveCandidateFromModal(candidate.candidate_id); };
        }
        
        // --- Configure Delete Button (Permanent) ---
        const btnDelete = document.getElementById('btnDeletePermanentCandidate');
        const newBtnDelete = btnDelete.cloneNode(true);
        btnDelete.parentNode.replaceChild(newBtnDelete, btnDelete);
        
        newBtnDelete.onclick = function() { permanentDeleteCandidateFromModal(candidate.candidate_id); };
        
        document.getElementById('editCandidateModal').classList.add('visible');
    }

    document.getElementById('btnUpdateCandidate').addEventListener('click', async () => {
        const form = document.getElementById('editCandidateForm');
        const formData = new FormData(form);
        try {
            const resp = await fetch('cud_candidate.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) {
                alert(data.message);
                document.getElementById('editCandidateModal').classList.remove('visible');
                fetchCandidates();
            } else alert('Error: ' + data.message);
        } catch (e) { alert("Request failed."); }
    });

    // Archive Action (Modal)
    async function archiveCandidateFromModal(id) {
        if (!confirm("Are you sure you want to archive this candidate?")) return;
        const formData = new FormData();
        formData.append('action_type', 'delete'); // 'delete' maps to Archive in PHP
        formData.append('candidate_id', id);
        try {
            const resp = await fetch('cud_candidate.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) {
                alert(data.message);
                document.getElementById('editCandidateModal').classList.remove('visible');
                fetchCandidates();
            } else alert('Error: ' + data.message);
        } catch (e) { alert("Request failed."); }
    }

    // Restore Action (Modal)
    async function restoreCandidateFromModal(id) {
        if (!confirm("Restore this candidate to Active list?")) return;
        const formData = new FormData();
        formData.append('action_type', 'restore');
        formData.append('candidate_id', id);
        try {
            const resp = await fetch('cud_candidate.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) {
                alert(data.message);
                document.getElementById('editCandidateModal').classList.remove('visible');
                fetchCandidates(); 
            } else alert('Error: ' + data.message);
        } catch(e) { console.error(e); }
    }
    
    // Permanent Delete Action (Modal)
    async function permanentDeleteCandidateFromModal(id) {
        if (!confirm("WARNING: This will PERMANENTLY delete the candidate record. This action cannot be undone. Proceed?")) return;
        const formData = new FormData();
        formData.append('action_type', 'permanent_delete');
        formData.append('candidate_id', id);
        try {
            const resp = await fetch('cud_candidate.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) {
                alert(data.message);
                document.getElementById('editCandidateModal').classList.remove('visible');
                fetchCandidates();
            } else alert('Error: ' + data.message);
        } catch(e) { console.error(e); }
    }

    // --- Single Restore (List Icon) ---
    async function restoreCandidate(id) {
        if (!confirm("Restore this candidate to Active list?")) return;
        const formData = new FormData();
        formData.append('action_type', 'restore');
        formData.append('candidate_id', id);
        try {
            const resp = await fetch('cud_candidate.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (data.success) {
                alert(data.message);
                fetchCandidates();
            } else alert('Error: ' + data.message);
        } catch(e) { console.error(e); }
    }
    window.restoreCandidate = restoreCandidate;

    document.getElementById('btnCloseEditModal').addEventListener('click', () => document.getElementById('editCandidateModal').classList.remove('visible'));

    // --- Bulk Actions ---
    function updateButtonVisibility() {
        const checkedCount = document.querySelectorAll('input[name="candidate_check"]:checked').length;
        
        // Permanent Delete is always available if selected
        document.getElementById('permanentDeleteSelectedBtn').style.display = checkedCount > 0 ? 'inline-block' : 'none';
        
        if (isArchiveView) {
            document.getElementById('archiveSelectedBtn').style.display = 'none';
            document.getElementById('restoreSelectedBtn').style.display = checkedCount > 0 ? 'inline-block' : 'none';
        } else {
            document.getElementById('archiveSelectedBtn').style.display = checkedCount > 0 ? 'inline-block' : 'none';
            document.getElementById('restoreSelectedBtn').style.display = 'none';
        }
        
        document.getElementById('compareSelectedBtn').style.display = (checkedCount >= 2 && checkedCount <= 3) ? 'inline-block' : 'none';
    }
    
    // Bulk Archive
    document.getElementById('archiveSelectedBtn').addEventListener('click', async () => {
        const selected = Array.from(document.querySelectorAll('input[name="candidate_check"]:checked')).map(cb => cb.value);
        if (!selected.length) return;
        if (!confirm(`Archive ${selected.length} selected candidate(s)?`)) return;
        await performBulkAction(selected, 'archive');
    });

    // Bulk Restore
    document.getElementById('restoreSelectedBtn').addEventListener('click', async () => {
        const selected = Array.from(document.querySelectorAll('input[name="candidate_check"]:checked')).map(cb => cb.value);
        if (!selected.length) return;
        if (!confirm(`Restore ${selected.length} selected candidate(s)?`)) return;
        await performBulkAction(selected, 'restore');
    });
    
    // Bulk Permanent Delete
    document.getElementById('permanentDeleteSelectedBtn').addEventListener('click', async () => {
        const selected = Array.from(document.querySelectorAll('input[name="candidate_check"]:checked')).map(cb => cb.value);
        if (!selected.length) return;
        if (!confirm(`PERMANENTLY DELETE ${selected.length} selected candidate(s)? This cannot be undone.`)) return;
        await performBulkAction(selected, 'permanent_delete');
    });

    async function performBulkAction(ids, action) {
        try {
            const formData = new FormData();
            ids.forEach(id => formData.append('ids[]', id));
            formData.append('action', action);
            const resp = await fetch('delete_candidates.php', { method: 'POST', body: formData });
            const result = await resp.json();
            if (result && result.success) fetchCandidates();
            else alert('Action failed: ' + (result.message || 'Unknown error'));
        } catch (err) { console.error(err); alert('Error.'); }
    }

    // --- Resume Viewer, Status, Outreach, Event Listeners ... (Standard) ---
    function attachRowEventListeners() {
        document.querySelectorAll('input[name="candidate_check"]').forEach(cb => cb.addEventListener('change', updateButtonVisibility));
        document.querySelectorAll('.status-btn').forEach(btn => btn.addEventListener('click', onStatusButtonClick));
        document.querySelectorAll('.btn-original').forEach(btn => btn.addEventListener('click', onOpenResume));
        document.querySelectorAll('.btn-formatted').forEach(btn => btn.addEventListener('click', onViewFormatted));
        document.querySelectorAll('.btn-report').forEach(btn => btn.addEventListener('click', onReportClick));
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('input[name="candidate_check"]').forEach(cb => cb.checked = this.checked);
                updateButtonVisibility();
            });
        }
    }
    
    const resumeOverlay = document.getElementById('resumeViewerOverlay');
    const resumeIframe = document.getElementById('resumeViewerIframe');
    function onOpenResume(e) {
        const src = e.currentTarget.dataset.src;
        if (!src) { alert('No file available.'); return; }
        resumeIframe.src = src;
        resumeOverlay.classList.add('visible');
    }
    document.getElementById('closeResumeViewer').addEventListener('click', () => resumeOverlay.classList.remove('visible'));
    
    const statusOverlay = document.getElementById('statusOverlay');
    const statusSelect = document.getElementById('statusSelect');
    let currentStatusCandidateId = null;
    function onStatusButtonClick(e) {
        const btn = e.currentTarget;
        currentStatusCandidateId = btn.dataset.candidateId;
        statusSelect.value = btn.dataset.currentStatus || 'Active';
        statusOverlay.classList.add('visible');
    }
    document.getElementById('confirmStatusBtn').addEventListener('click', async () => {
        if (!currentStatusCandidateId) return;
        const formData = new FormData();
        formData.append('candidate_id', currentStatusCandidateId);
        formData.append('status', statusSelect.value);
        const resp = await fetch('update_status.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) { statusOverlay.classList.remove('visible'); fetchCandidates(); } 
        else alert('Failed to update status.');
    });
    document.getElementById('cancelStatusBtn').addEventListener('click', () => statusOverlay.classList.remove('visible'));
    
    function onReportClick(e) {
        const id = e.currentTarget.dataset.candidateId;
        window.location.href = `report.php?email=${currentEmail}&candidate_id=${encodeURIComponent(id)}`;
    }
    
    async function onViewFormatted(e) {
        const id = e.currentTarget.dataset.id;
        const candidate = allCandidates.find(c => c.candidate_id == id);
        if (!candidate) { alert('Candidate data not loaded.'); return; }
        // ... (PDF Generation Logic) ...
        const popup = document.getElementById('sendingPopup');
        popup.style.display = 'flex';
        try {
            const container = document.createElement('div');
            container.style.width = '700px'; container.style.padding = '40px'; container.style.background = '#ffffff'; container.style.color = '#333';
            container.innerHTML = `
                <div style="text-align:center; border-bottom: 2px solid #3a7c7c; padding-bottom: 20px; margin-bottom: 20px;">
                    <h1 style="color:#3a7c7c; margin:0;">${escapeHtml(candidate.name)}</h1>
                    <p>${escapeHtml(candidate.email)} | ${escapeHtml(candidate.contact_number)}</p>
                </div>
                <h3>Objective</h3><p>${escapeHtml(candidate.objective || 'N/A')}</p>
                <h3>Education</h3><p>${escapeHtml(candidate.education || 'N/A')}</p>
                <h3>Skills</h3><p>${escapeHtml(candidate.skills || 'N/A')}</p>
                <h3>Experience</h3><p>${escapeHtml(candidate.experience || 'N/A')}</p>
            `;
            document.body.appendChild(container);
            const opt = { margin: 0.5, filename: `${candidate.name}_Formatted_Resume.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' } };
            const pdfBlobUrl = await html2pdf().set(opt).from(container).output('bloburl');
            window.open(pdfBlobUrl, '_blank');
            document.body.removeChild(container);
            popup.style.display = 'none';
        } catch (err) { console.error(err); alert("Error generating PDF"); popup.style.display = 'none'; }
    }
    
    let outreachData = { id: null, email: null, action: null, meetLink: null };
    function openOutreach(id, email, action) {
        outreachData = { id, email, action };
        const cand = allCandidates.find(c => c.candidate_id == id);
        if (cand) outreachData.email = cand.email;
        document.getElementById('outreachTitle').innerText = action === 'accept' ? "Schedule Interview" : "Rejection Outreach";
        document.getElementById('dateGroup').style.display = action === 'accept' ? 'block' : 'none';
        document.getElementById('previewArea').style.display = 'none';
        document.getElementById('btnSendEmail').style.display = 'none';
        document.getElementById('btnGenerate').style.display = 'inline-block';
        document.getElementById('outreachModal').classList.add('visible');
    }
    window.openOutreach = openOutreach;
    
    document.getElementById('btnGenerate').addEventListener('click', () => {
        const date = document.getElementById('interviewDate').value;
        if (outreachData.action === 'accept' && !date) { alert("Please select a date."); return; }
        document.getElementById('aiLoading').style.display = 'block';
        const formData = new FormData();
        formData.append('candidate_id', outreachData.id);
        formData.append('action', outreachData.action);
        formData.append('date', date);
        fetch('get_email_draft.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                document.getElementById('aiLoading').style.display = 'none';
                if (data.status === 'success') {
                    document.getElementById('previewArea').style.display = 'block';
                    document.getElementById('emailSubject').value = data.subject;
                    document.getElementById('emailBody').value = data.body;
                    outreachData.meetLink = data.meet_link;
                    if (data.email_to) outreachData.email = data.email_to;
                    document.getElementById('btnGenerate').style.display = 'none';
                    document.getElementById('btnSendEmail').style.display = 'inline-block';
                } else alert(data.message);
            });
    });
    
    document.getElementById('btnSendEmail').addEventListener('click', () => {
        const popup = document.getElementById('sendingPopup');
        popup.style.display = 'flex';
        const formData = new FormData();
        formData.append('candidate_id', outreachData.id);
        formData.append('action', outreachData.action);
        formData.append('subject', document.getElementById('emailSubject').value);
        formData.append('body', document.getElementById('emailBody').value);
        formData.append('email', outreachData.email);
        if (outreachData.meetLink) formData.append('meet_link', outreachData.meetLink);
        const date = document.getElementById('interviewDate').value;
        if (date) formData.append('interview_date', date);

        fetch('send_outreach.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                popup.style.display = 'none';
                if (data.status === 'success') {
                    alert("Email sent!");
                    document.getElementById('outreachModal').classList.remove('visible');
                    fetchCandidates();
                } else alert("Failed: " + data.message);
            });
    });

    document.addEventListener('DOMContentLoaded', async () => {
        await fetchDynamicFilters();
        fetchCandidates();
        document.getElementById('searchInput').addEventListener('keypress', function(e) { if (e.which == 13) fetchCandidates(); }); 
        document.getElementById('searchInput').addEventListener('keyup', function() { if (this.value.trim() === '') fetchCandidates(); });
    });

    document.getElementById('resetFilters').addEventListener('click', () => {
        document.querySelectorAll('.filter-sidebar input[type="checkbox"]').forEach(cb => cb.checked = false);
        document.getElementById('searchInput').value = '';
        document.getElementById('scoreDropdown').value = 'All';
        fetchCandidates();
    });

    document.getElementById('scoreDropdown').addEventListener('change', fetchCandidates);
    document.getElementById('status-options').addEventListener('change', fetchCandidates);
    document.getElementById('job-position-options').addEventListener('change', fetchCandidates);
    document.getElementById('department-options').addEventListener('change', fetchCandidates);
</script>
</body>
</html>