<?php
require_once(__DIR__ . '/../../../Private/security/session.php');
session_start();
require_once('../database/dbhelper.php');
require_once(__DIR__ . '/../../../Private/security/csrf.php');

$conn = getDB();
authRequireUser($conn);
$error = '';
$userId = (int)$_SESSION['user_id'];

$cartStatement = mysqli_prepare($conn, 'SELECT id FROM `cart` WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($cartStatement, 'i', $userId);
mysqli_stmt_execute($cartStatement);
mysqli_stmt_bind_result($cartStatement, $cartId);
$hasCart = mysqli_stmt_fetch($cartStatement);
mysqli_stmt_close($cartStatement);

$cartItems = [];
if ($hasCart) {
	$itemsStatement = mysqli_prepare($conn, 'SELECT ci.product_id, p.title, p.price, ci.id AS cart_item_id FROM `cart_item` ci INNER JOIN `product` p ON p.id = ci.product_id WHERE ci.cart_id = ? AND p.deleted = 0');
	mysqli_stmt_bind_param($itemsStatement, 'i', $cartId);
	mysqli_stmt_execute($itemsStatement);
	$itemsResult = mysqli_stmt_get_result($itemsStatement);
	while ($row = mysqli_fetch_assoc($itemsResult)) {
		$cartItems[] = $row;
	}
	mysqli_free_result($itemsResult);
	mysqli_stmt_close($itemsStatement);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	adminVerifyCsrf();
	$fullname = trim($_POST['fullname'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$address = trim($_POST['address'] ?? '');

	if (!$cartItems) {
		$error = 'Giỏ hàng đang trống.';
	} elseif ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $address === '') {
		$error = 'Vui lòng điền đầy đủ thông tin nhận hàng.';
	} else {
		$totalMoney = 0;
		foreach ($cartItems as $item) {
			$totalMoney += (int)$item['price'];
		}

		mysqli_begin_transaction($conn);
		try {
			$result = mysqli_query($conn, 'SELECT COALESCE(MAX(id), -1) + 1 AS next_id FROM `order`');
			$orderId = (int)mysqli_fetch_assoc($result)['next_id'];
			mysqli_free_result($result);

			$orderStatement = mysqli_prepare($conn, 'INSERT INTO `order` (id, user_id, fullname, email, phone_number, address, note, order_date, status, total_money) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 1, ?)');
			$note = '';
			mysqli_stmt_bind_param($orderStatement, 'iisssssi', $orderId, $userId, $fullname, $email, $phone, $address, $note, $totalMoney);
			mysqli_stmt_execute($orderStatement);
			mysqli_stmt_close($orderStatement);

			foreach ($cartItems as $item) {
				$result = mysqli_query($conn, 'SELECT COALESCE(MAX(id), -1) + 1 AS next_id FROM `order_detail`');
				$detailId = (int)mysqli_fetch_assoc($result)['next_id'];
				mysqli_free_result($result);
				$price = (int)$item['price'];
				$quantity = 1;
				$detailTotal = $price;
				$detailStatement = mysqli_prepare($conn, 'INSERT INTO `order_detail` (id, order_id, product_id, price, num, total_money) VALUES (?, ?, ?, ?, ?, ?)');
				mysqli_stmt_bind_param($detailStatement, 'iiiiii', $detailId, $orderId, $item['product_id'], $price, $quantity, $detailTotal);
				mysqli_stmt_execute($detailStatement);
				mysqli_stmt_close($detailStatement);
			}

			$clearStatement = mysqli_prepare($conn, 'DELETE FROM `cart_item` WHERE cart_id = ?');
			mysqli_stmt_bind_param($clearStatement, 'i', $cartId);
			mysqli_stmt_execute($clearStatement);
			mysqli_stmt_close($clearStatement);
			mysqli_commit($conn);
			header('Location: complete.php?order_id=' . $orderId);
			exit;
		} catch (Throwable $exception) {
			mysqli_rollback($conn);
			$error = 'Không thể tạo đơn hàng. Vui lòng thử lại.';
		}
	}
}
$utilsPageTitle = 'Thanh toán | KhoaiLaptop';
$utilsExtraCss = 'checkout.css';
require_once('../assets/header.php');
?>
	<main class="checkout-page container">
		<a class="back-link" href="index.php">← Tiếp tục mua sắm</a>
		<div class="checkout-heading">
			<p class="eyebrow">CHECKOUT</p>
			<h1>Hoàn tất đơn hàng</h1>
			<p>Điền thông tin nhận hàng để chúng tôi chuẩn bị đơn của bạn.</p>
		</div>
		<?php if ($error !== ''): ?><p class="notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
		<?php if (!$cartItems): ?><p>Giỏ hàng đang trống. <a href="index.php">Quay lại mua sắm</a></p><?php endif; ?>
		<div class="checkout-layout">
			<form class="checkout-form" method="post">
				<?php echo adminCsrfField(); ?>
				<div class="form-section">
					<h2>Thông tin nhận hàng</h2>
					<label>Họ và tên<input name="fullname" required value="<?php echo htmlspecialchars($_SESSION['fullname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
					<label>Email<input type="email" name="email" required value="<?php echo htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
					<label>Số điện thoại<input name="phone" required></label>
					<label>Địa chỉ nhận hàng<textarea name="address" rows="4" required></textarea></label>
				</div>
				<button class="checkout-submit" type="submit" <?php echo !$cartItems ? 'disabled' : ''; ?>>Xác nhận đặt hàng <span>↗</span></button>
			</form>

			<aside class="order-summary">
				<h2>Đơn hàng của bạn</h2>
				<?php $checkoutTotal = 0; ?>
				<?php foreach ($cartItems as $item): ?>
					<?php $checkoutTotal += (int)$item['price']; ?>
					<div class="summary-row">
						<span><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></span>
						<strong><?php echo number_format((int)$item['price'], 0, ',', '.'); ?> đ</strong>
					</div>
				<?php endforeach; ?>
				<div class="summary-total"><span>Tổng cộng</span><strong><?php echo number_format($checkoutTotal, 0, ',', '.'); ?> đ</strong></div>
			</aside>
		</div>
	</main>
<?php require_once('../assets/footer.php'); ?>
