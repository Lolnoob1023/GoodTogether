
<?php

// ======================================
// GOOD TOGETHER - ADMIN EVENT MODERATION
// ======================================

session_start();
require "database.php";

date_default_timezone_set("Asia/Kuala_Lumpur");

// 1. Check login.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

// 2. Check the account role in MySQL.
$sql = "SELECT role FROM users WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);
$statement->execute(["user_id" => $userId]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

// Only Admin accounts can open this page.
if (!$user || $user["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Administrators only.");
}

// 3. Create a security token for moderation forms.
if (!isset($_SESSION["admin_event_token"])) {
    $_SESSION["admin_event_token"] =
        bin2hex(random_bytes(32));
}

// ======================================
// GET ALL PLATFORM EVENTS
// ======================================

// LEFT JOIN gets the organizer's name.
// The registration count helps protect
// events with volunteers already registered.
$sql = "SELECT
            e.event_id,
            e.title,
            e.city,
            e.event_date,
            e.status,

            COALESCE(
                u.full_name,
                'Unassigned'
            ) AS organizer_name,

            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.event_id = e.event_id
            ) AS registered_count

        FROM events e

        LEFT JOIN users u
            ON e.organizer_id = u.user_id

        ORDER BY e.event_id DESC

        LIMIT 100";

$statement = $pdo->query($sql);

$events = $statement->fetchAll(PDO::FETCH_ASSOC);

// ======================================
// GET EVENT STATISTICS
// ======================================

$sql = "SELECT
            COUNT(*) AS total_events,

            COALESCE(
                SUM(status = 'published'), 0
            ) AS published_events,

            COALESCE(
                SUM(status = 'hidden'), 0
            ) AS hidden_events

        FROM events";

$statement = $pdo->query($sql);
$counts = $statement->fetch(PDO::FETCH_ASSOC);

$totalEvents = (int)$counts["total_events"];
$publishedEvents = (int)$counts["published_events"];
$hiddenEvents = (int)$counts["hidden_events"];

// ======================================
// FEEDBACK MESSAGES
// ======================================

$messages = [
    "hidden" => "Event hidden successfully.",
    "restored" => "Event restored successfully.",
    "blocked" => "Cannot hide an event with registered volunteers.",
    "expired" => "A past event cannot be restored.",
    "not_found" => "Event not found.",
    "invalid" => "Invalid request.",
    "error" => "Unable to update event. Please try again."
];

$noticeCode = $_GET["notice"] ?? "";
$notice = "";

if (is_string($noticeCode)) {
    $notice = $messages[$noticeCode] ?? "";
}

