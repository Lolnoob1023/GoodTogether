
<?php

// GOOD TOGETHER - SAVE VOLUNTEER ATTENDANCE

session_start();
require "database.php";

date_default_timezone_set("Asia/Kuala_Lumpur");

// Return to the attendance page.
function goBack($eventId, $notice) {
    header(
        "Location: event_volunteers.php?id=" .
        $eventId . "&notice=" . $notice
    );
    exit;
}

// Only POST requests are accepted.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}

// User must be logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

// Verify the account is an Organizer.
$sql = "SELECT role FROM users WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);
$statement->execute(["user_id" => $userId]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user["role"] !== "organizer") {
    http_response_code(403);
    exit("Access denied.");
}

// Get form values.
$rawEventId = $_POST["event_id"] ?? "";
$rawRegistrationId = $_POST["registration_id"] ?? "";
$status = $_POST["attendance_status"] ?? "";
$token = $_POST["attendance_token"] ?? "";

// Validate the ID fields.
if (
    !is_string($rawEventId) ||
    !is_string($rawRegistrationId)
) {
    header("Location: dashboard.php");
    exit;
}

$eventId = filter_var(
    $rawEventId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$registrationId = filter_var(
    $rawRegistrationId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($eventId === false || $registrationId === false) {
    header("Location: dashboard.php");
    exit;
}

// Verify the security token.
if (
    !is_string($token) ||
    !isset($_SESSION["attendance_token"]) ||
    !hash_equals($_SESSION["attendance_token"], $token)
) {
    goBack($eventId, "invalid");
}

// Only accept valid attendance values.
if (
    !is_string($status) ||
    !in_array(
        $status,
        ["pending", "present", "absent"],
        true
    )
) {
    goBack($eventId, "invalid");
}

try {

    // Check that the volunteer is registered
    // for an event owned by this organizer.
    $sql = "SELECT r.registration_id

            FROM registrations r

            INNER JOIN events e
                ON r.event_id = e.event_id

            WHERE r.registration_id = :registration_id
            AND r.event_id = :event_id
            AND e.organizer_id = :organizer_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "registration_id" => $registrationId,
        "event_id" => $eventId,
        "organizer_id" => $userId
    ]);

    $registration = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        goBack($eventId, "not_found");
    }

    // Record the time when attendance is marked.
    $updatedAt = null;

    if ($status !== "pending") {
        $updatedAt = date("Y-m-d H:i:s");
    }

    // Save the attendance status in MySQL.
    $sql = "UPDATE registrations r

            INNER JOIN events e
                ON r.event_id = e.event_id

            SET r.attendance_status = :status,
                r.attendance_updated_at = :updated_at

            WHERE r.registration_id = :registration_id
            AND r.event_id = :event_id
            AND e.organizer_id = :organizer_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "status" => $status,
        "updated_at" => $updatedAt,
        "registration_id" => $registrationId,
        "event_id" => $eventId,
        "organizer_id" => $userId
    ]);

    // Return with a success message.
    goBack($eventId, "saved");

} catch (PDOException $error) {

    error_log($error->getMessage());

    goBack($eventId, "error");
}

?>
