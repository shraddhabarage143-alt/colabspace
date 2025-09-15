<?php
// login.php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json'); // Return JSON response

    // Database connection
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db = "colabspace";

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database connection failed"]);
        exit();
    }

    // Get POST data and sanitize
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT uid, name, email, password FROM users WHERE name = ? AND email = ?");
    $stmt->bind_param("ss", $name, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            echo json_encode([
                "success" => true,
                "uid" => $user['uid'],
                "name" => $user['name'],
                "email" => $user['email']
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid password"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "User  not found. Please register first."]);
    }

    $stmt->close();
    $conn->close();
    exit(); // IMPORTANT: stop further execution so HTML below is NOT sent on POST
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Billing SaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
    <div class="card shadow-sm p-4" style="width: 100%; max-width: 400px;">
        <h3 class="text-center mb-3">Login</h3>
        <form id="loginForm">
            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" placeholder="John Doe" required />
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" required />
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required />
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <div class="text-center mt-3">
            <a href="register.php">Request access</a>
        </div>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                localStorage.setItem('uid', data.uid);
                localStorage.setItem('name', data.name);
                localStorage.setItem('email', data.email); 
                localStorage.setItem('accountCreated', true); 

                alert('Login successful! Redirecting...');
                window.location.href = 'entry.php'; // change as needed
            } else {
                alert(data.message);
            }
        } catch (error) {
            alert('An error occurred. Please try again.');
            console.error(error);
        }
    });
    </script>
</body>
</html>
