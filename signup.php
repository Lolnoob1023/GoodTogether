
<?php
// ======================================
// GOOD TOGETHER - USER REGISTRATION
// ======================================

// Start a PHP session.
session_start();

// Connect to our MySQL database.
require "database.php";

// Variables for form messages.
$error = "";
$success = "";

// Variables for user input.
$name = "";
$email = "";
$city = "";
$role = "";

// Create a security token for the form.
if (!isset($_SESSION["signup_token"])) {
    $_SESSION["signup_token"] = bin2hex(random_bytes(32));
}

// Show a success message after registration.
if (isset($_SESSION["success_message"])) {
    $success = $_SESSION["success_message"];
    unset($_SESSION["success_message"]);
}

// ======================================
// PROCESS THE SIGN-UP FORM
// ======================================

// Check whether the user submitted the form.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get the information from the form.
    $name = trim($_POST["full_name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $city = $_POST["city"] ?? "";
    $role = $_POST["role"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Check the security token.
    $token = $_POST["signup_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["signup_token"], $token)) {

        $error = "Invalid form request. Please reload the page.";

    // Check for empty fields.
    } elseif (
        $name === "" ||
        $email === "" ||
        $city === "" ||
        $role === "" ||
        $password === ""
    ) {

        $error = "Please complete all fields.";

    // Check whether the email format is valid.
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    // Check input lengths.
    } elseif (strlen($name) > 100 || strlen($email) > 150) {

        $error = "Name or email is too long.";

    // Check the selected city.
    } elseif (!in_array($city, [
        "Kuala Lumpur",
        "Petaling Jaya",
        "Subang Jaya",
        "Shah Alam"
    ], true)) {

        $error = "Please select a valid city.";

    // Only allow Volunteer or Organizer.
    } elseif ($role !== "volunteer" && $role !== "organizer") {

        $error = "Please select a valid account type.";

    // Password must have at least 8 characters.
    } elseif (strlen($password) < 8) {

        $error = "Password must have at least 8 characters.";

    // Both passwords must match.
    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } else {

        // All form checks passed.
        try {

            // Check whether the email already exists.
            $sql = "SELECT user_id FROM users WHERE email = :email";

            $statement = $pdo->prepare($sql);

            $statement->execute([
                "email" => $email
            ]);

            $existingUser = $statement->fetch();

            if ($existingUser) {

                $error = "This email is already registered.";

            } else {

                // Hash the password before saving.
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // SQL command to insert a new user.
                $sql = "INSERT INTO users
                        (full_name, email, city, role, password_hash)
                        VALUES
                        (:name, :email, :city, :role, :password)";

                // Prepare the SQL command.
                $statement = $pdo->prepare($sql);

                // Insert the user's details.
                $statement->execute([
                    "name" => $name,
                    "email" => $email,
                    "city" => $city,
                    "role" => $role,
                    "password" => $hashedPassword
                ]);

                // Store a success message temporarily.
                $_SESSION["success_message"] =
                    "Account created successfully! Your details are saved in MySQL.";

                // Redirect to avoid submitting the form twice.
                header("Location: signup.php");
                exit;
            }

        } catch (PDOException $errorDetails) {

            // Record technical details in the server log.
            error_log($errorDetails->getMessage());

            // Show a simple error to the user.
            $error = "Unable to create account. Please try again.";
        }
    }
}

// Safely display text inside HTML.
function safe($text) {
    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up | Good Together</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="page">

    <!-- WEBSITE HEADER -->
    <header>
        <h1>Good Together</h1>
        <p>People helping people, close to home.</p>
    </header>

    <!-- NAVIGATION -->
    
<?php require "navigation.php"; ?>


    <!-- SIGN-UP FORM -->
    <main class="auth-container">

        <div class="auth-box">

            <h2>Create an Account</h2>

            <p>Join Good Together and help your community.</p>

            <!-- PHP FORM MESSAGES -->
            <?php if ($error !== "") { ?>
                <p class="form-message error">
                    <?php echo safe($error); ?>
                </p>
            <?php } ?>

            <?php if ($success !== "") { ?>
                <p class="form-message">
                    <?php echo safe($success); ?>
                </p>
            <?php } ?>

            <!-- REGISTRATION FORM -->
            <form id="signupForm" method="POST" action="signup.php">

                <!-- Security token -->
                <input
                    type="hidden"
                    name="signup_token"
                    value="<?php echo safe($_SESSION['signup_token']); ?>"
                >

                <!-- FULL NAME -->
                <label for="fullName">Full Name</label>

                <input
                    type="text"
                    id="fullName"
                    name="full_name"
                    value="<?php echo safe($name); ?>"
                    placeholder="Enter your full name"
                    maxlength="100"
                    required
                >

                <!-- EMAIL -->
                <label for="signupEmail">Email Address</label>

                <input
                    type="email"
                    id="signupEmail"
                    name="email"
                    value="<?php echo safe($email); ?>"
                    placeholder="Enter your email"
                    maxlength="150"
                    required
                >

                <!-- CITY -->
                <label for="userCity">Your City</label>

                <select id="userCity" name="city" required>

                    <option value="">Select your city</option>

                    <?php
                    $cities = [
                        "Kuala Lumpur",
                        "Petaling Jaya",
                        "Subang Jaya",
                        "Shah Alam"
                    ];

                    foreach ($cities as $cityOption) {
                    ?>
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

                <!-- ACCOUNT TYPE -->
                <label for="role">Register As</label>

                <select id="role" name="role" required>
                    <option value="">Select account type</option>

                    <option value="volunteer"
                        <?php if ($role === "volunteer") echo "selected"; ?>>
                        Volunteer
                    </option>

                    <option value="organizer"
                        <?php if ($role === "organizer") echo "selected"; ?>>
                        Event Organizer
                    </option>
                </select>

                <!-- PASSWORD -->
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    placeholder="At least 8 characters"
                    autocomplete="new-password"
                    required
                >

                <!-- CONFIRM PASSWORD -->
                <label for="confirmPassword">
                    Confirm Password
                </label>

                <input
                    type="password"
                    id="confirmPassword"
                    name="confirm_password"
                    minlength="8"
                    placeholder="Enter password again"
                    autocomplete="new-password"
                    required
                >

                <!-- SUBMIT -->
                <button type="submit">
                    Create Account
                </button>

                <p id="signupMessage" class="form-message error"
                   aria-live="polite"></p>

            </form>

            <p class="auth-link">
                Already have an account?
                <a href="login.php">Login Here</a>
            </p>

        </div>

    </main>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 Good Together</p>
    </footer>

</div>

<!-- SIMPLE JAVASCRIPT VALIDATION -->
<script>

// Get the Sign-Up form.
const signupForm = document.getElementById("signupForm");

// Check the passwords before submitting.
signupForm.addEventListener("submit", function(event) {

    const password = document.getElementById("password").value;

    const confirmPassword =
        document.getElementById("confirmPassword").value;

    if (password !== confirmPassword) {

        // Only stop submission when passwords do not match.
        event.preventDefault();

        document.getElementById("signupMessage").textContent =
            "Passwords do not match.";
    }
});

</script>

</body>
</html>
