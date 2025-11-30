<?php 
session_start();
if (!isset($_GET['email'])) { header('Location: login.php'); exit(); }
$currentEmail = $_GET['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resume Reader | Interview Calendar</title>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; margin: 0; }
        .header { background: #3a7c7c; padding: 1.25rem 2rem; display: flex; align-items: center; color: white; }
        .back-link { color: white; text-decoration: none; font-size: 1.5rem; font-weight: bold; }
        .main-container { padding: 40px; max-width: 1200px; margin: 0 auto; }
        #calendar { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .fc-event { cursor: pointer; }
        .header .logout-link {position: absolute;right: 2rem;font-size: 1.5rem; text-decoration: none;color: white;}
        .logout-link:hover {text-decoration: underline;}

        /* MODAL */
        #interviewModal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .modal-box {
            background: white;
            padding: 25px;
            width: 400px;
            border-radius: 10px;
            text-align: center;
        }
        .modal-btn {
            display: block;
            margin: 15px 0;
            padding: 12px;
            border-radius: 6px;
            background: #3a7c7c;
            color: white;
            text-decoration: none;
            font-size: 1rem;
        }
        .modal-btn:hover { opacity: 0.8; }
    </style>
</head>
<body>

<header class="header">
    <a href="dashboard.php?email=<?php echo urlencode($currentEmail); ?>" class="back-link">
        <i class="fas fa-chevron-left"></i> Back
    </a>
    <h1 style="flex-grow:1; text-align:center; margin:0;">Interview Calendar</h1>
    <a href="logout.php" class="logout-link">Log Out</a>
</header>

<div class="main-container">
    <div id='calendar'></div>
</div>

<!-- MODAL -->
<div id="interviewModal">
    <div class="modal-box">
        <h2 id="modalTitle"></h2>
        <a id="meetBtn" class="modal-btn" target="_blank">Start Google Meet</a>
        <a id="questionBtn" class="modal-btn">View Interview Questions</a>
        <a onclick="closeModal()" class="modal-btn" style="background:#999;">Cancel</a>
    </div>
</div>

<script>
function closeModal() {
    document.getElementById('interviewModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: 'get_interviews.php',

        eventClick: function(info) {
            info.jsEvent.preventDefault();

            // Extract event data
            let candidateId = info.event.title.replace("Interview with Candidate ", "");
            let meetLink = info.event.extendedProps.meet_link;
            let questions = info.event.extendedProps.questions;

            // Set modal title
            document.getElementById('modalTitle').textContent = "Candidate ID: " + candidateId;

            // Google Meet button
            let meetBtn = document.getElementById('meetBtn');
            if(meetLink) {
                meetBtn.style.display = 'block';
                meetBtn.href = meetLink;
            } else {
                meetBtn.style.display = 'none';
            }

            // Interview Questions button
            let questionBtn = document.getElementById('questionBtn');
            if(questions) {
                questionBtn.style.display = 'block';
                questionBtn.onclick = function() {
                    alert("Interview Questions:\n\n" + questions);
                };
            } else {
                questionBtn.style.display = 'none';
            }

            // Show modal
            document.getElementById('interviewModal').style.display = 'flex';
        }
    });

    calendar.render();
});
</script>

</body>
</html>
