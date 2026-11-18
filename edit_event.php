 
<?php

// =====================================
// GOOD TOGETHER - EDIT EVENT
// =====================================

session_start();
require "database.php";

date_default_timezone_set("Asia/Kuala_Lumpur");

// Only logged-in users can access this page.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// Verify the account role using MySQL.
$sql = "SELECT role FROM users WHERE user_id = :user_id";
$statement = $pdo->prepare($sql);
$statement->execute(["user_id" => $userId]);
$user = $statement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user["role"] !== "organizer") {
    http_response_code(403);
    exit("Access denied. Only organizers can edit events.");
}

// Read the event ID from the URL or submitted form.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rawId = $_POST["event_id"] ?? "";
} else {
    $rawId = $_GET["id"] ?? "";
}

// Check that the event ID is a valid number.
if (!is_string($rawId) && !is_int($rawId)) {
    http_response_code(400);
    exit("Invalid event ID.");
}

$eventId = filter_var(
    $rawId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($eventId === false) {
    http_response_code(400);
    exit("Invalid event ID.");
}

// Get only an event that belongs to this organizer.
$sql = "SELECT * FROM events
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
    exit("Event not found or you do not own this event.");
}

// Editing is limited to upcoming published events.
if (
    $event["status"] !== "published" ||
    strtotime($event["event_date"]) <= time()
) {
    exit("Only upcoming published events can be edited.");
}

// Security token for the edit form.
if (!isset($_SESSION["edit_event_token"])) {
    $_SESSION["edit_event_token"] = bin2hex(random_bytes(32));
}

// Allowed selections.
$cities = [
    "Kuala Lumpur",
    "Petaling Jaya",
    "Subang Jaya",
    "Shah Alam"
];

$categories = [
    "Environment",
    "Charity",
    "Animal Welfare",
    "Community"
];

