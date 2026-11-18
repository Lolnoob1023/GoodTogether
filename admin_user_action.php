
<?php

// =====================================
// GOOD TOGETHER - ADMIN USER ACTION
// =====================================

session_start();
require "database.php";

// Return to the User Management page.
function goBack($message) {

    header(
        "Location: admin_users.php?notice=" . $message
    );

    exit;
}

// Only accept POST form submissions.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    goBack("invalid");
}

// Check login.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$adminId = (int)$_SESSION["user_id"];

// =====================================
// VERIFY ADMINISTRATOR
// =====================================

$sql = "SELECT role, account_status
        FROM users
        WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);

$statement->execute([
    "user_id" => $adminId
]);

$admin = $statement->fetch(PDO::FETCH_ASSOC);

if (
    !$admin ||
    $admin["role"] !== "admin" ||
    $admin["account_status"] !== "active"
) {

    http_response_code(403);
    exit("Access denied.");
}

// =====================================
// CHECK SUBMITTED INFORMATION
// =====================================

// Validate the security token.
$token = $_POST["admin_user_token"] ?? "";

if (
    !is_string($token) ||
    !isset($_SESSION["admin_user_token"]) ||
    !hash_equals($_SESSION["admin_user_token"], $token)
) {

    goBack("invalid");
}

// Get the selected user ID.
$rawId = $_POST["user_id"] ?? "";

if (!is_string($rawId)) {
    goBack("invalid");
}

$targetId = filter_var(
    $rawId,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if ($targetId === false) {
    goBack("invalid");
}

// Get the requested action.
$action = $_POST["action"] ?? "";

if (
    !is_string($action) ||
    !in_array($action, ["suspend", "restore"], true)
) {

    goBack("invalid");
}

// Never allow an Admin to suspend themselves.
if ($targetId === $adminId) {
    goBack("protected");
}

// =====================================
// CHANGE ACCOUNT STATUS IN MYSQL
// =====================================

try {

    // Start a transaction.
    $pdo->beginTransaction();

    // Lock the selected user record.
    $sql = "SELECT role, account_status
            FROM users
            WHERE user_id = :user_id
            FOR UPDATE";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "user_id" => $targetId
    ]);

    $targetUser = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    // Check whether the account exists.
    if (!$targetUser) {

        $pdo->rollBack();
        goBack("not_found");
    }

    // Protect all Administrator accounts.
    if ($targetUser["role"] === "admin") {

        $pdo->rollBack();
        goBack("protected");
    }

    // Only Volunteer or Organizer accounts
    // can be managed through this feature.
    if (
        !in_array(
            $targetUser["role"],
            ["volunteer", "organizer"],
            true
        )
    ) {

        $pdo->rollBack();
        goBack("invalid");
    }

    // Decide the new account status.
    if ($action === "suspend") {

        $newStatus = "suspended";
        $successMessage = "suspended";

    } else {

        $newStatus = "active";
        $successMessage = "restored";
    }

    // Prevent unnecessary updates.
    if ($targetUser["account_status"] === $newStatus) {

        $pdo->rollBack();
        goBack("unchanged");
    }

    // Update only the selected account.
    $sql = "UPDATE users
            SET account_status = :status
            WHERE user_id = :user_id
            AND role IN ('volunteer', 'organizer')";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        "status" => $newStatus,
        "user_id" => $targetId
    ]);

    // Save the database change.
    $pdo->commit();

    // Return to Admin User Management.
    goBack($successMessage);

} catch (PDOException $error) {

    // Cancel unfinished database changes.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($error->getMessage());

    goBack("error");
}

?>
