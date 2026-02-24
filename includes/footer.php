</main>

<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <div class="logo"> Melody<span>Masters</span></div>
      <p>Your one-stop destination for premium musical instruments. Trusted by 10,000+ musicians since 2010.</p>
    </div>
    <div class="footer-col">
      <h4>Shop</h4>
      <div class="footer-links">
        <a href="<?php echo BASE_URL; ?>/home.php">All Products</a>
        <a href="<?php echo BASE_URL; ?>/home.php?cat=1">Guitars</a>
        <a href="<?php echo BASE_URL; ?>/home.php?cat=2">Keyboards</a>
        <a href="<?php echo BASE_URL; ?>/home.php?cat=3">Drums</a>
        <a href="<?php echo BASE_URL; ?>/home.php?cat=7">Audio Gear</a>
      </div>
    </div>
    <div class="footer-col">
      <h4>Account</h4>
      <div class="footer-links">
        <?php if (isLoggedIn()): ?>
          <a href="<?php echo BASE_URL; ?>/account.php">My Profile</a>
          <a href="<?php echo BASE_URL; ?>/orders.php">My Orders</a>
          <a href="<?php echo BASE_URL; ?>/cart.php">Cart</a>
        <?php else: ?>
          <a href="<?php echo BASE_URL; ?>/login.php">Login</a>
          <a href="<?php echo BASE_URL; ?>/register.php">Create Account</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="footer-col">
      <h4>Support</h4>
      <div class="footer-links">
        <a href="#">FAQ</a>
        <a href="#">Shipping Policy</a>
        <a href="#">Returns</a>
        <a href="#">Contact Us</a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?php echo date('Y'); ?> Melody Masters. All rights reserved.</span>
    <span>Free shipping on orders over $100 </span>
  </div>
</footer>

<script>
// Mobile nav toggle
const hamburger = document.getElementById('hamburger');
const navLinks  = document.getElementById('navLinks');
if (hamburger) {
  hamburger.addEventListener('click', () => {
    navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
    navLinks.style.flexDirection = 'column';
    navLinks.style.position = 'absolute';
    navLinks.style.top = '70px';
    navLinks.style.left = '0';
    navLinks.style.right = '0';
    navLinks.style.background = '#0f172a';
    navLinks.style.padding = '12px 24px';
    navLinks.style.zIndex = '999';
  });
}
</script>
</body>
</html>
