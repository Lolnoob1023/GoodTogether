
<?php

// =====================================
// GOOD TOGETHER - MY REGISTRATIONS
// =====================================

// Start the login session.
session_start();

// Connect to MySQL.
require "database.php";

// Set Malaysia's time zone.
date_default_timezone_set("Asia/Kuala_Lumpur");

// 1. Check whether the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Get the user's ID.
$userId = (int) $_SESSION["user_id"];

// 2. Verify the account role using MySQL.
$sql = "SELECT role FROM users WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "user_id" => $userId
]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

// Only volunteer accounts can open this page.
if (!$user || $user["role"] !== "volunteer") {
    http_response_code(403);
    exit("Access denied. This page is for volunteers.");
}

// 3. Create the cancellation security token.
if (!isset($_SESSION["cancel_token"])) {
    $_SESSION["cancel_token"] = bin2hex(random_bytes(32));
}

// =====================================
// GET THE VOLUNTEER'S REGISTRATIONS
// =====================================

$sql = "SELECT
            r.registration_id,
            r.registered_at,
            r.attendance_status,
            r.attendance_updated_at,

            e.title,
            e.city,
            e.category,
            e.venue,
            e.description,
            e.event_date,
            e.status AS event_status,

            (
                e.event_date > NOW()
                AND e.status = 'published'
            ) AS can_cancel

        FROM registrations r

        INNER JOIN events e
            ON r.event_id = e.event_id

        WHERE r.user_id = :user_id

        ORDER BY e.event_date ASC";

$statement = $pdo->prepare($sql);

$statement->execute([
    "user_id" => $userId
]);

$registrations = $statement->fetchAll(PDO::FETCH_ASSOC);

// Count the current registrations.
$totalRegistrations = count($registrations);

// =====================================
// FEEDBACK MESSAGES
// =====================================

$messages = [
    "cancelled" => "Registration cancelled successfully.",
    "too_late" => "This event has already started.",
    "not_found" => "Registration not found.",
    "invalid" => "Invalid request.",
    "error" => "Something went wrong. Please try again."
];

$noticeCode = $_GET["notice"] ?? "";
$notice = "";

if (is_string($noticeCode)) {
    $notice = $messages[$noticeCode] ?? "";
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

    <title>My Registrations | Good Together</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="page">

    <!-- HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>My Volunteer Registrations</p>
    </header>

    <!-- NAVIGATION -->
 
<?php require "navigation.php"; ?>


    <!-- MAIN CONTENT -->
    <main class="registrations-page">

        <h2>My Volunteer Registrations</h2>

        <p>
            View your registered activities and
            check your attendance records.
        </p>

        <p>
            <strong>Total Registrations:</strong>
            <?php echo $totalRegistrations; ?>
        </p>

        <!-- FEEDBACK MESSAGE -->
        <?php if ($notice !== "") { ?>

            <p class="registration-notice">
                <?php echo safe($notice); ?>
            </p>

        <?php } ?>

        <!-- NO REGISTRATIONS -->
        <?php if ($totalRegistrations === 0) { ?>

            <div class="registration-card">

                <h3>No Registrations Yet</h3>

                <p>
                    You haven't joined any activities.
                    Browse volunteering opportunities
                    to get started.
                </p>

                <a
                    href="opportunities.php"
                    class="dashboard-link"
                >
                    Explore Opportunities
                </a>

            </div>

        <?php } else { ?>

            <!-- DISPLAY REGISTERED EVENTS -->
            <?php foreach ($registrations as $event) { ?>

                <?php

                // Get the attendance status.
                $attendance = $event["attendance_status"];

                // Convert status into readable text.
                $attendanceLabel = "Not Marked";

                if ($attendance === "present") {
                    $attendanceLabel = "Present";
                }

                if ($attendance === "absent") {
                    $attendanceLabel = "Absent";
                }

                // Check whether the event is upcoming.
                $isUpcoming =
                    strtotime($event["event_date"]) > time();

                ?>

                <div class="registration-card">

                    <!-- EVENT TITLE -->
                    <h3>
                        <?php echo safe($event["title"]); ?>
                    </h3>

                    <!-- EVENT DETAILS -->
                    <p>
                        <strong>City:</strong>
                        <?php echo safe($event["city"]); ?>
                    </p>

                    <p>
                        <strong>Category:</strong>
                        <?php echo safe($event["category"]); ?>
                    </p>

                    <p>
                        <strong>Venue:</strong>
                        <?php echo safe($event["venue"]); ?>
                    </p>

                    <p>
                        <strong>Date and Time:</strong>

                        <?php
                        echo date(
                            "d M Y, g:i A",
                            strtotime($event["event_date"])
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Description:</strong>
                        <?php echo safe($event["description"]); ?>
                    </p>

                    <p>
                        <strong>Event Status:</strong>
                        <?php echo safe($event["event_status"]); ?>
                    </p>

                    <p>
                        <strong>Registered On:</strong>

                        <?php
                        echo date(
                            "d M Y",
                            strtotime($event["registered_at"])
                        );
                        ?>
                    </p>

                    <!-- ATTENDANCE INFORMATION -->
                    <div class="volunteer-attendance">

                        <strong>My Attendance:</strong>

                        <span class="attendance-label <?php
                            echo safe($attendance);
                        ?>">
                            <?php echo safe($attendanceLabel); ?>
                        </span>

                        <?php if (
                            !empty($event["attendance_updated_at"])
                        ) { ?>

                            <p class="attendance-updated">

                                Last Updated:

                                <?php
                                echo date(
                                    "d M Y, g:i A",
                                    strtotime(
                                        $event["attendance_updated_at"]
                                    )
                                );
                                ?>

                            </p>

                        <?php } else { ?>

                            <p class="attendance-updated">
                                Your organizer has not recorded
                                your attendance yet.
                            </p>

                        <?php } ?>

                    </div>

                    <!-- CANCELLATION -->
                    <?php if ((int)$event["can_cancel"] === 1) { ?>

                        <form
                            method="POST"
                            action="cancel_registration.php"
                            onsubmit="return confirm('Cancel this registration?');"
                        >

                            <input
                                type="hidden"
                                name="registration_id"
                                value="<?php
                                    echo (int)$event["registration_id"];
                                ?>"
                            >

                            <input
                                type="hidden"
                                name="cancel_token"
                                value="<?php
                                    echo safe(
                                        $_SESSION["cancel_token"]
                                    );
                                ?>"
                            >

                            <button
                                type="submit"
                                class="cancel-button"
                            >
                                Cancel Registration
                            </button>

                        </form>

                    <?php } elseif (!$isUpcoming) { ?>

                        <p class="past-event">
                            This event has already started.
                        </p>

                    <?php } else { ?>

                        <p class="past-event">
                            This registration cannot be cancelled online.
                        </p>

                    <?php } ?>

                </div>

            <?php } ?>

        <?php } ?>

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
