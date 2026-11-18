
<?php

// GOOD TOGETHER - CANCEL REGISTRATION

// Start the user's session.
session_start();

// Connect to MySQL.
require "database.php";

// Return the user to My Registrations.
function goBack($message) {

    header(
        "Location: my_registrations.php?notice=" . $message
    );

    exit;
}

// Only accept POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_registrations.php");
    exit;
}

// Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Only volunteers can cancel registrations.
if (($_SESSION["user_role"] ?? "") !== "volunteer") {
    header("Location: dashboard.php");
    exit;
}

// -------------------------------------
// CHECK THE SECURITY TOKEN
// -------------------------------------

$token = $_POST["cancel_token"] ?? "";

if (
    !is_string($token) ||
    !isset($_SESSION["cancel_token"]) ||
    !hash_equals($_SESSION["cancel_token"], $token)
) {
    goBack("invalid");
}

// -------------------------------------
// GET THE REGISTRATION ID
// -------------------------------------

$registrationId = $_POST["registration_id"] ?? "";

// Make sure it is a valid number.
if (!is_string($registrationId)) {
    goBack("invalid");
}

$registrationId = filter_var(
    $registrationId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($registrationId === false) {
    goBack("invalid");
}

// Get the logged-in user's ID.
$userId = (int)$_SESSION["user_id"];


// -------------------------------------
// CHECK AND CANCEL THE REGISTRATION
// -------------------------------------

try {

    // Begin a database transaction.
    $pdo->beginTransaction();

    // Find the registration and its event.
    // The registration must belong to this user.
    $sql = "SELECT
                (e.event_date > NOW()) AS can_cancel

            FROM registrations r

            INNER JOIN events e
                ON r.event_id = e.event_id

            WHERE r.registration_id = :registration_id
            AND r.user_id = :user_id

            FOR UPDATE";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "registration_id" => $registrationId,
        "user_id" => $userId
    ]);

    $registration = $statement->fetch(PDO::FETCH_ASSOC);

    // Check whether the registration exists.
    if (!$registration) {

        $pdo->rollBack();
        goBack("not_found");

    }

    // Prevent cancellation after the event starts.
    if ((int)$registration["can_cancel"] !== 1) {

        $pdo->rollBack();
        goBack("too_late");

    }

    // -------------------------------------
    // DELETE THE REGISTRATION FROM MYSQL
    // -------------------------------------

    $sql = "DELETE FROM registrations
            WHERE registration_id = :registration_id
            AND user_id = :user_id";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "registration_id" => $registrationId,
        "user_id" => $userId
    ]);

    // Save the changes.
    $pdo->commit();

    // Return with a success message.
    goBack("cancelled");

} catch (PDOException $error) {

    // Undo incomplete changes if necessary.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Record the technical error.
    error_log($error->getMessage());

    goBack("error");
}

?>