// Function for safe HTML output.
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

    <title>Event Moderation | Good Together</title>

    <link rel="stylesheet" href="style.css">

    <!-- Styling for this Admin page -->
    <style>

        .moderation-page {
            max-width: 1100px;
            margin: 30px auto;
            padding: 25px;
        }

        .moderation-page h2,
        .moderation-page h3 {
            color: #344b3c;
            font-family: Georgia, serif;
        }

        .moderation-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
        }

        .moderation-stat {
            padding: 20px;
            border: 1px solid #ccc;
            background: #fffdf7;
            text-align: center;
        }

        .moderation-stat strong {
            display: block;
            font-size: 28px;
            color: #344b3c;
        }

        .moderation-stat span {
            display: block;
            font-size: 13px;
            margin-top: 8px;
        }

        .moderation-table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        .moderation-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
            background: white;
        }

        .moderation-table th {
            background: #344b3c;
            color: white;
            padding: 12px;
            border: 1px solid #ccc;
            text-align: left;
        }

        .moderation-table td {
            padding: 12px;
            border: 1px solid #ccc;
            vertical-align: middle;
            overflow-wrap: anywhere;
        }

        .moderation-table tr:nth-child(even) {
            background: #f6f0e3;
        }

        .moderation-table form {
            margin: 0;
        }

        .moderation-button {
            padding: 10px 15px;
            border: none;
            color: white;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
        }

        .hide-button {
            background: #a76542;
        }

        .restore-button {
            background: #344b3c;
        }

        .moderation-note {
            color: #825438;
            font-size: 12px;
            line-height: 1.5;
        }

        .moderation-message {
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #aaa;
            background: #e3eee3;
            color: #27613e;
        }

        @media (max-width: 650px) {
            .moderation-stats {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <!-- HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>Administrator - Event Moderation</p>
    </header>

    <!-- NAVIGATION -->

<?php require "navigation.php"; ?>


    <!-- MAIN CONTENT -->
    <main class="moderation-page">

        <h2>Platform Event Moderation</h2>

        <p>
            Review events created on Good Together.
            Hide inappropriate listings or restore
            previously hidden events.
        </p>

        <!-- STATISTICS -->
        <div class="moderation-stats">

            <div class="moderation-stat">
                <strong><?php echo $totalEvents; ?></strong>
                <span>Total Events</span>
            </div>

            <div class="moderation-stat">
                <strong><?php echo $publishedEvents; ?></strong>
                <span>Published</span>
            </div>

            <div class="moderation-stat">
                <strong><?php echo $hiddenEvents; ?></strong>
                <span>Hidden</span>
            </div>

        </div>

        <!-- FEEDBACK -->
        <?php if ($notice !== "") { ?>

            <p class="moderation-message">
                <?php echo safe($notice); ?>
            </p>

        <?php } ?>

        <h3>Event Listings</h3>

        <p>
            Displaying up to 100 recent events.
            Events with existing registrations
            are protected from being hidden.
        </p>

        <div class="moderation-table-wrapper">

            <table class="moderation-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Event Title</th>
                        <th>Organizer</th>
                        <th>City</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($events) === 0) { ?>

                    <tr>
                        <td colspan="8">
                            No events found.
                        </td>
                    </tr>

                <?php } else { ?>

                    <?php foreach ($events as $event) { ?>

                        <?php

                        $eventId = (int)$event["event_id"];

                        $registered =
                            (int)$event["registered_count"];

                        $isFuture =
                            strtotime($event["event_date"]) > time();

                        ?>

                        <tr>

                            <td>
                                <?php echo $eventId; ?>
                            </td>

                            <td>
                                <?php echo safe($event["title"]); ?>
                            </td>

                            <td>
                                <?php
                                echo safe($event["organizer_name"]);
                                ?>
                            </td>

                            <td>
                                <?php echo safe($event["city"]); ?>
                            </td>

                            <td>
                                <?php
                                echo date(
                                    "d M Y, g:i A",
                                    strtotime($event["event_date"])
                                );
                                ?>
                            </td>

                            <td>
                                <?php echo safe($event["status"]); ?>
                            </td>

                            <td>
                                <?php echo $registered; ?>
                            </td>

                            <!-- MODERATION ACTION -->
                            <td>

                                <?php if (
                                    $event["status"] === "published"
                                ) { ?>

                                    <?php if ($registered === 0) { ?>

                                        <form
                                            method="POST"
                                            action="admin_event_action.php"
                                            onsubmit="return confirm('Hide this event from the public homepage?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="event_id"
                                                value="<?php echo $eventId; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="hide"
                                            >

                                            <input
                                                type="hidden"
                                                name="admin_event_token"
                                                value="<?php
                                                echo safe(
                                                    $_SESSION[
                                                        'admin_event_token'
                                                    ]
                                                );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="moderation-button hide-button"
                                            >
                                                Hide Event
                                            </button>

                                        </form>

                                    <?php } else { ?>

                                        <span class="moderation-note">
                                            Protected: volunteers registered
                                        </span>

                                    <?php } ?>

                                <?php } elseif (
                                    $event["status"] === "hidden"
                                ) { ?>

                                    <?php if ($isFuture) { ?>

                                        <form
                                            method="POST"
                                            action="admin_event_action.php"
                                        >

                                            <input
                                                type="hidden"
                                                name="event_id"
                                                value="<?php echo $eventId; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="restore"
                                            >

                                            <input
                                                type="hidden"
                                                name="admin_event_token"
                                                value="<?php
                                                echo safe(
                                                    $_SESSION[
                                                        'admin_event_token'
                                                    ]
                                                );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="moderation-button restore-button"
                                            >
                                                Restore Event
                                            </button>

                                        </form>

                                    <?php } else { ?>

                                        <span class="moderation-note">
                                            Past event
                                        </span>

                                    <?php } ?>

                                <?php } else { ?>

                                    <span class="moderation-note">
                                        No action available
                                    </span>

                                <?php } ?>

                            </td>

                        </tr>

                    <?php } ?>

                <?php } ?>

                </tbody>

            </table>

        </div>

        <p class="moderation-note">
            Hiding an event does not delete it from MySQL.
            It prevents the event from appearing on the
            public homepage.
        </p>

        <a href="dashboard.php" class="dashboard-link">
            Back to Admin Dashboard
        </a>

    </main>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>

</body>
</html>
