<?php
session_start();
include("includes/db.php"); // make sure db connection file eka thiyenawa

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        // Check hashed password
        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            header("Location: home.php"); // redirect to home page
            exit();

        } else {
            $error = "Invalid Email or Password!";
        }

    } else {
        $error = "Invalid Email or Password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Poppins', sans-serif;
}

body{
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: #f1f5f9;
}

.container{
    width:950px;
    height:550px;
    background:#fff;
    border-radius:25px;
    display:flex;
    overflow:hidden;
    box-shadow:0 20px 40px rgba(0,0,0,0.4);
}

/* LEFT SIDE */
.left{
    width:50%;
    background:url('assets/images/bg1.jpg');
    position:relative;
    color:white;
    padding:30px;
}

.left::after{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.5);
}

.left-content{
    position:relative;
    z-index:2;
}

.left h3{
    margin-bottom:20px;
}

.profile{
    position:absolute;
    bottom:30px;
    left:30px;
    display:flex;
    align-items:center;
    gap:10px;
}

.profile img{
    width:45px;
    height:45px;
    border-radius:50%;
}

/* RIGHT SIDE */
.right{
    width:50%;
    padding:50px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.logo{
    font-weight:600;
    font-size:20px;
    margin-bottom:20px;
}

.right h1{
    font-size:28px;
    margin-bottom:5px;
}

.right p{
    color:#777;
    margin-bottom:25px;
}

form input{
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border-radius:8px;
    border:1px solid #ddd;
    outline:none;
    transition:0.3s;
}

form input:focus{
    border-color:#ff4d4d;
}

button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:25px;
    background:#ff4d4d;
    color:white;
    font-weight:600;
    cursor:pointer;
    transition:0.3s;
}

button:hover{
    background:#e63946;
}

.error{
    color:red;
    margin-bottom:10px;
}

.signup{
    text-align:center;
    margin-top:15px;
}

.signup a{
    color:#ff4d4d;
    text-decoration:none;
    font-weight:600;
}

@media(max-width:900px){
    .container{
        flex-direction:column;
        width:90%;
        height:auto;
    }
    .left{
        height:200px;
        width:100%;
    }
    .right{
        width:100%;
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

    <!-- RIGHT PANEL -->
    <div class="right">

        <div class="logo">Melody Masters</div>

        <h1>Hi Musician</h1>
        <p>Welcome to Melody Masters</p>

        <?php if($error != "") { ?>
            <div class="error"><?php echo $error; ?></div>
        <?php } ?>

        <form method="POST" action="">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

        <div class="signup">
            Don't have an account? <a href="register.php">Sign up</a>
        </div>

    </div>

</div>

</body>
</html>