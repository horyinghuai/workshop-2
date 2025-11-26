<?php
session_start();
// Redirect if not logged in
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
        .back-link { color: white; text-decoration: none; font-size: 1.2rem; font-weight: bold; }
        .main-container { padding: 40px; max-width: 1200px; margin: 0 auto; }
        #calendar { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .fc-event { cursor: pointer; }

        .fc-toolbar button {
            background-color: #3a7c7c !important;
            color: white !important;
        }

        .fc-toolbar-title {
            color: #3a7c7c !important;
        }

        .fc-timegrid-now-indicator {
            background-color: #9fc2c6 !important;
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="dashboard.php?email=<?php echo urlencode($currentEmail); ?>" class="back-link"><i class="fas fa-chevron-left"></i> Back</a>
        <h1 style="flex-grow:1; text-align:center; margin:0;">Interview Calendar</h1>
    </header>

    <div class="main-container">
        <div id='calendar'></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: 'get_interviews.php', // Fetch from PHP
                eventClick: function(info) {
                    info.jsEvent.preventDefault(); // don't let the browser navigate
                    if (info.event.url) {
                        window.open(info.event.url, "_blank");
                    }
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>