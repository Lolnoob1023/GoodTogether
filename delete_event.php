
<?php

// =====================================
// GOOD TOGETHER - DELETE EVENT
// =====================================

session_start();
require "database.php";

// Redirect back to the dashboard.
function goDashboard($message) {
    header("Location: dashboard.php?notice=" . $message);
    exit;
}

// Only POST requests can delete events.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    goDashboard("invalid");
}

// Check login.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

// Check the user's actual role in MySQL.
$sql = "SELECT role FROM users WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "user_id" => $userId
]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user["role"] !== "organizer") {
    http_response_code(403);
    exit("Access denied.");
}

// Check the security token.
$token = $_POST["delete_event_token"] ?? "";

if (
    !is_string($token) ||
    !isset($_SESSION["delete_event_token"]) ||
    !hash_equals($_SESSION["delete_event_token"], $token)
) {
    goDashboard("invalid");
}

// Get and validate the event ID.
$rawId = $_POST["event_id"] ?? "";

if (!is_string($rawId) && !is_int($rawId)) {
    goDashboard("invalid");
}

$eventId = filter_var(
    $rawId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($eventId === false) {
    goDashboard("invalid");
}

// =====================================
// CHECK AND DELETE THE EVENT
// =====================================

try {

    $pdo->beginTransaction();

    // Find the event and lock its row.
    // It must belong to this organizer.
    $sql = "SELECT event_id
            FROM events
            WHERE event_id = :event_id
            AND organizer_id = :organizer_id
            FOR UPDATE";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "event_id" => $eventId,
        "organizer_id" => $userId
    ]);

    $event = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$event) {

        $pdo->rollBack();
        goDashboard("not_found");
    }

    // Count existing volunteer registrations.
    $sql = "SELECT COUNT(*)
            FROM registrations
            WHERE event_id = :event_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "event_id" => $eventId
    ]);

    $registered = (int)$statement->fetchColumn();

    // Protect events that already have volunteers.
    if ($registered > 0) {

        $pdo->rollBack();
        goDashboard("blocked");
    }

    // Delete only this organizer's event.
    $sql = "DELETE FROM events
            WHERE event_id = :event_id
            AND organizer_id = :organizer_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "event_id" => $eventId,
        "organizer_id" => $userId
    ]);

    // Save the database change.
    $pdo->commit();

    goDashboard("deleted");

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    goDashboard("error");
}

?>
