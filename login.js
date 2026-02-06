document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("loginForm");
    const usernameInput = form.username;
    const passwordInput = form.password;
    const usernameError = document.getElementById("usernameError");
    const passwordError = document.getElementById("passwordError");

    // Prevent typing more than 8 characters and validate Alphanumeric
    passwordInput.addEventListener("input", () => {
        let val = passwordInput.value;

        // Max length check (enforce slicing if pasted)
        if (val.length > 8) {
            passwordInput.value = val.slice(0, 8);
            val = passwordInput.value; // update val
        }

        // Inline Validation
        const alphanumericRegex = /^[a-zA-Z0-9]*$/;
        if (!alphanumericRegex.test(val)) {
            passwordError.textContent = "Only alphanumeric characters allowed";
            passwordInput.classList.add("invalid");
        } else {
            passwordError.textContent = "";
            passwordInput.classList.remove("invalid");
        }
    });

    form.addEventListener("submit", (e) => {
        let valid = true;
        usernameError.textContent = "";
        passwordError.textContent = "";

        // Remove invalid classes
        usernameInput.classList.remove("invalid");
        passwordInput.classList.remove("invalid");

        // Username validation
        if (usernameInput.value.trim() === "") {
            usernameError.textContent = "Username is required";
            usernameInput.classList.add("invalid");
            valid = false;
        }

        // Password validation
        const alphanumericRegex = /^[a-zA-Z0-9]*$/;
        if (passwordInput.value.trim() === "") {
            passwordError.textContent = "Password is required";
            passwordInput.classList.add("invalid");
            valid = false;
        } else if (passwordInput.value.length > 8) {
            // Should not happen with slice, but good safeguard
            passwordError.textContent = "Password max 8 characters";
            passwordInput.classList.add("invalid");
            valid = false;
        } else if (!alphanumericRegex.test(passwordInput.value)) {
            passwordError.textContent = "Only alphanumeric characters allowed";
            passwordInput.classList.add("invalid");
            valid = false;
        }

        if (!valid) e.preventDefault();
    });
});

// Toggle password visibility
function togglePassword() {
    const passField = document.getElementById("password");
    passField.type = passField.type === "password" ? "text" : "password";
}
