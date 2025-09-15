<?php
// register.php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "colabspace";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $phone_number = $_POST["phone_number"];
    $speciality = $_POST["speciality"];
    $password = $_POST["password"];
    $confirm = $_POST["confirm"];

    if ($password !== $confirm) {
        $message = "Passwords do not match!";
    } else {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Generate UID (simple random unique id)
        $uid = uniqid("user_");

        // Prepare and bind to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO users (uid, name, email, phone_number, speciality, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $uid, $name, $email, $phone_number, $speciality, $hashedPassword);

        if ($stmt->execute()) {
            echo "<script>
                localStorage.setItem('uid', '$uid');
                localStorage.setItem('name', '$name');
                localStorage.setItem('email', '$email');
                alert('Registration successful!');
                window.location.href='login.html';
            </script>";
            exit();
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - CollabSpace</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
  <div class="card shadow-sm p-4" style="width: 100%; max-width: 400px;">
    <h3 class="text-center mb-3">Register</h3>
    <?php if (!empty($message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST" action="register.php">
      <div class="mb-3">
        <label for="name" class="form-label">Full Name</label>
        <input type="text" class="form-control" id="name" name="name" placeholder="John Doe" required />
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" required />
      </div>
      <div class="mb-3">
        <label for="phone_number" class="form-label">Phone Number</label>
        <input type="text" class="form-control" id="phone_number" name="phone_number" placeholder="123-456-7890" />
      </div>
      <div class="mb-3">
        <label for="speciality" class="form-label">Speciality</label>
        <input type="text" class="form-control" id="speciality" name="speciality" placeholder="Your speciality" />
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required />
      </div>
      <div class="mb-3">
        <label for="confirm" class="form-label">Confirm Password</label>
        <input type="password" class="form-control" id="confirm" name="confirm" placeholder="Confirm Password" required />
      </div>
      <button type="submit" class="btn btn-primary w-100">Register</button>
    </form>
    <div class="text-center mt-3">
      <a href="login.html">Already have an account? Login</a>
    </div>
  </div>
</body>
</html>
