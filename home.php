<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Home - Melody Masters</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Poppins', sans-serif;
}

body{
    background:#f5f7fa;
}

/* Navbar */
.navbar{
    background:#111827;
    padding:15px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    color:white;
}

.navbar a{
    color:white;
    text-decoration:none;
    margin-left:20px;
    font-weight:500;
}

.navbar a:hover{
    color:#ff4d4d;
}

/* Hero */
.hero{
    height:400px;
    background:url('assets/images/bg1.jpg') center/cover;
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    text-align:center;
    position:relative;
}

.hero::after{
    content:"";
    position:absolute;
    inset:0;
    background:rgba(0,0,0,0.5);
}

.hero-content{
    position:relative;
    z-index:2;
}

.hero h1{
    font-size:40px;
    margin-bottom:10px;
}

.hero p{
    font-size:18px;
}

/* Section */
.section{
    padding:60px 40px;
    text-align:center;
}

.products{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(250px,1fr));
    gap:20px;
    margin-top:30px;
}

.card{
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,0.1);
}

.card h3{
    margin-bottom:10px;
}

.footer{
    background:#111827;
    color:white;
    text-align:center;
    padding:15px;
    margin-top:40px;
}
</style>
</head>

<body>

<div class="navbar">
    <div><strong>Melody Masters 🎵</strong></div>
    <div>
        <a href="#">Home</a>
        <a href="#">Shop</a>
        <a href="#">Cart</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="hero">
    <div class="hero-content">
        <h1>Welcome <?php echo $_SESSION['user_name']; ?> 🎶</h1>
        <p>Discover Your Perfect Instrument</p>
    </div>
</div>

<div class="section">
    <h2>Featured Instruments</h2>

    <div class="products">
        <div class="card">
            <h3>Acoustic Guitar</h3>
            <p>$199.00</p>
        </div>

        <div class="card">
            <h3>Electric Piano</h3>
            <p>$499.00</p>
        </div>

        <div class="card">
            <h3>Drum Set</h3>
            <p>$799.00</p>
        </div>
    </div>
</div>

<div class="footer">
    © 2026 Melody Masters. All Rights Reserved.
</div>

</body>
</html>