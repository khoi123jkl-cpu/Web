<?php
$utilsPageTitle = $utilsPageTitle ?? 'KhoaiLaptop | Laptop & PC gear';
$utilsExtraCss = $utilsExtraCss ?? '';

if (session_status() === PHP_SESSION_NONE) {
    require_once('../../Private/security/session.php');
    session_start();
}

$cartItemCount = 0;
if (isset($_SESSION['user_id'])) {
    require_once('../database/dbhelper.php');
    $headerConn = getDB();
    $cartCountStatement = mysqli_prepare($headerConn, 'SELECT COUNT(*) FROM `cart_item` ci INNER JOIN `cart` c ON c.id = ci.cart_id WHERE c.user_id = ?');
    $headerUserId = (int)$_SESSION['user_id'];
    mysqli_stmt_bind_param($cartCountStatement, 'i', $headerUserId);
    mysqli_stmt_execute($cartCountStatement);
    mysqli_stmt_bind_result($cartCountStatement, $cartItemCount);
    mysqli_stmt_fetch($cartCountStatement);
    mysqli_stmt_close($cartCountStatement);
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($utilsPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Cửa hàng laptop, điện thoại và phụ kiện KhoaiLaptop.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&amp;family=Space+Grotesk:wght@500;600;700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/index.css?v=3">
    <link rel="stylesheet" href="../assets/css/utils.css">
    <?php if ($utilsExtraCss !== ''): ?><link rel="stylesheet" href="../assets/css/<?php echo htmlspecialchars($utilsExtraCss, ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?>
</head>
<body>
<header class="site-header">
    <div class="announcement">Qúa đẳngs cấp <span>•</span> Không hoàn tiền</div>
    <nav class="nav container" aria-label="Main navigation">
        <a class="brand" href="index.php#top" aria-label="KhoaiLaptop home">
            <span class="brand-mark">K</span>
            <span>KHOAILAPTOP<span class="brand-light">SUPPLY</span></span>
        </a>
        <div class="nav-links">
            <a href="index.php#shop">Shop</a>
            <a href="category.php">Categories</a>
            <a href="index.php#about">Our standard</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="nav-actions">
            <div class="account-dropdown">
                <a class="account-link" href="../admin/authen/loginregister.php">Account</a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="orders.php">Xem đơn hàng</a>
                    <a class="dropdown-item" href="../admin/authen/logout.php">Đăng xuất</a>
                </div>
            </div>
            <form action="index.php" method="get" class="search-form" data-search-form>
                <input type="text" name="q" placeholder="Tìm kiếm mặt hàng..." value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Tìm kiếm mặt hàng" autocomplete="off" data-search-input>
                <button type="submit" class="icon-button" aria-label="Search">⌕</button>
                <div class="search-results" data-search-results></div>
            </form>
            <?php if (!empty($showCartButton)): ?>
                <a class="cart-button" href="cart.php" aria-label="Open cart">Cart</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
<a class="floating-cart" href="cart.php" aria-label="Mở giỏ hàng, <?php echo (int)$cartItemCount; ?> sản phẩm" aria-hidden="true" tabindex="-1">
    <span class="floating-cart-icon" aria-hidden="true">🛒</span>
    <span class="floating-cart-count"><?php echo (int)$cartItemCount; ?></span>
</a>
<script src="../assets/js/cart-bubble.js" defer></script>
<script src="../assets/js/search.js" defer></script>
