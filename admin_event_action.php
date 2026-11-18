
<?php

// ======================================
// GOOD TOGETHER - ADMIN EVENT ACTION
// ======================================

session_start();
require "database.php";

date_default_timezone_set("Asia/Kuala_Lumpur");

// Return to the Admin Event Moderation page.
function goBack($message) {

    header(
        "Location: admin_moderation.php?notice=" . $message
    );

    exit;
}

// 1. Only POST requests can change event data.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    goBack("invalid");
}

// 2. Check whether the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

// 3. Verify the Admin role in MySQL.
$sql = "SELECT role FROM users WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "user_id" => $userId
]);

$user = $statement->fetch(PDO::FETCH_ASSOC);

if (!$user || $user["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Administrators only.");
}

// ======================================
// VALIDATE THE FORM
// ======================================

// 4. Check the security token.
$token = $_POST["admin_event_token"] ?? "";

if (
    !is_string($token) ||
    !isset($_SESSION["admin_event_token"]) ||
    !hash_equals($_SESSION["admin_event_token"], $token)
) {
    goBack("invalid");
}

// 5. Get the selected event ID.
$rawEventId = $_POST["event_id"] ?? "";

if (!is_string($rawEventId)) {
    goBack("invalid");
}

$eventId = filter_var(
    $rawEventId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($eventId === false) {
    goBack("invalid");
}

// 6. Get the requested action.
$action = $_POST["action"] ?? "";

// Only allow two specific actions.
if (
    !is_string($action) ||
    !in_array($action, ["hide", "restore"], true)
) {
    goBack("invalid");
}

// ======================================
// UPDATE THE EVENT IN MYSQL
// ======================================

try {

    // Start a transaction.
    $pdo->beginTransaction();

    // 7. Lock the selected event record.
    $sql = "SELECT event_id, status, event_date
            FROM events
            WHERE event_id = :event_id
            FOR UPDATE";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "event_id" => $eventId
    ]);

    $event = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$event) {

        $pdo->rollBack();
        goBack("not_found");
    }

    // ----------------------------------
    // HIDE AN EVENT
    // ----------------------------------

    if ($action === "hide") {

        // Only published events can be hidden.
        if ($event["status"] !== "published") {

            $pdo->rollBack();
            goBack("invalid");
        }

        // Check whether volunteers have joined.
        $sql = "SELECT COUNT(*)
                FROM registrations
                WHERE event_id = :event_id";

        $statement = $pdo->prepare($sql);

        $statement->execute([
            "event_id" => $eventId
        ]);

        $registered = (int)$statement->fetchColumn();

        // Protect events with registrations.
        if ($registered > 0) {

            $pdo->rollBack();
            goBack("blocked");
        }

        // Set the new status.
        $newStatus = "hidden";
        $successMessage = "hidden";

    // ----------------------------------
    // RESTORE AN EVENT
    // ----------------------------------

    } else {

        // Only hidden events can be restored.
        if ($event["status"] !== "hidden") {

            $pdo->rollBack();
            goBack("invalid");
        }

        // Do not restore events already in the past.
        if (
            strtotime($event["event_date"]) <= time()
        ) {

            $pdo->rollBack();
            goBack("expired");
        }

        // Set the new status.
        $newStatus = "published";
        $successMessage = "restored";
    }

    // ----------------------------------
    // SAVE THE CHANGE
    // ----------------------------------

    // 8. Update the event's status in MySQL.
    $sql = "UPDATE events
            SET status = :status
            WHERE event_id = :event_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "status" => $newStatus,
        "event_id" => $eventId
    ]);

    // 9. Save the transaction.
    $pdo->commit();

    // Return to the Admin page.
    goBack($successMessage);

} catch (PDOException $error) {

    // Undo unfinished changes.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Keep technical details in the server log.
    error_log($error->getMessage());

    goBack("error");
}

?>
