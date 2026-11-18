
<?php

// ======================================
// GOOD TOGETHER - HOMEPAGE
// ======================================

// 1. Start the user session.
session_start();

// 2. Connect to MySQL.
require "database.php";

// Check whether someone is logged in.
$loggedIn = isset($_SESSION["user_id"]);

// Get the account type.
$userRole = $_SESSION["user_role"] ?? "";

// Get the logged-in user's ID.
// Use 0 if nobody is logged in.
$userId = 0;

if ($loggedIn) {
    $userId = (int) $_SESSION["user_id"];
}

// Create a security token for registration forms.
if (!isset($_SESSION["join_token"])) {
    $_SESSION["join_token"] = bin2hex(random_bytes(32));
}

// ======================================
// GET EVENTS FROM MYSQL
// ======================================

// Get published upcoming events.
// Also count total registrations and
// whether this user has already joined.
$sql = "SELECT e.*,

        (SELECT COUNT(*)
         FROM registrations r
         WHERE r.event_id = e.event_id)
         AS registered_count,

        (SELECT COUNT(*)
         FROM registrations r
         WHERE r.event_id = e.event_id
         AND r.user_id = :user_id)
         AS my_registration_count

        FROM events e

        WHERE e.status = 'published'
        AND e.event_date >= NOW()

        ORDER BY e.event_date ASC";

// Prepare the query.
$statement = $pdo->prepare($sql);

// Run the query using the current user's ID.
$statement->execute([
    "user_id" => $userId
]);

// Store the event records in an array.
$events = $statement->fetchAll(PDO::FETCH_ASSOC);

// ======================================
// DISPLAY MESSAGES
// ======================================

// Possible registration messages.
$messages = [
    "joined" => "You successfully joined the event!",
    "already" => "You have already registered for this event.",
    "full" => "Sorry, this event is full.",
    "closed" => "This event is no longer available.",
    "volunteer_only" => "Only volunteer accounts can join events.",
    "invalid" => "Invalid request. Please try again.",
    "error" => "Something went wrong. Please try again."
];

// Read the message code from the URL.
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

    <title>Volunteer Opportunities | Good Together</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="page">

    <!-- WEBSITE HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>People helping people, close to home.</p>
    </header>
    <!-- SHARED NAVIGATION: HIDES THE LINK TO THE CURRENT PAGE -->
    <?php require "navigation.php"; ?>
    <!-- OPPORTUNITIES PAGE INTRO -->
    <section class="opportunities-intro">
        <p class="home-eyebrow">VOLUNTEER OPPORTUNITIES</p>
        <h2>Find your next way to make a difference.</h2>
        <p>Browse volunteering opportunities near you. Filter by city or category, review event details, and register for an activity that fits your interests.</p>
    </section>



    <!-- MAIN CONTENT -->
    <div class="content">

        <!-- LEFT SIDEBAR -->
        <aside class="sidebar">

            <h3>Find Opportunities</h3>

            <label for="search">Search Events</label>

            <input
                type="text"
                id="search"
                placeholder="Enter a keyword"
            >

            <label for="city">Select City</label>

            <select id="city">

                <option value="all">All Cities</option>
                <option value="Kuala Lumpur">Kuala Lumpur</option>
                <option value="Petaling Jaya">Petaling Jaya</option>
                <option value="Subang Jaya">Subang Jaya</option>
                <option value="Shah Alam">Shah Alam</option>

            </select>

            <label for="category">Select Category</label>

            <select id="category">

                <option value="all">All Categories</option>
                <option value="Environment">Environment</option>
                <option value="Charity">Charity</option>
                <option value="Animal Welfare">Animal Welfare</option>
                <option value="Community">Community</option>

            </select>

            <button id="resetButton" type="button">
                Reset Search
            </button>

        </aside>

        <!-- EVENT LIST -->
        <main id="events" class="main-content">

            <h2>Upcoming Volunteer Events</h2>

            <!-- Display registration message -->
            <?php if ($notice !== "") { ?>

                <p class="registration-notice">
                    <?php echo safe($notice); ?>
                </p>

            <?php } ?>

            <!-- Updated by JavaScript -->
            <p id="eventCount"></p>

            <!-- Display database events -->
            <?php foreach ($events as $event) { ?>

                <?php
                // Calculate how many places remain.
                $capacity = (int)$event["capacity"];

                $registered = (int)$event["registered_count"];

                $placesLeft = max(0, $capacity - $registered);

                // Check if this user already joined.
                $alreadyJoined =
                    (int)$event["my_registration_count"] > 0;
                ?>

                <div
                    class="event"
                    data-city="<?php echo safe($event['city']); ?>"
                    data-category="<?php echo safe($event['category']); ?>"
                >

                    <!-- EVENT TITLE -->
                    <h3>
                        <?php echo safe($event["title"]); ?>
                    </h3>

                    <p>
                        <strong>City:</strong>
                        <?php echo safe($event["city"]); ?>
                    </p>

                    <p>
                        <strong>Category:</strong>
                        <?php echo safe($event["category"]); ?>
                    </p>

                    <p>
                        <strong>Date:</strong>

                        <?php
                        echo date(
                            "d M Y",
                            strtotime($event["event_date"])
                        );
                        ?>
                    </p>

                    <p>
                        <strong>Available Places:</strong>
                        <?php echo $placesLeft; ?>
                        / <?php echo $capacity; ?>
                    </p>

                    <!-- OPEN EVENT INTRODUCTION PAGE -->
                    <div class="registration-area">
                        <a class="event-view-link"
                           href="event_details.php?id=<?php echo (int)$event['event_id']; ?>">
                            View Event
                        </a>
                    </div>

                </div>

            <?php } ?>

            <p id="noResults" hidden>
                No upcoming events match your search, or none are currently available.
            </p>

        </main>

    </div>

    <!-- ABOUT SECTION -->
    <section id="about" class="about">

        <h2>About Good Together</h2>

        <p>
            Good Together connects volunteers with
            community organizations and local activities.

            Our goal is to make volunteering easier,
            more accessible and better organized.
        </p>

    </section>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 Good Together. All Rights Reserved.</p>
    </footer>

</div>

<!-- Existing search and filter JavaScript -->
<script src="script.js"></script>

</body>
</html>
