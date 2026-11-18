
<?php

// GOOD TOGETHER - VOLUNTEER ATTENDANCE PAGE

session_start();
require "database.php";

date_default_timezone_set("Asia/Kuala_Lumpur");

// Check whether the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// Only organizers can access this page.
$sql = "SELECT role FROM users WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);
$statement->execute(["user_id" => $userId]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user["role"] !== "organizer") {
    http_response_code(403);
    exit("Access denied. Only organizers can manage attendance.");
}

// Get the event ID from the URL.
$rawId = $_GET["id"] ?? "";

if (!is_string($rawId)) {
    exit("Invalid event ID.");
}

$eventId = filter_var(
    $rawId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($eventId === false) {
    exit("Invalid event ID.");
}

// Create a security token for attendance forms.
if (!isset($_SESSION["attendance_token"])) {
    $_SESSION["attendance_token"] = bin2hex(random_bytes(32));
}

// =====================================
// GET THE ORGANIZER'S EVENT
// =====================================

$sql = "SELECT *
        FROM events
        WHERE event_id = :event_id
        AND organizer_id = :organizer_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "event_id" => $eventId,
    "organizer_id" => $userId
]);

$event = $statement->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    exit("Event not found or you do not own it.");
}

// =====================================
// GET REGISTERED VOLUNTEERS
// =====================================

$sql = "SELECT
            r.registration_id,
            r.registered_at,
            r.attendance_status,
            r.attendance_updated_at,
            u.full_name,
            u.email,
            u.city

        FROM registrations r

        INNER JOIN users u
            ON r.user_id = u.user_id

        WHERE r.event_id = :event_id

        ORDER BY r.registered_at ASC";

$statement = $pdo->prepare($sql);

$statement->execute([
    "event_id" => $eventId
]);

$volunteers = $statement->fetchAll(PDO::FETCH_ASSOC);

// =====================================
// CALCULATE ATTENDANCE STATISTICS
// =====================================

$total = count($volunteers);

$present = 0;
$absent = 0;
$pending = 0;

foreach ($volunteers as $volunteer) {

    if ($volunteer["attendance_status"] === "present") {

        $present++;

    } elseif ($volunteer["attendance_status"] === "absent") {

        $absent++;

    } else {

        $pending++;
    }
}

$capacity = (int)$event["capacity"];

$placesLeft = max(0, $capacity - $total);

// Show feedback after saving attendance.
$messages = [
    "saved" => "Attendance updated successfully!",
    "invalid" => "Invalid request.",
    "not_found" => "Registration not found.",
    "error" => "Unable to save attendance."
];

$noticeCode = $_GET["notice"] ?? "";

$notice = "";

if (is_string($noticeCode)) {
    $notice = $messages[$noticeCode] ?? "";
}

// Safely display text.
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

    <title>Volunteer Attendance | Good Together</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="page">

    <!-- HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>Organizer - Attendance Management</p>
    </header>

    <!-- NAVIGATION -->

