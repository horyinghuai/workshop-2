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
        .fc-toolbar button { background-color: #3a7c7c !important; color: white !important; }
        .fc-toolbar-title { color: #3a7c7c !important; }
        .header .logout-link {position: absolute;right: 2rem;font-size: 1.5rem; text-decoration: none;color: white;}

        /* MODAL */
        #interviewModal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.55); display: none;
            justify-content: center; align-items: center; z-index: 9999;
        }
        .modal-box {
            background: white; padding: 25px; width: 500px;
            border-radius: 10px; text-align: center;
        }
        .modal-btn {
            display: block; margin: 15px 0; padding: 12px;
            border-radius: 6px; background: #3a7c7c; color: white;
            text-decoration: none; font-size: 1rem; cursor: pointer;
        }
        .modal-btn:hover { opacity: 0.8; }
        #questionDisplay {
            display: none; text-align: left; background: #f0f0f0;
            padding: 15px; max-height: 200px; overflow-y: auto;
            border-radius: 5px; margin-top: 10px; white-space: pre-wrap;
        }
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

<div id="interviewModal">
    <div class="modal-box">
        <h2 id="modalTitle"></h2>
        <a id="meetBtn" class="modal-btn" target="_blank">Start Google Meet</a>
        <a id="questionBtn" class="modal-btn">View Interview Questions</a>
        <div id="questionDisplay"></div>
        <a onclick="closeModal()" class="modal-btn" style="background:#c32121ff;">Close</a>
    </div>
</div>

<script>
function closeModal() {
    document.getElementById('interviewModal').style.display = 'none';
    document.getElementById('questionDisplay').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: 'get_interviews.php',
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            let titleText = info.event.title; 
            let meetLink = info.event.extendedProps.meet_link;
            let questions = info.event.extendedProps.questions;

            document.getElementById('modalTitle').textContent = titleText;

            let meetBtn = document.getElementById('meetBtn');
            meetBtn.style.display = meetLink ? 'block' : 'none';
            if(meetLink) meetBtn.href = meetLink;

            let questionBtn = document.getElementById('questionBtn');
            let questionDisplay = document.getElementById('questionDisplay');
            
            if(questions) {
                questionBtn.style.display = 'block';
                questionBtn.innerText = "View Interview Questions";
                questionBtn.onclick = function() {
                    if (questionDisplay.style.display === 'none') {
                        questionDisplay.innerText = questions;
                        questionDisplay.style.display = 'block';
                        questionBtn.innerText = "Hide Interview Questions";
                    } else {
                        questionDisplay.style.display = 'none';
                        questionBtn.innerText = "View Interview Questions";
                    }
                };
            } else {
                questionBtn.style.display = 'none';
            }
            questionDisplay.style.display = 'none';
            document.getElementById('interviewModal').style.display = 'flex';
        }
    });
    calendar.render();
});
</script>
</body>
</html>