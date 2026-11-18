
<?php

// ======================================
// GOOD TOGETHER - JOIN AN EVENT
// ======================================

// 1. Start the user's login session.
session_start();

// 2. Connect to MySQL.
require "database.php";

// This function returns the user to the homepage.
function goHome($message) {

    header("Location: opportunities.php?notice=" . $message . "#events");
    exit;
}

// 3. Only accept a form submission.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    goHome("invalid");
}

// 4. Check whether the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// 5. Only volunteers can join events.
if (($_SESSION["user_role"] ?? "") !== "volunteer") {
    goHome("volunteer_only");
}

// 6. Check the form's security token.
$token = $_POST["join_token"] ?? "";

if (
    !is_string($token) ||
    !isset($_SESSION["join_token"]) ||
    !hash_equals($_SESSION["join_token"], $token)
) {
    goHome("invalid");
}

// 7. Get the selected event ID.
$eventId = $_POST["event_id"] ?? "";

if (!is_string($eventId)) {
    goHome("invalid");
}

// Check that the event ID is a positive number.
$eventId = filter_var(
    $eventId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($eventId === false) {
    goHome("invalid");
}

// Get the logged-in volunteer's ID.
$userId = (int) $_SESSION["user_id"];

// ======================================
// CHECK EVENT AND SAVE REGISTRATION
// ======================================

try {

    // Start a database transaction.
    $pdo->beginTransaction();

    // 8. Find the event and lock its row.
    // This helps prevent two requests from
    // taking the final available place together.
    $sql = "SELECT capacity, status,
                   (event_date >= NOW()) AS is_future
            FROM events
            WHERE event_id = :event_id
            FOR UPDATE";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "event_id" => $eventId
    ]);

    $event = $statement->fetch(PDO::FETCH_ASSOC);

    // Stop if the event does not exist.
    if (!$event) {
        $pdo->rollBack();
        goHome("closed");
    }

    // Stop if the event is cancelled or in the past.
    if (
        $event["status"] !== "published" ||
        (int)$event["is_future"] !== 1
    ) {
        $pdo->rollBack();
        goHome("closed");
    }

    // 9. Check if the volunteer already joined.
    $sql = "SELECT registration_id
            FROM registrations
            WHERE user_id = :user_id
            AND event_id = :event_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "user_id" => $userId,
        "event_id" => $eventId
    ]);

    $existingRegistration = $statement->fetch();

    if ($existingRegistration) {
        $pdo->rollBack();
        goHome("already");
    }

    // 10. Count registrations for this event.
    $sql = "SELECT COUNT(*)
            FROM registrations
            WHERE event_id = :event_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "event_id" => $eventId
    ]);

    $registeredCount = (int)$statement->fetchColumn();

    // Stop if there are no places left.
    if ($registeredCount >= (int)$event["capacity"]) {
        $pdo->rollBack();
        goHome("full");
    }

    // 11. Save the new registration.
    $sql = "INSERT INTO registrations
            (user_id, event_id)
            VALUES (:user_id, :event_id)";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "user_id" => $userId,
        "event_id" => $eventId
    ]);

    // 12. Save the database changes.
    $pdo->commit();

    // Return to homepage with success message.
    goHome("joined");

} catch (PDOException $error) {

    // Undo unfinished database changes.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Save technical error in server log.
    error_log($error->getMessage());

    goHome("error");
}
?>