<?php require "navigation.php"; ?>


    <!-- MAIN CONTENT -->
    <main class="volunteers-container">

        <h2>Volunteer Attendance Management</h2>

        <!-- EVENT INFORMATION -->
        <div class="volunteers-event-info">

            <h3><?php echo safe($event["title"]); ?></h3>

            <p>
                <strong>City:</strong>
                <?php echo safe($event["city"]); ?>
            </p>

            <p>
                <strong>Venue:</strong>
                <?php echo safe($event["venue"]); ?>
            </p>

            <p>
                <strong>Date:</strong>
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

            <p>
                <strong>Registered Volunteers:</strong>
                <?php echo $total; ?>
                / <?php echo $capacity; ?>
            </p>

            <p>
                <strong>Available Places:</strong>
                <?php echo $placesLeft; ?>
            </p>

        </div>

        <!-- ATTENDANCE STATISTICS -->
        <div class="attendance-stats">

            <div class="attendance-stat-box">
                <h3><?php echo $total; ?></h3>
                <p>Registered</p>
            </div>

            <div class="attendance-stat-box">
                <h3><?php echo $present; ?></h3>
                <p>Present</p>
            </div>

            <div class="attendance-stat-box">
                <h3><?php echo $absent; ?></h3>
                <p>Absent</p>
            </div>

            <div class="attendance-stat-box">
                <h3><?php echo $pending; ?></h3>
                <p>Not Marked</p>
            </div>

        </div>

        <!-- FEEDBACK MESSAGE -->
        <?php if ($notice !== "") { ?>

            <p class="registration-notice">
                <?php echo safe($notice); ?>
            </p>

        <?php } ?>

        <h3>Registered Volunteer List</h3>

        <!-- IF NO VOLUNTEERS -->
        <?php if ($total === 0) { ?>

            <div class="volunteers-empty">
                <p>No volunteers have registered yet.</p>
            </div>

        <?php } else { ?>

            <!-- VOLUNTEER TABLE -->
            <div class="volunteers-table-wrapper">

                <table class="volunteers-table">

                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>City</th>
                            <th>Registered On</th>
                            <th>Attendance</th>
                            <th>Update</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    $number = 1;

                    foreach ($volunteers as $volunteer) {
                    ?>

                        <?php
                        $status = $volunteer["attendance_status"];
                        ?>

                        <tr>

                            <!-- NUMBER -->
                            <td>
                                <?php echo $number; ?>
                            </td>

                            <!-- NAME -->
                            <td>
                                <?php echo safe($volunteer["full_name"]); ?>
                            </td>

                            <!-- EMAIL -->
                            <td>
                                <?php echo safe($volunteer["email"]); ?>
                            </td>

                            <!-- CITY -->
                            <td>
                                <?php echo safe($volunteer["city"]); ?>
                            </td>

                            <!-- REGISTRATION DATE -->
                            <td>
                                <?php
                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $volunteer["registered_at"]
                                    )
                                );
                                ?>
                            </td>

                            <!-- CURRENT ATTENDANCE -->
                            <td>

                                <?php
                                if ($status === "present") {
                                    echo "Present";

                                } elseif ($status === "absent") {
                                    echo "Absent";

                                } else {
                                    echo "Not Marked";
                                }
                                ?>

                                <?php if (
                                    !empty($volunteer["attendance_updated_at"])
                                ) { ?>

                                    <p class="attendance-date">

                                        Updated:

                                        <?php
                                        echo date(
                                            "d M Y, g:i A",
                                            strtotime(
                                                $volunteer["attendance_updated_at"]
                                            )
                                        );
                                        ?>

                                    </p>

                                <?php } ?>

                            </td>

                            <!-- ATTENDANCE UPDATE FORM -->
                            <td>

                                <form
                                    method="POST"
                                    action="update_attendance.php"
                                    class="attendance-form"
                                >

                                    <input
                                        type="hidden"
                                        name="event_id"
                                        value="<?php echo $eventId; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="registration_id"
                                        value="<?php echo (int)$volunteer["registration_id"]; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="attendance_token"
                                        value="<?php echo safe($_SESSION["attendance_token"]); ?>"
                                    >

                                    <select
                                        name="attendance_status"
                                        aria-label="Attendance for <?php echo safe($volunteer['full_name']); ?>"
                                    >

                                        <option
                                            value="pending"
                                            <?php if ($status === "pending") echo "selected"; ?>
                                        >
                                            Not Marked
                                        </option>

                                        <option
                                            value="present"
                                            <?php if ($status === "present") echo "selected"; ?>
                                        >
                                            Present
                                        </option>

                                        <option
                                            value="absent"
                                            <?php if ($status === "absent") echo "selected"; ?>
                                        >
                                            Absent
                                        </option>

                                    </select>

                                    <button
                                        type="submit"
                                        class="attendance-save"
                                    >
                                        Save
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php
                        $number++;
                    }
                    ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

        <a href="dashboard.php" class="dashboard-link">
            Back to Dashboard
        </a>

    </main>

    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>

</body>
</html>
