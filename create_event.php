
<?php

// =====================================
// GOOD TOGETHER - CREATE EVENT
// =====================================

// 1. Start the login session.
session_start();

// 2. Connect to MySQL.
require "database.php";

// Use Malaysia's local time for event dates.
date_default_timezone_set("Asia/Kuala_Lumpur");

// 3. Check whether the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Store the logged-in user's ID.
$userId = (int) $_SESSION["user_id"];

// 4. Check the user's role in MySQL.
$sql = "SELECT role FROM users WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "user_id" => $userId
]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

// Only organizers can create events.
if (!$user || $user["role"] !== "organizer") {
    http_response_code(403);
    exit("Access denied. Only organizers can create events.");
}

// 5. Create a security token.
if (!isset($_SESSION["event_token"])) {
    $_SESSION["event_token"] = bin2hex(random_bytes(32));
}

// Store error messages.
$error = "";

// Available cities and categories.
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

// This function safely displays text in HTML.
function safe($text) {
    return htmlspecialchars(
        (string)$text,
        ENT_QUOTES,
        "UTF-8"
    );
}

// This function reads a form field.
// If the field is missing, return an empty string.
function getInput($name) {

    if (isset($_POST[$name]) && is_string($_POST[$name])) {
        return trim($_POST[$name]);
    }

    return "";
}

// Empty values before the form is submitted.
$title = "";
$description = "";
$city = "";
$category = "";
$venue = "";
$eventDate = "";
$capacityText = "";

// =====================================
// PROCESS THE CREATE EVENT FORM
// =====================================

