document.getElementById("registerForm").addEventListener("submit", function (e) {

    let valid = true;

    // Form fields
    const fullName = this.full_name;
    const email = this.email;
    const mobile = this.mobile;
    const username = this.username;
    const password = this.password;
    const confirmPassword = this.confirm_password;
    const dob = this.dob;
    const gender = this.gender;
    const terms = this.terms;

    // Clear previous errors
    clearErrors();

    // Full Name
    if (fullName.value.trim() === "") {
        showError("fullNameError", "Full name is required");
        valid = false;
    }

    // Email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email.value.trim())) {
        showError("emailError", "Enter a valid email");
        valid = false;
    }

    // Mobile
    if (!/^\d{10}$/.test(mobile.value.trim())) {
        showError("mobileError", "Mobile must be 10 digits");
        valid = false;
    }

    // Username
    if (!/^[a-zA-Z0-9_]{4,}$/.test(username.value.trim())) {
        showError("usernameError", "Username must be alphanumeric (min 4 chars)");
        valid = false;
    }

    // Password
    const alphanumericRegex = /^[a-zA-Z0-9]*$/;
    if (password.value.length === 0) { // Check empty if required, though native 'required' attribute isn't on input in PHP file
        // PHP has it, JS should too? Logic above implies it.
    }

    // Explicit 8 char max check
    if (password.value.length > 8) {
        showError("passwordError", "Maximum 8 characters allowed");
        password.classList.add("invalid");
        valid = false;
    } else if (!alphanumericRegex.test(password.value)) {
        showError("passwordError", "Only alphanumeric characters allowed");
        password.classList.add("invalid");
        valid = false;
    } else {
        password.classList.remove("invalid");
    }

    // Confirm Password
    if (password.value !== confirmPassword.value) {
        showError("confirmPasswordError", "Passwords do not match");
        valid = false;
    }

    // DOB
    if (dob.value === "") {
        showError("dobError", "Date of birth is required");
        valid = false;
    }

    // Gender
    if (gender.value === "") {
        showError("genderError", "Please select gender");
        valid = false;
    }

    // Terms
    if (!terms.checked) {
        showError("termsError", "Please accept Terms and Conditions");
        valid = false;
    }

    if (!valid) e.preventDefault();
});

// Helper functions
function showError(id, msg) {
    let el = document.getElementById(id);
    if (!el) {
        // fallback to last span in input container
        const input = document.querySelector(`[name='${id.replace('Error', '').toLowerCase()}']`);
        if (input) el = input.parentElement.querySelector(".error-msg");
    }
    if (el) el.innerText = msg;
}

function clearErrors() {
    document.querySelectorAll(".error-msg").forEach(el => el.innerText = "");
    document.querySelectorAll(".invalid").forEach(el => el.classList.remove("invalid"));
}

function togglePassword(id) {
    const field = document.getElementById(id);
    field.type = field.type === "password" ? "text" : "password";
}

// Mobile: allow only numbers
document.querySelector("input[name='mobile']").addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, '');
});

// Real-time Password Validation
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');

passwordInput.addEventListener('input', function () {
    validatePassword(this);
});

// Also validate confirm password when primary changes? 
// Usually better to just validate confirm on its own input or submit, 
// but asking for specific "inline" messages for the password field itself.

function validatePassword(input) {
    const val = input.value;
    const errorId = input.id + "Error";

    // Clear field specific error first
    const errorEl = document.getElementById(errorId);
    if (errorEl) errorEl.innerText = "";
    input.classList.remove("invalid");

    // Max 8 chars check (already handled by maxlength, but good visual check)
    if (val.length > 8) {
        showError(errorId, "Maximum 8 characters allowed");
        input.classList.add("invalid");
        return;
    }

    // Alphanumeric check
    const alphanumericRegex = /^[a-zA-Z0-9]*$/;
    if (!alphanumericRegex.test(val)) {
        showError(errorId, "Only alphanumeric characters allowed");
        input.classList.add("invalid");
    }
}

// Terms checkbox - clear error on check
document.getElementById('terms').addEventListener('change', function () {
    if (this.checked) {
        document.getElementById('termsError').innerText = "";
        // Re-validate on submit will handle uncheck
    }
});

function showError(id, msg) {
    let el = document.getElementById(id);
    if (!el) {
        const input = document.querySelector(`[name='${id.replace('Error', '').toLowerCase()}']`);
        if (input) el = input.parentElement.querySelector(".error-msg");
    }
    if (el) {
        el.innerText = msg;
        // Optionally add invalid class to input associated with this error
        const inputName = id.replace('Error', '');
        // Handle camelCase mapping manually or generic approach? 
        // register.js structure suggests simple mapping.
        // Let's rely on the caller to add 'invalid' class or doing it here safely if possible.
    }
}

// Original showError was:
/*
function showError(id, msg) {
    let el = document.getElementById(id);
    if (!el) {
        // fallback to last span in input container
        const input = document.querySelector(`[name='${id.replace('Error','').toLowerCase()}']`);
        if (input) el = input.parentElement.querySelector(".error-msg");
    }
    if(el) el.innerText = msg;
}
*/