// Safely display text in HTML.
function safe($text) {
    return htmlspecialchars(
        (string)$text,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Read form input safely.
function getInput($name) {
    $value = $_POST[$name] ?? "";
    return is_string($value) ? trim($value) : "";
}

// Load existing information into the form.
$title = $event["title"];
$description = $event["description"];
$city = $event["city"];
$category = $event["category"];
$venue = $event["venue"];

$eventDate = date(
    "Y-m-d\TH:i",
    strtotime($event["event_date"])
);

$capacityText = (string)$event["capacity"];
$error = "";

// =====================================
// PROCESS THE EDIT FORM
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get the updated information.
    $title = getInput("title");
    $description = getInput("description");
    $city = getInput("city");
    $category = getInput("category");
    $venue = getInput("venue");
    $eventDate = getInput("event_date");
    $capacityText = getInput("capacity");

    // Check capacity.
    $capacity = filter_var(
        $capacityText,
        FILTER_VALIDATE_INT,
        ["options" => [
            "min_range" => 1,
            "max_range" => 500
        ]]
    );

    // Check date and time.
    $dateObject = DateTimeImmutable::createFromFormat(
        "!Y-m-d\TH:i",
        $eventDate
    );

    $validDate = (
        $dateObject !== false &&
        $dateObject->format("Y-m-d\TH:i") === $eventDate
    );

    // Check the security token.
    $token = getInput("edit_event_token");

    if (!hash_equals($_SESSION["edit_event_token"], $token)) {

        $error = "Invalid request. Please reload the page.";

    } elseif (
        $title === "" ||
        $description === "" ||
        $city === "" ||
        $category === "" ||
        $venue === "" ||
        $eventDate === "" ||
        $capacityText === ""
    ) {

        $error = "Please complete all fields.";

    } elseif (
        strlen($title) > 150 ||
        strlen($venue) > 200 ||
        strlen($description) > 2000
    ) {

        $error = "Some information is too long.";

    } elseif (!in_array($city, $cities, true)) {

        $error = "Please select a valid city.";

    } elseif (!in_array($category, $categories, true)) {

        $error = "Please select a valid category.";

    } elseif ($capacity === false) {

        $error = "Capacity must be between 1 and 500.";

    } elseif (!$validDate) {

        $error = "Please enter a valid date and time.";

    } elseif ($dateObject <= new DateTimeImmutable()) {

        $error = "The event date must be in the future.";

    } else {

        try {

            // Start a transaction so capacity cannot
            // change while we update the event.
            $pdo->beginTransaction();

            // Lock the event and confirm ownership again.
            $sql = "SELECT
                        status,
                        (event_date > NOW()) AS is_future
                    FROM events
                    WHERE event_id = :event_id
                    AND organizer_id = :organizer_id
                    FOR UPDATE";

            $statement = $pdo->prepare($sql);

            $statement->execute([
                "event_id" => $eventId,
                "organizer_id" => $userId
            ]);

            $currentEvent = $statement->fetch(
                PDO::FETCH_ASSOC
            );

            if (
                !$currentEvent ||
                $currentEvent["status"] !== "published" ||
                (int)$currentEvent["is_future"] !== 1
            ) {

                $pdo->rollBack();
                $error = "This event can no longer be edited.";

            } else {

                // Count volunteers already registered.
                $sql = "SELECT COUNT(*)
                        FROM registrations
                        WHERE event_id = :event_id";

                $statement = $pdo->prepare($sql);

                $statement->execute([
                    "event_id" => $eventId
                ]);

                $registered = (int)$statement->fetchColumn();

                // Never reduce capacity below registrations.
                if ($capacity < $registered) {

                    $pdo->rollBack();

                    $error = "This event already has "
                           . $registered
                           . " registered volunteers. "
                           . "Capacity cannot be lower.";

                } else {

                    // Update the event details.
                    $sql = "UPDATE events
                            SET title = :title,
                                description = :description,
                                city = :city,
                                category = :category,
                                venue = :venue,
                                event_date = :event_date,
                                capacity = :capacity
                            WHERE event_id = :event_id
                            AND organizer_id = :organizer_id";

                    $statement = $pdo->prepare($sql);

                    $statement->execute([
                        "title" => $title,
                        "description" => $description,
                        "city" => $city,
                        "category" => $category,
                        "venue" => $venue,
                        "event_date" =>
                            $dateObject->format("Y-m-d H:i:s"),
                        "capacity" => $capacity,
                        "event_id" => $eventId,
                        "organizer_id" => $userId
                    ]);

                    $pdo->commit();

                    header("Location: dashboard.php?notice=updated");
                    exit;
                }
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $error = "Unable to update event. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Event | Good Together</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
<div class="page">

    <header>
        <h1>Good Together</h1>
        <p>Organizer Event Management</p>
    </header>


<?php require "navigation.php"; ?>


    <main class="auth-container">
        <div class="auth-box event-form">

            <h2>Edit Event</h2>

            <p>
                Update your event details below.
                Registered volunteers will keep their places.
            </p>

            <?php if ($error !== "") { ?>
                <p class="form-message error">
                    <?php echo safe($error); ?>
                </p>
            <?php } ?>

            <form method="POST" action="edit_event.php">

                <input
                    type="hidden"
                    name="event_id"
                    value="<?php echo (int)$eventId; ?>"
                >

                <input
                    type="hidden"
                    name="edit_event_token"
                    value="<?php echo safe($_SESSION['edit_event_token']); ?>"
                >

                <label for="title">Event Title</label>
                <input
                    type="text"
                    id="title"
                    name="title"
                    value="<?php echo safe($title); ?>"
                    maxlength="150"
                    required
                >

                <label for="city">City</label>
                <select id="city" name="city" required>
                    <?php foreach ($cities as $option) { ?>
                        <option
                            value="<?php echo safe($option); ?>"
                            <?php if ($city === $option) echo "selected"; ?>
                        >
                            <?php echo safe($option); ?>
                        </option>
                    <?php } ?>
                </select>

                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <?php foreach ($categories as $option) { ?>
                        <option
                            value="<?php echo safe($option); ?>"
                            <?php if ($category === $option) echo "selected"; ?>
                        >
                            <?php echo safe($option); ?>
                        </option>
                    <?php } ?>
                </select>

                <label for="venue">Venue</label>
                <input
                    type="text"
                    id="venue"
                    name="venue"
                    value="<?php echo safe($venue); ?>"
                    maxlength="200"
                    required
                >

                <label for="eventDate">Event Date and Time</label>
                <input
                    type="datetime-local"
                    id="eventDate"
                    name="event_date"
                    value="<?php echo safe($eventDate); ?>"
                    required
                >

                <label for="capacity">Maximum Volunteers</label>
                <input
                    type="number"
                    id="capacity"
                    name="capacity"
                    min="1"
                    max="500"
                    value="<?php echo safe($capacityText); ?>"
                    required
                >

                <label for="description">Event Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    maxlength="2000"
                    required
                ><?php echo safe($description); ?></textarea>

                <button type="submit">
                    Save Changes
                </button>

            </form>

            <p class="auth-link">
                <a href="dashboard.php">
                    Back to Dashboard
                </a>
            </p>

        </div>
    </main>

    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>
</body>
</html>
