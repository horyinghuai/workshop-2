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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); width: 90%; max-width: 500px;
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
    .edit-modal-wrapper .form-actions { display: flex; justify-content: space-between; gap: 10px; margin-top: 25px; }
    .edit-modal-wrapper .btn-confirm { background-color: #28a745; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer; flex: 1; font-weight: bold; }
    .edit-modal-wrapper .btn-delete { background-color: #d9534f; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer; flex: 1; font-weight: bold; }
    .edit-modal-wrapper .btn-cancel { background-color: #6c757d; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer; flex: 1; font-weight: bold; }

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

    #deleteSelectedBtn { display: none; margin-left: 1rem; padding: 0.6rem 1rem; border-radius: 8px; border: none; background: #e04b4b; color: white; font-weight: 600; cursor: pointer; }

    .btn-accept { background: #28a745; color: white; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; }
    .btn-reject { background: #dc3545; color: white; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; }
    
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
                    <button type="button" class="btn-delete" id="btnDeleteCandidate">Delete</button>
                    <button type="button" class="btn-cancel" id="btnCloseEditModal">Cancel</button>
                </div>
            </form>
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

    document.querySelectorAll('.filter-title').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetOptions = document.getElementById(targetId);
            this.classList.toggle('active');
            targetOptions.classList.toggle('visible');
        });
    });

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
            allCandidates = await response.json();

            const tableBody = document.getElementById('candidateTableBody');
            tableBody.innerHTML = ''; 

            if (!Array.isArray(allCandidates) || allCandidates.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="17" style="text-align: center; padding: 2rem;">No candidates found matching your criteria.</td></tr>`;
                updateDeleteButtonVisibility();
                return;
            }

            allCandidates.forEach(candidate => {
                let outreachContent = '';
                const outreachStatus = candidate.outreach_status || null;

                if (!outreachStatus) {
                    outreachContent = `
                        <div style="display:flex; gap:5px;" id="outreach-btns-${candidate.id}">
                            <button class="btn-accept" title="Schedule Interview" onclick="openOutreach('${escapeHtml(candidate.id)}', '', 'accept')"><i class="fas fa-check"></i></button>
                            <button class="btn-reject" title="Reject Candidate" onclick="openOutreach('${escapeHtml(candidate.id)}', '', 'reject')"><i class="fas fa-times"></i></button>
                        </div>
                        <span id="outreach-label-${candidate.id}" style="display:none; font-weight:600; font-size:0.9rem;"></span>
                    `;
                } else {
                    let colorStyle = 'color: #333;';
                    if (outreachStatus.includes('Scheduled')) colorStyle = 'color: #28a745;'; 
                    else if (outreachStatus.includes('Rejected')) colorStyle = 'color: #dc3545;';
                    outreachContent = `<span style="font-weight:600; font-size:0.9rem; ${colorStyle}">${escapeHtml(outreachStatus)}</span>`;
                }

                const row = document.createElement('tr');
                const nameCell = `<span class="clickable-name" onclick="openEditCandidate(${candidate.id})">${escapeHtml(candidate.name)}</span>`;

                row.innerHTML = `
                    <td><input type="checkbox" name="candidate_check" value="${escapeHtml(candidate.id)}"></td>
                    <td>${nameCell}</td>
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
                        <button class="status-btn" data-candidate-id="${escapeHtml(candidate.id)}" data-current-status="${escapeHtml(candidate.status)}">${escapeHtml(candidate.status)}</button>
                    </td>
                    <td>${outreachContent}</td>
                    <td><button class="btn-original" data-src="${escapeHtml(candidate.resume_original)}">Original Resume</button></td>
                    <td><button class="btn-formatted" data-id="${escapeHtml(candidate.id)}">Formatted Resume</button></td>
                    <td><button class="btn-report" data-candidate-id="${escapeHtml(candidate.id)}">Report</button></td>
                    <td>${escapeHtml(candidate.staff_in_charge || '')}</td>
                `;
                tableBody.appendChild(row);
            });

            attachRowEventListeners();
            updateDeleteButtonVisibility();

        } catch (error) {
            console.error('Error fetching candidates:', error);
            document.getElementById('candidateTableBody').innerHTML = `<tr><td colspan="17" style="text-align: center; color: red;">Error loading data.</td></tr>`;
        }
    }

    function openEditCandidate(id) {
        const candidate = allCandidates.find(c => c.id == id);
        if(!candidate) return;
        document.getElementById('edit_candidate_id').value = candidate.id;
        document.getElementById('edit_name').value = candidate.name;
        document.getElementById('edit_gender').value = candidate.gender || '';
        document.getElementById('edit_email').value = candidate.email || '';
        document.getElementById('edit_contact').value = candidate.contact_number || '';
        document.getElementById('edit_address').value = candidate.address || '';
        document.getElementById('editCandidateModal').classList.add('visible');
    }

    document.getElementById('btnUpdateCandidate').addEventListener('click', async () => {
        const form = document.getElementById('editCandidateForm');
        const formData = new FormData(form);
        try {
            const resp = await fetch('cud_candidate.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if(data.success) {
                alert(data.message);
                document.getElementById('editCandidateModal').classList.remove('visible');
                fetchCandidates();
            } else alert('Error: ' + data.message);
        } catch(e) { console.error(e); alert("Request failed."); }
    });

    document.getElementById('btnDeleteCandidate').addEventListener('click', async () => {
        if(!confirm("Are you sure you want to delete this candidate?")) return;
        const id = document.getElementById('edit_candidate_id').value;
        const formData = new FormData();
        formData.append('action_type', 'delete');
        formData.append('candidate_id', id);
        try {
            const resp = await fetch('cud_candidate.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if(data.success) {
                alert(data.message);
                document.getElementById('editCandidateModal').classList.remove('visible');
                fetchCandidates(); 
            } else alert('Error: ' + data.message);
        } catch(e) { console.error(e); alert("Request failed."); }
    });

    document.getElementById('btnCloseEditModal').addEventListener('click', () => document.getElementById('editCandidateModal').classList.remove('visible'));

    function attachRowEventListeners() {
        document.querySelectorAll('input[name="candidate_check"]').forEach(cb => cb.addEventListener('change', updateDeleteButtonVisibility));
        document.querySelectorAll('.status-btn').forEach(btn => btn.addEventListener('click', onStatusButtonClick));
        document.querySelectorAll('.btn-original').forEach(btn => btn.addEventListener('click', onOpenResume));
        
        // Updated handler for Formatted Resume button to allow VIEWING first
        document.querySelectorAll('.btn-formatted').forEach(btn => btn.addEventListener('click', onViewFormatted));
        
        document.querySelectorAll('.btn-report').forEach(btn => btn.addEventListener('click', onReportClick));
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('input[name="candidate_check"]').forEach(cb => cb.checked = this.checked);
                updateDeleteButtonVisibility();
            });
        }
    }

    // --- VIEW FORMATTED RESUME (Generated from DB Data) ---
    async function onViewFormatted(e) {
        const id = e.currentTarget.dataset.id;
        const candidate = allCandidates.find(c => c.id == id);
        
        if (!candidate) { alert('Candidate data not found.'); return; }

        const popup = document.getElementById('sendingPopup');
        const progressText = document.getElementById('sendingProgress');
        popup.style.display = 'flex';
        popup.querySelector('h3').innerText = "Generating PDF...";
        progressText.innerText = "Formatting...";

        try {
            const container = document.createElement('div');
            container.style.width = '700px';
            container.style.padding = '40px';
            container.style.background = '#ffffff';
            container.style.fontFamily = 'Arial, sans-serif';
            container.style.color = '#333';
            container.style.lineHeight = '1.6';

            // Construct HTML from DB Data
            container.innerHTML = `
                <div style="text-align:center; border-bottom: 2px solid #3a7c7c; padding-bottom: 20px; margin-bottom: 20px;">
                    <h1 style="color:#3a7c7c; margin:0;">${escapeHtml(candidate.name)}</h1>
                    <p style="margin:5px 0;">${escapeHtml(candidate.email)} | ${escapeHtml(candidate.contact_number)}</p>
                    <p style="margin:5px 0;">${escapeHtml(candidate.address)}</p>
                </div>

                <h3 style="color:#3a7c7c; border-bottom: 1px solid #ddd; margin-top:20px;">Objective</h3>
                <p>${escapeHtml(candidate.objective || 'N/A').replace(/\n/g, '<br>')}</p>

                <h3 style="color:#3a7c7c; border-bottom: 1px solid #ddd; margin-top:20px;">Education</h3>
                <p>${escapeHtml(candidate.education || 'N/A').replace(/\n/g, '<br>')}</p>

                <h3 style="color:#3a7c7c; border-bottom: 1px solid #ddd; margin-top:20px;">Skills</h3>
                <p>${escapeHtml(candidate.skills || 'N/A').replace(/\n/g, '<br>')}</p>

                <h3 style="color:#3a7c7c; border-bottom: 1px solid #ddd; margin-top:20px;">Experience</h3>
                <p>${escapeHtml(candidate.experience || 'N/A').replace(/\n/g, '<br>')}</p>

                <h3 style="color:#3a7c7c; border-bottom: 1px solid #ddd; margin-top:20px;">Language</h3>
                <p>${escapeHtml(candidate.language || 'N/A').replace(/\n/g, '<br>')}</p>

                <h3 style="color:#3a7c7c; border-bottom: 1px solid #ddd; margin-top:20px;">Others</h3>
                <p>${escapeHtml(candidate.others || 'N/A').replace(/\n/g, '<br>')}</p>
            `;

            document.body.appendChild(container);

            const opt = {
                margin: 0.5,
                filename: `${candidate.name}_Formatted_Resume.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            };

            // Use 'output' with 'bloburl' to open in new tab instead of saving immediately
            const pdfBlobUrl = await html2pdf().set(opt).from(container).output('bloburl');
            window.open(pdfBlobUrl, '_blank');

            document.body.removeChild(container);
            popup.style.display = 'none';
            popup.querySelector('h3').innerText = "Processing..."; 

        } catch (err) {
            console.error(err);
            alert("Error generating PDF");
            popup.style.display = 'none';
        }
    }

    // Bulk Delete
    document.getElementById('deleteSelectedBtn').addEventListener('click', async () => {
        const selected = Array.from(document.querySelectorAll('input[name="candidate_check"]:checked')).map(cb => cb.value);
        if (!selected.length) return;
        if (!confirm(`Delete ${selected.length} selected row(s)?`)) return;
        try {
            const formData = new FormData();
            selected.forEach(id => formData.append('ids[]', id));
            const resp = await fetch('delete_candidates.php', { method: 'POST', body: formData });
            const result = await resp.json();
            if (result && result.success) fetchCandidates();
            else alert('Failed to delete selected rows.');
        } catch (err) { console.error(err); alert('Error deleting rows.'); }
    });

    function updateDeleteButtonVisibility() {
        const anyChecked = document.querySelectorAll('input[name="candidate_check"]:checked').length > 0;
        document.getElementById('deleteSelectedBtn').style.display = anyChecked ? 'inline-block' : 'none';
    }

    // Resume Viewer (Only for Original)
    const resumeOverlay = document.getElementById('resumeViewerOverlay');
    const resumeIframe = document.getElementById('resumeViewerIframe');
    function onOpenResume(e) {
        const src = e.currentTarget.dataset.src;
        if (!src) { alert('No file available.'); return; }
        resumeIframe.src = src;
        resumeOverlay.classList.add('visible');
    }
    document.getElementById('closeResumeViewer').addEventListener('click', () => resumeOverlay.classList.remove('visible'));
    resumeOverlay.addEventListener('click', (e) => { if(e.target === resumeOverlay) resumeOverlay.classList.remove('visible'); });

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
        if(res.success) {
            statusOverlay.classList.remove('visible');
            fetchCandidates();
        } else alert('Failed to update status.');
    });
    document.getElementById('cancelStatusBtn').addEventListener('click', () => statusOverlay.classList.remove('visible'));

    function onReportClick(e) {
        const id = e.currentTarget.dataset.candidateId;
        window.location.href = `report.php?email=${currentEmail}&candidate_id=${encodeURIComponent(id)}`;
    }

    let outreachData = { id: null, email: null, action: null, meetLink: null };
    function openOutreach(id, email, action) {
        outreachData = { id, email, action };
        const cand = allCandidates.find(c => c.id == id);
        if(cand) outreachData.email = cand.email;
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
            if(data.status === 'success') {
                document.getElementById('previewArea').style.display = 'block';
                document.getElementById('emailSubject').value = data.subject;
                document.getElementById('emailBody').value = data.body;
                outreachData.meetLink = data.meet_link;
                if(data.email_to) outreachData.email = data.email_to; 
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
        if(outreachData.meetLink) formData.append('meet_link', outreachData.meetLink);
        const date = document.getElementById('interviewDate').value;
        if(date) formData.append('interview_date', date);

        fetch('send_outreach.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            popup.style.display = 'none';
            if(data.status === 'success') {
                alert("Email sent!");
                document.getElementById('outreachModal').classList.remove('visible');
                fetchCandidates();
            } else alert("Failed: " + data.message);
        });
    });

    document.addEventListener('DOMContentLoaded', async () => {
        await fetchDynamicFilters();
        fetchCandidates();
    });
    document.getElementById('resetFilters').addEventListener('click', () => {
        document.querySelectorAll('.filter-sidebar input[type="checkbox"]').forEach(cb => cb.checked = false);
        document.getElementById('searchInput').value = '';
        document.getElementById('scoreDropdown').value = 'All';
        fetchCandidates();
    });
    document.getElementById('searchInput').addEventListener('input', debounce(fetchCandidates, 300));
    document.getElementById('scoreDropdown').addEventListener('change', fetchCandidates);
    document.getElementById('status-options').addEventListener('change', fetchCandidates);
    document.getElementById('job-position-options').addEventListener('change', fetchCandidates);
    document.getElementById('department-options').addEventListener('change', fetchCandidates);

    function debounce(func, wait) {
        let timeout; return function(...args) { clearTimeout(timeout); timeout = setTimeout(() => func.apply(this, args), wait); };
    }
</script>
</body>
</html>