// 6. Check if the form was submitted.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Read information from the form.
    $title = getInput("title");
    $description = getInput("description");
    $city = getInput("city");
    $category = getInput("category");
    $venue = getInput("venue");
    $eventDate = getInput("event_date");
    $capacityText = getInput("capacity");
    $token = getInput("event_token");

    // Convert capacity to a whole number.
    $capacity = filter_var(
        $capacityText,
        FILTER_VALIDATE_INT,
        ["options" => [
            "min_range" => 1,
            "max_range" => 500
        ]]
    );

    // Convert date input into a PHP date object.
    $dateObject = DateTimeImmutable::createFromFormat(
        "!Y-m-d\TH:i",
        $eventDate
    );

    // Check whether the date is valid.
    $validDate = (
        $dateObject !== false &&
        $dateObject->format("Y-m-d\TH:i") === $eventDate
    );

    // =====================================
    // VALIDATE THE FORM
    // =====================================

    // Check the security token.
    if (!hash_equals($_SESSION["event_token"], $token)) {

        $error = "Invalid form request. Please reload the page.";

    // Check required fields.
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

    // Check title and venue lengths.
    } elseif (
        strlen($title) > 150 ||
        strlen($venue) > 200 ||
        strlen($description) > 2000
    ) {

        $error = "Some information is too long.";

    // Check city.
    } elseif (!in_array($city, $cities, true)) {

        $error = "Please select a valid city.";

    // Check category.
    } elseif (!in_array($category, $categories, true)) {

        $error = "Please select a valid category.";

    // Check capacity.
    } elseif ($capacity === false) {

        $error = "Capacity must be between 1 and 500.";

    // Check date.
    } elseif (!$validDate) {

        $error = "Please enter a valid event date and time.";

    // The event must be in the future.
    } elseif ($dateObject <= new DateTimeImmutable()) {

        $error = "Please choose a future date and time.";

    } else {

        // =====================================
        // SAVE THE EVENT INTO MYSQL
        // =====================================

        try {

            // 7. Write the SQL INSERT command.
            $sql = "INSERT INTO events
                    (
                        organizer_id,
                        title,
                        description,
                        city,
                        category,
                        venue,
                        event_date,
                        capacity,
                        status
                    )
                    VALUES
                    (
                        :organizer_id,
                        :title,
                        :description,
                        :city,
                        :category,
                        :venue,
                        :event_date,
                        :capacity,
                        'published'
                    )";

            // 8. Prepare the SQL command.
            $statement = $pdo->prepare($sql);

            // 9. Save the information.
            $statement->execute([
                "organizer_id" => $userId,
                "title" => $title,
                "description" => $description,
                "city" => $city,
                "category" => $category,
                "venue" => $venue,
                "event_date" => $dateObject->format("Y-m-d H:i:s"),
                "capacity" => $capacity
            ]);

            // 10. Return to the dashboard.
            header("Location: dashboard.php?created=1");
            exit;

        } catch (PDOException $e) {

            // Record the technical error in the server log.
            error_log($e->getMessage());

            $error = "Unable to create event. Please try again.";
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

    <title>Create Event | Good Together</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="page">

    <!-- WEBSITE HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>Create a Volunteering Event</p>
    </header>

    <!-- NAVIGATION -->

<?php require "navigation.php"; ?>

    <!-- MAIN FORM -->
    <main class="auth-container">

        <div class="auth-box event-form">

            <h2>Create New Event</h2>

            <p>
                Complete the information below to
                publish a volunteering activity.
            </p>

            <!-- Display PHP error -->
            <?php if ($error !== "") { ?>

                <p class="form-message error">
                    <?php echo safe($error); ?>
                </p>

            <?php } ?>

            <!-- CREATE EVENT FORM -->
            <form method="POST" action="create_event.php">

                <!-- Security token -->
                <input
                    type="hidden"
                    name="event_token"
                    value="<?php echo safe($_SESSION['event_token']); ?>"
                >

                <!-- EVENT TITLE -->
                <label for="title">Event Title</label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    placeholder="Example: Beach Clean-Up"
                    maxlength="150"
                    value="<?php echo safe($title); ?>"
                    required
                >

                <!-- CITY -->
                <label for="city">City</label>

                <select id="city" name="city" required>
                    <option value="">Select City</option>

                    <?php foreach ($cities as $cityOption) { ?>

                        <option
                            value="<?php echo safe($cityOption); ?>"
                            <?php
                            if ($city === $cityOption) {
                                echo "selected";
                            }
                            ?>
                        >
                            <?php echo safe($cityOption); ?>
                        </option>

                    <?php } ?>
                </select>

                <!-- CATEGORY -->
                <label for="category">Category</label>

                <select id="category" name="category" required>
                    <option value="">Select Category</option>

                    <?php foreach ($categories as $categoryOption) { ?>

                        <option
                            value="<?php echo safe($categoryOption); ?>"
                            <?php
                            if ($category === $categoryOption) {
                                echo "selected";
                            }
                            ?>
                        >
                            <?php echo safe($categoryOption); ?>
                        </option>

                    <?php } ?>
                </select>

                <!-- VENUE -->
                <label for="venue">Venue</label>

                <input
                    type="text"
                    id="venue"
                    name="venue"
                    placeholder="Example: Community Hall"
                    maxlength="200"
                    value="<?php echo safe($venue); ?>"
                    required
                >

                <!-- DATE AND TIME -->
                <label for="eventDate">Event Date and Time</label>

                <input
                    type="datetime-local"
                    id="eventDate"
                    name="event_date"
                    value="<?php echo safe($eventDate); ?>"
                    required
                >

                <!-- MAXIMUM VOLUNTEERS -->
                <label for="capacity">Maximum Volunteers</label>

                <input
                    type="number"
                    id="capacity"
                    name="capacity"
                    min="1"
                    max="500"
                    value="<?php echo safe($capacityText); ?>"
                    placeholder="Example: 30"
                    required
                >

                <!-- DESCRIPTION -->
                <label for="description">Event Description</label>

                <textarea
                    id="description"
                    name="description"
                    rows="5"
                    maxlength="2000"
                    placeholder="Describe the event and volunteer duties..."
                    required
                ><?php echo safe($description); ?></textarea>

                <!-- SUBMIT BUTTON -->
                <button type="submit">
                    Publish Event
                </button>

            </form>

            <p class="auth-link">
                <a href="dashboard.php">
                    Back to Dashboard
                </a>
            </p>

        </div>

    </main>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>

</body>
</html>
