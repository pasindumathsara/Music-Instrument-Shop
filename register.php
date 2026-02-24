<?php
session_start();
include("includes/db.php");

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name  = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "Email already exists!";
    } else {

        $role = "customer"; // default role

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);

        if ($stmt->execute()) {
            header("Location: login.php");
            exit();
        } else {
            $message = "Something went wrong!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', sans-serif;
}

body{
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
   background: #f1f5f9;
}

/* Main Container */
.container{
    width:900px;
    max-width:95%;
    height:580px;
    background:#fff;
    border-radius:30px;
    overflow:hidden;
    display:flex;
    box-shadow:0 25px 60px rgba(0,0,0,0.4);
}

/* Left Section */
.left{
    flex:1;
    background:url('assets/images/bg1.jpg') no-repeat center center/cover;
    position:relative;
    color:white;
    padding:40px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
}

.left::after{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.6);
}

.left-content{
    position:relative;
    z-index:2;
}

.left h2{
    font-size:28px;
    font-weight:600;
}

.left p{
    font-size:14px;
    opacity:0.8;
}

/* Right Section */
.right{
    flex:1;
    padding:60px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.right h3{
    font-size:18px;
    margin-bottom:10px;
    color:#444;
}

.right h1{
    font-size:36px;
    margin-bottom:10px;
}

.right p{
    color:#777;
    margin-bottom:30px;
}

/* Inputs */
.input-group{
    margin-bottom:18px;
}

.input-group input{
    width:100%;
    padding:14px;
    border-radius:8px;
    border:1px solid #ddd;
    font-size:14px;
}

.input-group input:focus{
    border-color:#ef4444;
    outline:none;
}

/* Button */
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:25px;
    background:#ef4444;
    color:#fff;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
}

button:hover{
    background:#dc2626;
}

/* Message */
.message{
    margin-bottom:15px;
    color:red;
    font-size:14px;
}

/* Link */
.login-link{
    margin-top:20px;
    font-size:14px;
}

.login-link a{
    color:#ef4444;
    text-decoration:none;
}

.login-link a:hover{
    text-decoration:underline;
}

/* Responsive */
@media(max-width:900px){
    .container{
        flex-direction:column;
        height:auto;
    }
    .left{
        height:250px;
    }
}
</style>
</head>

<body>

<div class="container">

    <!-- LEFT PANEL -->
    <div class="left">
        <div class="left-content">
            <h3>Find Your Sound</h3>
        </div>

        <div class="profile">
           
            <div>
                <strong>Melody Masters</strong><br>
                <small>Trusted by 10,000+ Musicians Est. 2010</small>
            </div>
        </div>
    </div>

    <!-- Right Side -->
    <div class="right">

        <h3>Melody Masters</h3>
        <h1>Create Account</h1>
        <p>Join the Melody Masters community</p>

        <?php if($message != ""): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="input-group">
                <input type="text" name="name" placeholder="Full Name" required>
            </div>

            <div class="input-group">
                <input type="email" name="email" placeholder="Email Address" required>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit">Sign Up</button>

        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login</a>
        </div>

    </div>

</div>

</body>
</html>