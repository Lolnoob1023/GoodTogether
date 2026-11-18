
<?php

// ==========================================
// GOOD TOGETHER - COMPLETE MEMBER DASHBOARD
// Volunteer, Organizer and Administrator
// ==========================================

// Start the user's session.
session_start();

// Connect to MySQL.
require "database.php";

// Use Malaysia's time zone.
date_default_timezone_set("Asia/Kuala_Lumpur");


// ==========================================
// 1. CHECK WHETHER THE USER IS LOGGED IN
// ==========================================

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Get the logged-in user's ID.
$userId = (int) $_SESSION["user_id"];

// Get the user's latest details from MySQL.
$sql = "SELECT full_name, role, account_status
        FROM users
        WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "user_id" => $userId
]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

// Redirect if the account no longer exists
// or is suspended.
if (!$user || $user["account_status"] !== "active") {

    $_SESSION = [];
    session_destroy();

    header("Location: login.php?account=inactive");
    exit;
}

// Save information into variables.
$name = $user["full_name"];
$role = $user["role"];

// Keep session information updated.
$_SESSION["user_name"] = $name;
$_SESSION["user_role"] = $role;


// ==========================================
// 2. SECURITY TOKENS
// ==========================================

// Logout security token.
if (!isset($_SESSION["logout_token"])) {

    $_SESSION["logout_token"] = bin2hex(
        random_bytes(32)
    );
}

// Delete Event security token.
if (!isset($_SESSION["delete_event_token"])) {

    $_SESSION["delete_event_token"] = bin2hex(
        random_bytes(32)
    );
}


// ==========================================
// 3. SAFE HTML OUTPUT
// ==========================================

// Prevent unsafe HTML from being displayed.
function safe($text) {

    return htmlspecialchars(
        (string) $text,
        ENT_QUOTES,
        "UTF-8"
    );
}


// ==========================================
// 4. VOLUNTEER DASHBOARD DATA
// ==========================================

$myRegistrations = [];
$upcomingEvents = [];

$joinedCount = 0;
$upcomingCount = 0;
$presentCount = 0;
$absentCount = 0;
$pendingCount = 0;

if ($role === "volunteer") {

    // Get the volunteer's registered events.
    $sql = "SELECT
                r.registration_id,
                r.attendance_status,
                e.title,
                e.city,
                e.event_date,
                e.status AS event_status

            FROM registrations r

            INNER JOIN events e
                ON r.event_id = e.event_id

            WHERE r.user_id = :user_id

            ORDER BY e.event_date ASC";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "user_id" => $userId
    ]);

    $myRegistrations = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    // Count all current registrations.
    $joinedCount = count($myRegistrations);

    foreach ($myRegistrations as $registration) {

        $attendance = $registration["attendance_status"];

        // Count attendance statuses.
        if ($attendance === "present") {

            $presentCount++;

        } elseif ($attendance === "absent") {

            $absentCount++;

        } else {

            $pendingCount++;
        }

        // Check whether the event is upcoming.
        $isFuture = (
            strtotime($registration["event_date"]) > time()
        );

        if (
            $isFuture &&
            $registration["event_status"] === "published"
        ) {

            $upcomingCount++;

            $upcomingEvents[] = $registration;
        }
    }
}


// ==========================================
// 5. ORGANIZER DASHBOARD DATA
// ==========================================

$myEvents = [];

$totalEvents = 0;
$totalCapacity = 0;
$totalRegistrations = 0;

if ($role === "organizer") {

    // Get events created by this organizer.
    $sql = "SELECT
                e.event_id,
                e.title,
                e.city,
                e.event_date,
                e.capacity,
                e.status,

                (e.event_date > NOW()) AS is_future,

                (
                    SELECT COUNT(*)
                    FROM registrations r
                    WHERE r.event_id = e.event_id
                ) AS registered_count

            FROM events e

            WHERE e.organizer_id = :organizer_id

            ORDER BY e.event_date DESC";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "organizer_id" => $userId
    ]);

    $myEvents = $statement->fetchAll(PDO::FETCH_ASSOC);

    // Calculate organizer statistics.
    $totalEvents = count($myEvents);

    foreach ($myEvents as $event) {

        $totalCapacity += (int) $event["capacity"];

        $totalRegistrations +=
            (int) $event["registered_count"];
    }
}


