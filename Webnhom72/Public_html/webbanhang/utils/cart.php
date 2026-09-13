<?php
require_once('../../Private/security/session.php');
session_start();
require_once('../database/dbhelper.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../admin/authen/loginregister.php');
    exit;
}

$conn = getDB();
$userId = (int)$_SESSION['user_id'];
$error = '';

$cartStatement = mysqli_prepare($conn, 'SELECT id FROM `cart` WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($cartStatement, 'i', $userId);
mysqli_stmt_execute($cartStatement);
mysqli_stmt_bind_result($cartStatement, $cartId);
$hasCart = mysqli_stmt_fetch($cartStatement);
mysqli_stmt_close($cartStatement);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasCart) {
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'remove' && $productId > 0) {
        $statement = mysqli_prepare($conn, 'DELETE FROM `cart_item` WHERE cart_id = ? AND product_id = ? LIMIT 1');
        mysqli_stmt_bind_param($statement, 'ii', $cartId, $productId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    } elseif ($action === 'clear') {
        $statement = mysqli_prepare($conn, 'DELETE FROM `cart_item` WHERE cart_id = ?');
        mysqli_stmt_bind_param($statement, 'i', $cartId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }

    header('Location: cart.php');
    exit;
}

$cartItems = [];
if ($hasCart) {
    $itemsStatement = mysqli_prepare($conn, 'SELECT ci.product_id, p.title, p.price, p.thumbnail FROM `cart_item` ci INNER JOIN `product` p ON p.id = ci.product_id WHERE ci.cart_id = ? AND p.deleted = 0 ORDER BY ci.id');
    mysqli_stmt_bind_param($itemsStatement, 'i', $cartId);
    mysqli_stmt_execute($itemsStatement);
    $itemsResult = mysqli_stmt_get_result($itemsStatement);
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $cartItems[] = $row;
    }
    mysqli_free_result($itemsResult);
    mysqli_stmt_close($itemsStatement);
}

$cartTotal = 0;
foreach ($cartItems as $item) {
    $cartTotal += (float)$item['price'];
}

$utilsPageTitle = 'Giỏ hàng | KhoaiLaptop';
require_once('../assets/header.php');
?>
<main class="container utils-page">
    <a class="back-link" href="index.php">← Tiếp tục mua sắm</a>
    <div class="checkout-heading">
        <p class="eyebrow">CART</p>
        <h1>Giỏ hàng của bạn</h1>
        <p>Các sản phẩm bạn đã chọn được lưu trong tài khoản.</p>
    </div>

    <?php if (!$cartItems): ?>
        <p>Giỏ hàng đang trống. <a href="index.php#shop">Quay lại mua sắm</a></p>
    <?php else: ?>
        <div class="cart-page-list">
            <?php foreach ($cartItems as $item): ?>
                <div class="cart-row">
                    <?php if (!empty($item['thumbnail'])): ?>
                        <img src="<?php echo htmlspecialchars($item['thumbnail'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                    <?php endif; ?>
                    <div>
                        <h3><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo number_format((float)$item['price'], 0, ',', '.'); ?> đ</p>
                    </div>
                    <form method="post">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?php echo (int)$item['product_id']; ?>">
                        <button class="close-button" type="submit" aria-label="Xóa sản phẩm">×</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="cart-footer">
            <div>
                <span>Tổng cộng</span>
                <strong><?php echo number_format($cartTotal, 0, ',', '.'); ?> đ</strong>
            </div>
            <div>
                <form method="post">
                    <input type="hidden" name="action" value="clear">
                    <button class="secondary-button" type="submit">Xóa giỏ hàng</button>
                </form>
                <a class="primary-button" href="checkout.php">Thanh toán <span>↗</span></a>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require_once('../assets/footer.php'); ?>
