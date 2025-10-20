<?php
    include 'connection.php';
    $error = '';
    $success = '';
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Server-side validation
        $valid = true;
        
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) 
        {
            $error = "All fields are required";
            $valid = false;
        } 
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
        {
            $error = "Please enter a valid email address";
            $valid = false;
        } 
        elseif ($password !== $confirm_password) 
        {
            $error = "Passwords do not match";
            $valid = false;
        } 
        elseif (strlen($password) < 8) 
        {
            $error = "Password must be at least 8 characters long";
            $valid = false;
        }
        
        if ($valid) 
        {
            // Check if username already exists in users and email already exists in login
            $check_user_query = "SELECT * FROM users WHERE username = ?";
            $stmt = $conn->prepare($check_user_query);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $user_result = $stmt->get_result();

            $check_email_query = "SELECT * FROM login WHERE email = ?";
            $stmt2 = $conn->prepare($check_email_query);
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $email_result = $stmt2->get_result();

            if ($user_result->num_rows > 0) 
            {
                $error = "Username already exists";
            } 
            elseif ($email_result->num_rows > 0) 
            {
                $error = "Email already exists";
            } 
            else 
            {
                // Insert username into users (role as user)
                $role = 'user';
                $insert_user_query = "INSERT INTO users (username, role) VALUES (?, ?)";
                $stmt = $conn->prepare($insert_user_query);
                $stmt->bind_param("ss", $username, $role);
                if ($stmt->execute()) 
                {
                    $user_id = $conn->insert_id;
                    // Insert email and password into login (user_id FK)
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_login_query = "INSERT INTO login (user_id, email, password_hash) VALUES (?, ?, ?)";
                    $stmt2 = $conn->prepare($insert_login_query);
                    $stmt2->bind_param("iss", $user_id, $email, $hashed_password);
                    if ($stmt2->execute()) 
                    {
                        header("Location: login.php");
                        exit();
                    } 
                    else 
                    {
                        $error = "Error saving login info: " . $conn->error;
                    }
                } 
                else 
                {
                    $error = "Error saving user info: " . $conn->error;
                }
            }
            $stmt->close();
            $stmt2->close();
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #ffffff;
            color: #333333;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        }
        .neon-text {
            text-shadow: 0 0 10px rgba(99, 102, 241, 0.2);
        }
        .category-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .category-card:hover {
            transform: translateY(-5px);
            border-color: #6366f1;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.1);
        }
        .btn-primary {
            background: linear-gradient(45deg, #4f46e5, #6366f1);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.2);
        }
        .nav-link {
            position: relative;
            color: #4b5563;
        }
        .nav-link:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background: #4f46e5;
            transition: width 0.3s ease;
        }
        .nav-link:hover {
            color: #1f2937;
        }
        .nav-link:hover:after {
            width: 100%;
        }
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            background-color: white !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        section:first-of-type {
            padding-top: 6rem;
        }
        h1, h2, h3, h4, h5, h6 {
            color: #111827;
        }
        section {
            background-color: #ffffff;
        }
        input {
            background-color: #ffffff !important;
            border: 1px solid #d1d5db !important;
            transition: all 0.3s ease;
        }
        input:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .error {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .success {
            color: #10b981;
            font-size: 1rem;
            margin: 1rem 0;
            text-align: center;
        }
        .error-border {
            border-color: #ef4444 !important;
        }
        .form-container {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .form-container:hover {
            transform: translateY(-5px);
            border-color: #6366f1;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.1);
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-white shadow-sm">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-gamepad text-white"></i>
                    </div>
                    <span class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">NexusGear</span>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="homepage.php" class="nav-link text-gray-600 hover:text-indigo-600">Home</a>
                    <a href="#" class="nav-link text-gray-600 hover:text-indigo-600">Products</a>
                    <a href="#" class="nav-link text-gray-600 hover:text-indigo-600">Categories</a>
                    <a href="#" class="nav-link text-gray-600 hover:text-indigo-600">Deals</a>
                    <a href="#" class="nav-link text-gray-600 hover:text-indigo-600">About</a>
                </div>
                <div class="flex items-center space-x-6">
                    <button class="text-gray-600 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-search text-lg"></i>
                    </button>
                    <button class="text-gray-600 hover:text-indigo-600 relative transition-colors">
                        <i class="fas fa-shopping-cart text-lg"></i>
                        <span class="absolute -top-2 -right-2 bg-indigo-600 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center">0</span>
                    </button>
                    <button class="md:hidden text-gray-600 hover:text-indigo-600 transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section for Signup (form only, centered) -->
    <section class="min-h-screen flex items-center justify-center pt-24 pb-8 bg-gradient-to-b from-white to-gray-50">
        <div class="form-container max-w-sm w-full rounded-xl p-4 shadow-xl bg-white">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold mb-2 neon-text">Create an Account</h2>
                <p class="text-gray-600">Join NexusGear to access exclusive gaming gear and deals</p>
            </div>
            <?php if ($error): ?>
                <div class="error text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="post" id="signupForm" onsubmit="return validateForm()" class="space-y-6">
                <div class="form-group">
                    <input type="text" 
                           name="username" 
                           id="username" 
                           placeholder="Username" 
                           oninput="validateUsername()"
                           class="w-full px-4 py-3 rounded-lg bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900 placeholder-gray-400">
                    <div id="username_error" class="error"></div>
                </div>
                <div class="form-group">
                    <input type="email" 
                           name="email" 
                           id="email" 
                           placeholder="Email"
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                           oninput="validateEmail()"
                           class="w-full px-4 py-3 rounded-lg bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900 placeholder-gray-400">
                    <div id="email_error" class="error"></div>
                </div>
                <div class="form-group">
                    <input type="password" 
                           name="password" 
                           id="password" 
                           placeholder="Password" 
                           oninput="validatePassword()"
                           class="w-full px-4 py-3 rounded-lg bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900 placeholder-gray-400">
                    <div id="password_error" class="error"></div>
                </div>
                <div class="form-group">
                    <input type="password" 
                           name="confirm_password" 
                           id="confirm_password" 
                           placeholder="Confirm Password" 
                           oninput="validateConfirmPassword()"
                           class="w-full px-4 py-3 rounded-lg bg-white border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-gray-900 placeholder-gray-400">
                    <div id="confirm_password_error" class="error"></div>
                </div>
                <button type="submit" class="btn-primary w-full py-3 rounded-lg font-medium text-white hover:shadow-lg transition-all">
                    Sign Up
                </button>
            </form>
            <p class="text-center text-gray-600 mt-6">
                Already have an account? <a href="login.php" class="text-indigo-600 hover:text-indigo-800 transition-colors">Log in</a>
            </p>
        </div>
    </section>

    <script>
        // Track fields the user has interacted with
        const touchedFields = {
            username: false,
            email: false,
            password: false,
            confirm_password: false
        };

        function markTouched(field) {
            touchedFields[field] = true;
        }

        // Validation functions only show errors if the field was touched
        function validateUsername() 
        {
            const username = document.getElementById('username');
            const error = document.getElementById('username_error');
            if (!touchedFields.username) { error.textContent = ''; error.style.display = 'none'; username.classList.remove('error-border'); return true; }
            if (username.value.trim() === '') {
                showError(username, error, 'Username is required');
                return false;
            } else if (username.value.length < 4) {
                showError(username, error, 'Username must be at least 4 characters');
                return false;
            } else if (!/^[a-zA-Z0-9_]+$/.test(username.value)) {
                showError(username, error, 'Only letters, numbers, and underscores allowed');
                return false;
            } else {
                removeError(username, error);
                return true;
            }
        }
        function validateEmail() {
            const email = document.getElementById('email');
            const error = document.getElementById('email_error');
            if (!touchedFields.email) { error.textContent = ''; error.style.display = 'none'; email.classList.remove('error-border'); return true; }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.value.trim() === '') {
                showError(email, error, 'Email is required');
                return false;
            } else if (!emailRegex.test(email.value)) {
                showError(email, error, 'Please enter a valid email');
                return false;
            } else {
                removeError(email, error);
                return true;
            }
        }
        function validatePassword() {
            const password = document.getElementById('password');
            const error = document.getElementById('password_error');
            if (!touchedFields.password) { error.textContent = ''; error.style.display = 'none'; password.classList.remove('error-border'); return true; }
            if (password.value === '') {
                showError(password, error, 'Password is required');
                return false;
            } else if (password.value.length < 8) {
                showError(password, error, 'Password must be at least 8 characters');
                return false;
            } else {
                removeError(password, error);
                return true;
            }
        }
        function validateConfirmPassword() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const error = document.getElementById('confirm_password_error');
            if (!touchedFields.confirm_password) { error.textContent = ''; error.style.display = 'none'; confirmPassword.classList.remove('error-border'); return true; }
            if (confirmPassword.value === '') {
                showError(confirmPassword, error, 'Please confirm your password');
                return false;
            } else if (confirmPassword.value !== password.value) {
                showError(confirmPassword, error, 'Passwords do not match');
                return false;
            } else {
                removeError(confirmPassword, error);
                return true;
            }
        }
        // Hook up input and blur events to mark touch and validate
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').addEventListener('input', function() { markTouched('username'); validateUsername(); });
            document.getElementById('username').addEventListener('blur', function() { markTouched('username'); validateUsername(); });
            document.getElementById('email').addEventListener('input', function() { markTouched('email'); validateEmail(); });
            document.getElementById('email').addEventListener('blur', function() { markTouched('email'); validateEmail(); });
            document.getElementById('password').addEventListener('input', function() { markTouched('password'); validatePassword(); });
            document.getElementById('password').addEventListener('blur', function() { markTouched('password'); validatePassword(); });
            document.getElementById('confirm_password').addEventListener('input', function() { markTouched('confirm_password'); validateConfirmPassword(); });
            document.getElementById('confirm_password').addEventListener('blur', function() { markTouched('confirm_password'); validateConfirmPassword(); });
        });

        // Form validation on submit: mark all as touched
        function validateForm() {
            touchedFields.username = true;
            touchedFields.email = true;
            touchedFields.password = true;
            touchedFields.confirm_password = true;
            const isUsernameValid = validateUsername();
            const isEmailValid = validateEmail();
            const isPasswordValid = validatePassword();
            const isConfirmPasswordValid = validateConfirmPassword();
            return isUsernameValid && isEmailValid && isPasswordValid && isConfirmPasswordValid;
        }
        // Helpers (unchanged)
        function showError(input, errorElement, message) {
            input.classList.add('error-border');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
        function removeError(input, errorElement) {
            input.classList.remove('error-border');
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
    </script>
</body>
</html>