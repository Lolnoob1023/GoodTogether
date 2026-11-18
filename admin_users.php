
<?php

// =====================================
// GOOD TOGETHER - ADMIN USER MANAGEMENT
// =====================================

session_start();
require "database.php";

// Check login.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$adminId = (int)$_SESSION["user_id"];

// Verify Administrator privileges.
$sql = "SELECT role, account_status
        FROM users
        WHERE user_id = :user_id";

$statement = $pdo->prepare($sql);
$statement->execute(["user_id" => $adminId]);

$admin = $statement->fetch(PDO::FETCH_ASSOC);

if (
    !$admin ||
    $admin["role"] !== "admin" ||
    $admin["account_status"] !== "active"
) {
    http_response_code(403);
    exit("Access denied. Administrators only.");
}

// Create form security token.
if (!isset($_SESSION["admin_user_token"])) {
    $_SESSION["admin_user_token"] =
        bin2hex(random_bytes(32));
}

// =====================================
// RETRIEVE USER ACCOUNTS
// =====================================

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
        LIMIT 100";

$statement = $pdo->query($sql);
$accounts = $statement->fetchAll(PDO::FETCH_ASSOC);

// Get account statistics.
$sql = "SELECT
            COUNT(*) AS total_users,
            COALESCE(
                SUM(account_status = 'active'), 0
            ) AS active_users,
            COALESCE(
                SUM(account_status = 'suspended'), 0
            ) AS suspended_users
        FROM users";

$statement = $pdo->query($sql);
$counts = $statement->fetch(PDO::FETCH_ASSOC);

// Messages after an action.
$messages = [
    "suspended" => "Account suspended successfully.",
    "restored" => "Account restored successfully.",
    "protected" => "Administrator accounts are protected.",
    "not_found" => "User account not found.",
    "invalid" => "Invalid request.",
    "unchanged" => "This account already has that status.",
    "error" => "Unable to update account."
];

$noticeCode = $_GET["notice"] ?? "";
$notice = "";

if (is_string($noticeCode)) {
    $notice = $messages[$noticeCode] ?? "";
}

// Safely display database text.
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

    <title>Manage Users | Good Together</title>

    <link rel="stylesheet" href="style.css">

    <style>

        .user-admin-page {
            max-width: 1100px;
            margin: 30px auto;
            padding: 25px;
        }

        .user-admin-page h2,
        .user-admin-page h3 {
            color: #344b3c;
            font-family: Georgia, serif;
        }

        .user-admin-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
        }

        .user-admin-stat {
            background: #fffdf7;
            border: 1px solid #ccc;
            padding: 20px;
            text-align: center;
        }

        .user-admin-stat strong {
            display: block;
            font-size: 28px;
            color: #344b3c;
        }

        .user-admin-stat span {
            font-size: 13px;
            color: #555;
        }

        .user-admin-table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        .user-admin-table {
            border-collapse: collapse;
            width: 100%;
            min-width: 850px;
            background: white;
        }

        .user-admin-table th {
            background: #344b3c;
            color: white;
            padding: 12px;
            text-align: left;
            border: 1px solid #ccc;
        }

        .user-admin-table td {
            padding: 12px;
            border: 1px solid #ccc;
            overflow-wrap: anywhere;
        }

        .user-admin-table tr:nth-child(even) {
            background: #f6f0e3;
        }

        .user-admin-table form {
            margin: 0;
        }

        .user-admin-button {
            color: white;
            padding: 10px 14px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }

        .suspend-button {
            background: #a76542;
        }

        .restore-button {
            background: #344b3c;
        }

        .account-active {
            color: #27613e;
            font-weight: bold;
        }

        .account-suspended {
            color: #974c31;
            font-weight: bold;
        }

        .account-protected {
            font-size: 12px;
            color: #777;
        }

        .admin-user-message {
            padding: 15px;
            background: #e3eee3;
            border: 1px solid #8ab499;
            color: #27613e;
            margin: 20px 0;
        }

        @media (max-width: 650px) {
            .user-admin-stats {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <!-- WEBSITE HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>Administrator - User Management</p>
    </header>

    <!-- NAVIGATION -->
 
<?php require "navigation.php"; ?>

    <main class="user-admin-page">

        <h2>Manage User Accounts</h2>

        <p>
            Review registered users, suspend accounts,
            or restore accounts when appropriate.
        </p>

        <!-- ACCOUNT STATISTICS -->
        <div class="user-admin-stats">

            <div class="user-admin-stat">
                <strong>
                    <?php echo (int)$counts["total_users"]; ?>
                </strong>
                <span>Total Users</span>
            </div>

            <div class="user-admin-stat">
                <strong>
                    <?php echo (int)$counts["active_users"]; ?>
                </strong>
                <span>Active Accounts</span>
            </div>

            <div class="user-admin-stat">
                <strong>
                    <?php echo (int)$counts["suspended_users"]; ?>
                </strong>
                <span>Suspended Accounts</span>
            </div>

        </div>

        <!-- FEEDBACK -->
        <?php if ($notice !== "") { ?>

            <p class="admin-user-message">
                <?php echo safe($notice); ?>
            </p>

        <?php } ?>

        <h3>Registered Users</h3>

        <p>
            Displaying up to 100 recent accounts.
            Administrator accounts cannot be suspended.
        </p>

        <div class="user-admin-table-wrapper">

            <table class="user-admin-table">

                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>City</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($accounts as $account) { ?>

                    <?php
                    $targetId = (int)$account["user_id"];
                    $status = $account["account_status"];

                    $isProtected = (
                        $targetId === $adminId ||
                        $account["role"] === "admin"
                    );
                    ?>

                    <tr>

                        <td>
                            <?php echo $targetId; ?>
                        </td>

                        <td>
                            <?php echo safe($account["full_name"]); ?>
                        </td>

                        <td>
                            <?php echo safe($account["email"]); ?>
                        </td>

                        <td>
                            <?php echo safe($account["city"]); ?>
                        </td>

                        <td>
                            <?php echo safe($account["role"]); ?>
                        </td>

                        <!-- ACCOUNT STATUS -->
                        <td>

                            <?php if ($status === "active") { ?>

                                <span class="account-active">
                                    Active
                                </span>

                            <?php } else { ?>

                                <span class="account-suspended">
                                    Suspended
                                </span>

                            <?php } ?>

                        </td>

                        <!-- MODERATION ACTION -->
                        <td>

                            <?php if ($isProtected) { ?>

                                <span class="account-protected">
                                    Protected Admin
                                </span>

                            <?php } else { ?>

                                <form
                                    method="POST"
                                    action="admin_user_action.php"
                                    onsubmit="return confirm('Change this account status?');"
                                >

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?php echo $targetId; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="admin_user_token"
                                        value="<?php
                                            echo safe(
                                                $_SESSION["admin_user_token"]
                                            );
                                        ?>"
                                    >

                                    <?php if ($status === "active") { ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="suspend"
                                        >

                                        <button
                                            type="submit"
                                            class="user-admin-button suspend-button"
                                        >
                                            Suspend
                                        </button>

                                    <?php } else { ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="restore"
                                        >

                                        <button
                                            type="submit"
                                            class="user-admin-button restore-button"
                                        >
                                            Restore
                                        </button>

                                    <?php } ?>

                                </form>

                            <?php } ?>

                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

        <p>
            Suspension blocks account access but does
            not delete existing events or registrations.
        </p>

        <a href="dashboard.php" class="dashboard-link">
            Back to Admin Dashboard
        </a>

    </main>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>

</body>
</html>
