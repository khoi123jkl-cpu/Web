<?php
require_once(__DIR__ . '/../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../database/dbhelper.php');

$conn = getDB();
$orderId = (int)($_GET['order_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$order = null;

if ($orderId > 0 && $userId > 0) {
	$hasOrderCodeCol = false;
	$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM `order` LIKE 'order_code'");
	if ($colCheck && mysqli_num_rows($colCheck) > 0) {
		$hasOrderCodeCol = true;
		mysqli_free_result($colCheck);
	}

	if ($hasOrderCodeCol) {
		$statement = mysqli_prepare($conn, 'SELECT id, order_code, delivery_pin, total_money, order_date FROM `order` WHERE id = ? AND user_id = ? LIMIT 1');
	} else {
		$statement = mysqli_prepare($conn, 'SELECT id, total_money, order_date FROM `order` WHERE id = ? AND user_id = ? LIMIT 1');
	}

	if ($statement) {
		mysqli_stmt_bind_param($statement, 'ii', $orderId, $userId);
		mysqli_stmt_execute($statement);
		$result = mysqli_stmt_get_result($statement);
		$order = mysqli_fetch_assoc($result);
		mysqli_free_result($result);
		mysqli_stmt_close($statement);
	}
}

$displayCode = !empty($order['order_code']) ? $order['order_code'] : ('KL-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT));
$deliveryPin = !empty($order['delivery_pin']) ? $order['delivery_pin'] : null;

$utilsPageTitle = 'Đặt hàng thành công | KhoaiLaptop';
require_once(__DIR__ . '/../assets/header.php');
?>
	<main class="container utils-page utils-page--narrow utils-page--center complete-page">
		<div class="complete-panel" style="max-width: 640px; margin: 0 auto; text-align: center; padding: 40px 24px;">
			<div style="width: 64px; height: 64px; margin: 0 auto 16px; background: #ecfdf5; color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px;">✓</div>
			<p class="eyebrow" style="color: #059669; font-weight: 700; letter-spacing: 1.5px;">ĐẶT HÀNG THÀNH CÔNG</p>
			<h1 style="margin-top: 8px;">Cảm ơn bạn đã mua sắm!</h1>
			<p style="color: #4b5563; font-size: 15px;">Đơn hàng của bạn đã được tiếp nhận và đang được xử lý.</p>

			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 24px 0; text-align: left;">
				<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px;">
					<span style="color: #64748b; font-size: 14px;">Mã đơn hàng:</span>
					<strong style="font-size: 16px; color: #0f172a; font-family: monospace;">#<?php echo htmlspecialchars($displayCode, ENT_QUOTES, 'UTF-8'); ?></strong>
				</div>
				<?php if ($deliveryPin): ?>
				<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px;">
					<span style="color: #64748b; font-size: 14px;">Mã PIN đối chiếu shipper:</span>
					<span style="font-size: 20px; font-weight: 800; color: #b45309; background: #fef3c7; padding: 2px 10px; border-radius: 6px; letter-spacing: 2px; font-family: monospace;">
						<?php echo htmlspecialchars($deliveryPin, ENT_QUOTES, 'UTF-8'); ?>
					</span>
				</div>
				<?php endif; ?>
				<?php if (!empty($order['total_money'])): ?>
				<div style="display: flex; justify-content: space-between; align-items: center;">
					<span style="color: #64748b; font-size: 14px;">Tổng thanh toán:</span>
					<strong style="font-size: 16px; color: #059669;"><?php echo number_format((float)$order['total_money'], 0, ',', '.'); ?> đ</strong>
				</div>
				<?php endif; ?>
			</div>

			<div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px; margin: 0 0 24px; text-align: left;">
				<p style="margin: 0 0 6px; font-size: 13px; font-weight: 700; color: #92400e;">
					🛡️ LƯU Ý BẢO MẬT & PHÒNG CHỐNG LỪA ĐẢO:
				</p>
				<p style="margin: 0; font-size: 13px; line-height: 1.5; color: #78350f;">
					Khi nhân viên giao hàng liên hệ hoặc đến nơi, vui lòng yêu cầu shipper đọc đúng <strong>Mã đơn hàng</strong><?php echo $deliveryPin ? ' và <strong>Mã PIN</strong>' : ''; ?> này. Tuyệt đối <strong>KHÔNG</strong> chuyển khoản trước vào tài khoản cá nhân lạ nếu chưa gặp trực tiếp shipper và đồng kiểm tra kiện hàng.
				</p>
			</div>

			<div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
				<a class="primary-button" href="orders.php">Xem đơn hàng của tôi ↗</a>
				<a class="secondary-button" href="index.php#shop" style="display: inline-block; padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #334155; font-weight: 600;">Tiếp tục mua sắm</a>
			</div>
		</div>
	</main>
<?php require_once(__DIR__ . '/../assets/footer.php'); ?>