// ==========================================
// 6. ADMINISTRATOR DASHBOARD DATA
// ==========================================

// Default statistics.
$adminTotalUsers = 0;
$adminVolunteers = 0;
$adminOrganizers = 0;
$adminAdmins = 0;

$adminTotalEvents = 0;
$adminTotalRegistrations = 0;

// Admin table records.
$adminUsers = [];
$adminEvents = [];

if ($role === "admin") {

    // --------------------------------------
    // COUNT USERS BY ROLE
    // --------------------------------------

    $sql = "SELECT role, COUNT(*) AS total
            FROM users
            GROUP BY role";

    $statement = $pdo->query($sql);

    $roleCounts = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($roleCounts as $row) {

        $count = (int) $row["total"];

        $adminTotalUsers += $count;

        if ($row["role"] === "volunteer") {

            $adminVolunteers = $count;

        } elseif ($row["role"] === "organizer") {

            $adminOrganizers = $count;

        } elseif ($row["role"] === "admin") {

            $adminAdmins = $count;
        }
    }

    // --------------------------------------
    // COUNT ALL EVENTS
    // --------------------------------------

    $sql = "SELECT COUNT(*) FROM events";

    $statement = $pdo->query($sql);

    $adminTotalEvents = (int) $statement->fetchColumn();

    // --------------------------------------
    // COUNT ALL REGISTRATIONS
    // --------------------------------------

    $sql = "SELECT COUNT(*) FROM registrations";

    $statement = $pdo->query($sql);

    $adminTotalRegistrations =
        (int) $statement->fetchColumn();

    // --------------------------------------
    // GET REGISTERED USER ACCOUNTS
    // --------------------------------------

    $sql = "SELECT
                user_id,
                full_name,
                email,
                city,
                role,
                account_status,
                created_at

            FROM users

            ORDER BY user_id DESC

            LIMIT 50";

    $statement = $pdo->query($sql);

    $adminUsers = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    // --------------------------------------
    // GET PLATFORM EVENTS
    // --------------------------------------

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

            LIMIT 50";

    $statement = $pdo->query($sql);

    $adminEvents = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );
}


// ==========================================
// 7. FEEDBACK MESSAGES
// ==========================================

$messages = [

    "updated" => "Event updated successfully!",

    "deleted" => "Event deleted successfully!",

    "blocked" =>
        "Cannot delete an event with registered volunteers.",

    "not_found" =>
        "Event not found or you do not own it.",

    "invalid" => "Invalid request.",

    "error" => "Something went wrong. Please try again."

];

$notice = "";

$noticeCode = $_GET["notice"] ?? "";

if (is_string($noticeCode)) {

    $notice = $messages[$noticeCode] ?? "";
}

// Message after creating an event.
if (($_GET["created"] ?? "") === "1") {

    $notice = "Your event was published successfully!";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Good Together</title>

    <!-- Main website CSS -->
    <link rel="stylesheet" href="style.css">

    <!-- Additional dashboard styling -->
    <style>

        /* Wider dashboard for Admin tables */
        .dashboard-box {
            max-width: 1100px;
        }

        /* Admin statistics layout */
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
        }

        .admin-stat {
            background-color: #f6f0e3;
            border: 1px solid #ccc;
            padding: 20px;
            text-align: center;
        }

        .admin-stat h3 {
            color: #344b3c;
            font-size: 30px;
            margin: 0;
        }

        .admin-stat p {
            color: #666;
            font-size: 13px;
        }

        /* Admin table sections */
        .admin-section {
            margin-top: 35px;
            margin-bottom: 35px;
        }

        .admin-table-wrapper {
            width: 100%;
            overflow-x: auto;
            margin-top: 15px;
        }

        .admin-table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
            background-color: white;
        }

        .admin-table th {
            background-color: #344b3c;
            color: white;
            padding: 12px;
            border: 1px solid #ccc;
            text-align: left;
        }

        .admin-table td {
            padding: 12px;
            border: 1px solid #ccc;
            overflow-wrap: anywhere;
        }

        .admin-table tbody tr:nth-child(even) {
            background-color: #f6f0e3;
        }

        /* Active and suspended account labels */
        .status-active {
            color: #27613e;
            font-weight: bold;
        }

        .status-suspended {
            color: #974c31;
            font-weight: bold;
        }

        /* Mobile adjustments */
        @media (max-width: 650px) {

            .admin-stats {
                grid-template-columns: repeat(2, 1fr);
            }

        }

    </style>

