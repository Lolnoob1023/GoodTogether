
// ==================================
// GOOD TOGETHER - ACCOUNT FORMS
// ==================================

// Get the Sign-Up form.
const signupForm = document.getElementById("signupForm");

// Check whether we are on the Sign-Up page.
if (signupForm) {

    // Run this function when the form is submitted.
    signupForm.addEventListener("submit", function(event) {

        // Stop the form from sending data for now.
        event.preventDefault();

        // Get both passwords.
        const password = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirmPassword").value;

        // Find where we will display a message.
        const message = document.getElementById("signupMessage");

        // Check whether passwords are the same.
        if (password !== confirmPassword) {

            message.textContent = "Passwords do not match.";
            message.className = "form-message error";

        } else {

            message.textContent =
                "Form checks passed! Account creation will be added when we connect PHP and MySQL.";

            message.className = "form-message";
        }

    });
}


// Get the Login form.
const loginForm = document.getElementById("loginForm");

// Check whether we are on the Login page.
if (loginForm) {

    loginForm.addEventListener("submit", function(event) {

        // Stop normal form submission.
        event.preventDefault();

        // Display a test message.
        const message = document.getElementById("loginMessage");

        message.textContent =
            "Login form is working. Real login will be added with PHP and MySQL.";

        message.className = "form-message";

    });
}
