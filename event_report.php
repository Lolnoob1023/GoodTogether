
<?php

// =====================================
// GOOD TOGETHER - ATTENDANCE REPORT
// =====================================

// Start the login session.
session_start();

// Connect to MySQL.
require "database.php";

// Set Malaysia's time zone.
date_default_timezone_set("Asia/Kuala_Lumpur");

// 1. Check login.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

// 2. Check the user's account type.
$sql = "SELECT role FROM users WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "user_id" => $userId
]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

// Only organizers can view reports.
if (!$user || $user["role"] !== "organizer") {
    http_response_code(403);
    exit("Access denied. Only organizers can view reports.");
}

// 3. Get the event ID from the URL.
$rawId = $_GET["id"] ?? "";

if (!is_string($rawId)) {
    http_response_code(400);
    exit("Invalid event ID.");
}

// Check that the event ID is a positive integer.
$eventId = filter_var(
    $rawId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($eventId === false) {
    http_response_code(400);
    exit("Invalid event ID.");
}

// =====================================
// GET THE ORGANIZER'S EVENT
// =====================================

// The organizer must own this event.
$sql = "SELECT
            event_id,
            title,
            city,
            venue,
            category,
            event_date,
            capacity,
            status
        FROM events
        WHERE event_id = :event_id
        AND organizer_id = :organizer_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "event_id" => $eventId,
    "organizer_id" => $userId
]);

$event = $statement->fetch(PDO::FETCH_ASSOC);

// Stop if the organizer does not own the event.
if (!$event) {
    http_response_code(404);
    exit("Event not found or you do not own it.");
}

// =====================================
// CALCULATE ATTENDANCE STATISTICS
// =====================================

// Count total registrations and each status.
$sql = "SELECT

            COUNT(*) AS total,

            COALESCE(
                SUM(attendance_status = 'present'), 0
            ) AS present_count,

            COALESCE(
                SUM(attendance_status = 'absent'), 0
            ) AS absent_count,

            COALESCE(
                SUM(attendance_status = 'pending'), 0
            ) AS pending_count

        FROM registrations

        WHERE event_id = :event_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "event_id" => $eventId
]);

$counts = $statement->fetch(PDO::FETCH_ASSOC);

// Convert database values to numbers.
$total = (int)$counts["total"];
$present = (int)$counts["present_count"];
$absent = (int)$counts["absent_count"];
$pending = (int)$counts["pending_count"];

// Volunteers with completed attendance records.
$marked = $present + $absent;

// Calculate available places.
$capacity = (int)$event["capacity"];

$placesLeft = max(0, $capacity - $total);

// =====================================
// CALCULATE PERCENTAGES
// =====================================

// Attendance rate:
// Present / (Present + Absent) * 100
// Use NULL if nobody has been marked.
$attendanceRate = null;

if ($marked > 0) {
    $attendanceRate = round(
        ($present / $marked) * 100,
        1
    );
}

// Marking progress:
// (Present + Absent) / Registered * 100
$markingProgress = 0;

if ($total > 0) {
    $markingProgress = round(
        ($marked / $total) * 100,
        1
    );
}

// Widths for the visual report bars.
$presentBar = 0;
$absentBar = 0;
$pendingBar = 0;

if ($total > 0) {
    $presentBar = ($present / $total) * 100;
    $absentBar = ($absent / $total) * 100;
    $pendingBar = ($pending / $total) * 100;
}

// Safely display text in HTML.
function safe($text) {
    return htmlspecialchars(
        (string)$text,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Attendance Report | Good Together</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="page">

    <!-- HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>Organizer Attendance Report</p>
    </header>

    <!-- NAVIGATION -->

<?php require "navigation.php"; ?>

    <!-- REPORT CONTENT -->
    <main class="report-page">

        <h2>Event Attendance Report</h2>

        <!-- EVENT DETAILS -->
        <div class="report-event">

            <h3>
                <?php echo safe($event["title"]); ?>
            </h3>

            <p>
                <strong>City:</strong>
                <?php echo safe($event["city"]); ?>
            </p>

            <p>
                <strong>Venue:</strong>
                <?php echo safe($event["venue"]); ?>
            </p>

            <p>
                <strong>Category:</strong>
                <?php echo safe($event["category"]); ?>
            </p>

            <p>
                <strong>Event Date:</strong>
                <?php
                echo date(
                    "d M Y, g:i A",
                    strtotime($event["event_date"])
                );
                ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?php echo safe($event["status"]); ?>
            </p>

        </div>

        <!-- ATTENDANCE STATISTICS -->
        <div class="report-stats">

            <div class="report-stat">
                <strong><?php echo $total; ?></strong>
                <span>Registered</span>
            </div>

            <div class="report-stat">
                <strong><?php echo $present; ?></strong>
                <span>Present</span>
            </div>

            <div class="report-stat">
                <strong><?php echo $absent; ?></strong>
                <span>Absent</span>
            </div>

            <div class="report-stat">
                <strong><?php echo $pending; ?></strong>
                <span>Not Marked</span>
            </div>

        </div>

        <!-- ATTENDANCE SUMMARY -->
        <section class="report-section">

            <h3>Attendance Summary</h3>

            <p>
                <strong>Available Places:</strong>
                <?php echo $placesLeft; ?>
                / <?php echo $capacity; ?>
            </p>

            <p>
                <strong>Marking Progress:</strong>
                <?php echo $markingProgress; ?>%
            </p>

            <p>
                <strong>Attendance Rate:</strong>

                <?php if ($attendanceRate === null) { ?>

                    Not available yet

                <?php } else { ?>

                    <?php echo $attendanceRate; ?>%

                <?php } ?>
            </p>

            <!-- PRESENT BAR -->
            <div class="report-bar-label">
                Present: <?php echo $present; ?>
            </div>

            <div class="report-bar">
                <div
                    class="report-bar-fill present"
                    style="width: <?php echo $presentBar; ?>%"
                ></div>
            </div>

            <!-- ABSENT BAR -->
            <div class="report-bar-label">
                Absent: <?php echo $absent; ?>
            </div>

            <div class="report-bar">
                <div
                    class="report-bar-fill absent"
                    style="width: <?php echo $absentBar; ?>%"
                ></div>
            </div>

            <!-- NOT MARKED BAR -->
            <div class="report-bar-label">
                Not Marked: <?php echo $pending; ?>
            </div>

            <div class="report-bar">
                <div
                    class="report-bar-fill pending"
                    style="width: <?php echo $pendingBar; ?>%"
                ></div>
            </div>

            <p class="report-note">
                Attendance Rate is calculated using
                only volunteers marked Present or Absent.

                Marking Progress shows how many
                registrations have been marked.

                Attendance Rate is provisional until
                all volunteers have been marked.
            </p>

        </section>

        <!-- NAVIGATION BUTTONS -->
        <a
            href="event_volunteers.php?id=<?php echo $eventId; ?>"
            class="dashboard-link"
        >
            Manage Attendance
        </a>

        <a href="dashboard.php" class="dashboard-link">
            Back to Dashboard
        </a>

    </main>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>

</body>
</html>