</head>

<body>

<div class="page">

    <!-- =================================
         WEBSITE HEADER
         ================================= -->

    <header>

        <h1>Good Together</h1>

        <p>Member Dashboard</p>

    </header>


    <!-- =================================
         NAVIGATION BAR
         ================================= -->


<?php require "navigation.php"; ?>



    <!-- =================================
         MAIN DASHBOARD
         ================================= -->

    <main class="dashboard-container">

        <div class="dashboard-box">

            <h2>
                Welcome, <?php echo safe($name); ?>!
            </h2>

            <p>

                <strong>Account Type:</strong>

                <?php echo safe($role); ?>

            </p>


            <!-- =============================
                 VOLUNTEER DASHBOARD
                 ============================= -->

            <?php if ($role === "volunteer") { ?>

                <h3>Volunteer Dashboard</h3>

                <p>
                    Track your volunteering activities
                    and attendance records.
                </p>

                <!-- VOLUNTEER STATISTICS -->

                <div class="volunteer-stats">

                    <div class="volunteer-stat">

                        <h3><?php echo $joinedCount; ?></h3>

                        <p>Registered Events</p>

                    </div>

                    <div class="volunteer-stat">

                        <h3><?php echo $upcomingCount; ?></h3>

                        <p>Upcoming Events</p>

                    </div>

                    <div class="volunteer-stat">

                        <h3><?php echo $presentCount; ?></h3>

                        <p>Present</p>

                    </div>

                    <div class="volunteer-stat">

                        <h3><?php echo $absentCount; ?></h3>

                        <p>Absent</p>

                    </div>

                    <div class="volunteer-stat">

                        <h3><?php echo $pendingCount; ?></h3>

                        <p>Not Marked</p>

                    </div>

                </div>


                <!-- VOLUNTEER BUTTONS -->

                <div class="dashboard-actions">

                    <a href="opportunities.php"
                       class="dashboard-link">
                        Explore Opportunities
                    </a>

                    <a href="my_registrations.php"
                       class="dashboard-link">
                        My Registrations
                    </a>

                </div>


                <!-- UPCOMING EVENTS -->

                <h3>My Upcoming Events</h3>

                <?php if ($upcomingCount === 0) { ?>

                    <p>
                        You have no upcoming registered events.
                    </p>

                <?php } else { ?>

                    <?php

                    $displayEvents = array_slice(
                        $upcomingEvents,
                        0,
                        3
                    );

                    ?>

                    <?php foreach ($displayEvents as $event) { ?>

                        <div class="volunteer-event">

                            <h4>
                                <?php echo safe($event["title"]); ?>
                            </h4>

                            <p>

                                <strong>City:</strong>

                                <?php echo safe($event["city"]); ?>

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

                                <strong>My Attendance:</strong>

                                <?php

                                $status = $event["attendance_status"];

                                if ($status === "present") {

                                    echo "Present";

                                } elseif ($status === "absent") {

                                    echo "Absent";

                                } else {

                                    echo "Not Marked";
                                }

                                ?>

                            </p>

                        </div>

                    <?php } ?>

                <?php } ?>

                <p class="dashboard-note">

                    Attendance is recorded by event organizers.
                    Not Marked means the organizer
                    has not recorded attendance yet.

                </p>


            <!-- =============================
                 ORGANIZER DASHBOARD
                 ============================= -->

            <?php } elseif ($role === "organizer") { ?>

                <h3>Organizer Dashboard</h3>

                <p>

                    Create events, manage volunteers
                    and view attendance reports.

                </p>


                <!-- FEEDBACK -->

                <?php if ($notice !== "") { ?>

                    <p class="registration-notice">

                        <?php echo safe($notice); ?>

                    </p>

                <?php } ?>


                <!-- ORGANIZER STATISTICS -->

                <div class="organizer-stats">

                    <div class="stat-box">

                        <h3><?php echo $totalEvents; ?></h3>

                        <p>My Events</p>

                    </div>

                    <div class="stat-box">

                        <h3><?php echo $totalCapacity; ?></h3>

                        <p>Total Event Capacity</p>

                    </div>

                    <div class="stat-box">

                        <h3><?php echo $totalRegistrations; ?></h3>

                        <p>Total Registrations</p>

                    </div>

                </div>


                <!-- CREATE EVENT BUTTON -->

                <a href="create_event.php"
                   class="dashboard-link">

                    + Create New Event

                </a>


                <h3>My Created Events</h3>


                <?php if ($totalEvents === 0) { ?>

                    <p>
                        You have not created any events yet.
                    </p>

                <?php } else { ?>

                    <?php foreach ($myEvents as $event) { ?>

                        <?php

                        $eventId = (int) $event["event_id"];

                        $registered =
                            (int) $event["registered_count"];

                        $canEdit =
                            $event["status"] === "published"
                            && (int) $event["is_future"] === 1;

                        ?>

                        <div class="organizer-event">

                            <h3>
                                <?php echo safe($event["title"]); ?>
                            </h3>

                            <p>

                                <strong>City:</strong>

                                <?php echo safe($event["city"]); ?>

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

                                <?php echo $registered; ?>

                                /

                                <?php echo (int) $event["capacity"]; ?>

                            </p>


                            <!-- ORGANIZER EVENT BUTTONS -->

                            <div class="event-actions">

<!-- VIEW EVENT DETAILS -->
<a
    href="event_details.php?id=<?php echo (int) $event['event_id']; ?>"
    class="dashboard-link"
>
    View Event
</a>

                                <!-- VIEW VOLUNTEERS -->

                                <a
                                    href="event_volunteers.php?id=<?php echo $eventId; ?>"
                                    class="dashboard-link"
                                >
                                    View Volunteers
                                </a>


                                <!-- VIEW REPORT -->

                                <a
                                    href="event_report.php?id=<?php echo $eventId; ?>"
                                    class="dashboard-link"
                                >
                                    View Report
                                </a>


                                <!-- EDIT EVENT -->

                                <?php if ($canEdit) { ?>

                                    <a
                                        href="edit_event.php?id=<?php echo $eventId; ?>"
                                        class="edit-event-link"
                                    >
                                        Edit Event
                                    </a>

                                <?php } ?>


                                <!-- DELETE EVENT -->

                                <?php if ($registered === 0) { ?>

                                    <form
                                        method="POST"
                                        action="delete_event.php"
                                        onsubmit="return confirm('Delete this event permanently?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="event_id"
                                            value="<?php echo $eventId; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="delete_event_token"
                                            value="<?php echo safe($_SESSION['delete_event_token']); ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-event-button"
                                        >
                                            Delete Event
                                        </button>

                                    </form>

                                <?php } else { ?>

                                    <p class="delete-help">

                                        Cannot delete:
                                        volunteers already registered.

                                    </p>

                                <?php } ?>

                            </div>

                        </div>

                    <?php } ?>

                <?php } ?>


            <!-- =============================
                 ADMINISTRATOR DASHBOARD
                 ============================= -->

            <?php } elseif ($role === "admin") { ?>

                <h3>Administrator Dashboard</h3>

                <p>

                    Monitor user accounts, event organizers,
                    volunteering activities and registrations.

                </p>


                <!-- ADMIN STATISTICS -->

                <div class="admin-stats">

                    <div class="admin-stat">

                        <h3><?php echo $adminTotalUsers; ?></h3>

                        <p>Total Users</p>

                    </div>

                    <div class="admin-stat">

                        <h3><?php echo $adminVolunteers; ?></h3>

                        <p>Volunteers</p>

                    </div>

                    <div class="admin-stat">

                        <h3><?php echo $adminOrganizers; ?></h3>

                        <p>Organizers</p>

                    </div>

                    <div class="admin-stat">

                        <h3><?php echo $adminAdmins; ?></h3>

                        <p>Administrators</p>

                    </div>

                    <div class="admin-stat">

                        <h3><?php echo $adminTotalEvents; ?></h3>

                        <p>Total Events</p>

                    </div>

                    <div class="admin-stat">

                        <h3>
                            <?php echo $adminTotalRegistrations; ?>
                        </h3>

                        <p>Registrations</p>

                    </div>

                </div>


                <!-- QUICK ADMIN LINKS -->

                <div class="dashboard-actions">

                    <a href="admin_users.php"
                       class="dashboard-link">

                        Manage User Accounts

                    </a>

                    <a href="admin_moderation.php"
                       class="dashboard-link">

                        Moderate Events

                    </a>

                </div>


                <!-- =========================
                     USER ACCOUNT TABLE
                     ========================= -->

                <section
                    id="admin-users"
                    class="admin-section"
                >

                    <h3>Registered User Accounts</h3>

                    <p>
                        Displaying the latest 50 accounts.
                    </p>

                    <div class="admin-table-wrapper">

                        <table class="admin-table">

                            <thead>

                                <tr>

                                    <th>User ID</th>

                                    <th>Full Name</th>

                                    <th>Email</th>

                                    <th>City</th>

                                    <th>Role</th>

                                    <th>Status</th>

                                    <th>Joined</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($adminUsers as $account) { ?>

                                <tr>

                                    <td>
                                        <?php
                                        echo (int) $account["user_id"];
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo safe($account["full_name"]);
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo safe($account["email"]);
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo safe($account["city"]);
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo safe($account["role"]);
                                        ?>
                                    </td>

                                    <!-- ACCOUNT STATUS -->
                                    <td>

                                        <?php if (
                                            $account["account_status"] === "active"
                                        ) { ?>

                                            <span class="status-active">
                                                Active
                                            </span>

                                        <?php } else { ?>

                                            <span class="status-suspended">
                                                Suspended
                                            </span>

                                        <?php } ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime($account["created_at"])
                                        );
                                        ?>

                                    </td>

                                </tr>

                            <?php } ?>

                            </tbody>

                        </table>

                    </div>

                </section>


                <!-- =========================
                     PLATFORM EVENT TABLE
                     ========================= -->

                <section
                    id="admin-events"
                    class="admin-section"
                >

                    <h3>Platform Events</h3>

                    <p>
                        Displaying the latest 50 events
                        from all organizers.
                    </p>

                    <div class="admin-table-wrapper">

                        <table class="admin-table">

                            <thead>

                                <tr>

                                    <th>Event ID</th>

                                    <th>Event Title</th>

                                    <th>Organizer</th>

                                    <th>City</th>

                                    <th>Event Date</th>

                                    <th>Status</th>

                                    <th>Volunteers</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($adminEvents as $event) { ?>

                                <tr>

                                    <td>
                                        <?php
                                        echo (int) $event["event_id"];
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo safe($event["title"]);
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo safe($event["organizer_name"]);
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo safe($event["city"]);
                                        ?>
                                    </td>

                                    <td>

                                        <?php
                                        echo date(
                                            "d M Y",
                                            strtotime($event["event_date"])
                                        );
                                        ?>

                                    </td>

                                    <td>
                                        <?php
                                        echo safe($event["status"]);
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo (int) $event["registered_count"];
                                        ?>
                                    </td>

                                </tr>

                            <?php } ?>

                            </tbody>

                        </table>

                    </div>

                </section>


                <p class="dashboard-note">

                    Use Manage Users to suspend or restore accounts.

                    Use Event Moderation to hide or restore
                    eligible event listings.

                </p>

            <?php } ?>


            <!-- =================================
                 LOGOUT BUTTON
                 ================================= -->


                <input
                    type="hidden"
                    name="logout_token"
                    value="<?php echo safe($_SESSION['logout_token']); ?>"
                >

                <button
                    type="submit"
                    class="logout-button"
                >
                    Logout
                </button>

            </form>

        </div>

    </main>


    <!-- FOOTER -->

    <footer>

        <p>&copy; 2026 Good Together</p>

    </footer>

</div>

</body>
</html>
