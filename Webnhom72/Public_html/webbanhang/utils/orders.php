<?php
require_once(__DIR__ . '/../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../database/dbhelper.php');
require_once(__DIR__ . '/../../../Private/security/csrf.php');


$conn = getDB();
authRequireUser($conn);
$userId = (int)$_SESSION['user_id'];
$orders = [];

$hasOrderCodeCol = false;
$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM `order` LIKE 'order_code'");
if ($colCheck && mysqli_num_rows($colCheck) > 0) {
    $hasOrderCodeCol = true;
    mysqli_free_result($colCheck);
}

if ($hasOrderCodeCol) {
    $orderStatement = mysqli_prepare($conn, 'SELECT id, order_code, delivery_pin, fullname, email, phone_number, address, order_date, status, total_money FROM `order` WHERE user_id = ? ORDER BY id DESC');
} else {
    $orderStatement = mysqli_prepare($conn, 'SELECT id, fullname, email, phone_number, address, order_date, status, total_money FROM `order` WHERE user_id = ? ORDER BY id DESC');
}
mysqli_stmt_bind_param($orderStatement, 'i', $userId);
mysqli_stmt_execute($orderStatement);
$orderResult = mysqli_stmt_get_result($orderStatement);

while ($order = mysqli_fetch_assoc($orderResult)) {
    $order['items'] = [];
    $orders[(int)$order['id']] = $order;
}
mysqli_free_result($orderResult);
mysqli_stmt_close($orderStatement);

if ($orders) {
    $orderIds = array_keys($orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));
    $detailStatement = mysqli_prepare($conn, "SELECT od.order_id, od.price, od.num, od.total_money, p.title, p.thumbnail FROM `order_detail` od LEFT JOIN `product` p ON p.id = od.product_id WHERE od.order_id IN ($placeholders) ORDER BY od.id");
    $bindValues = [$types];
    foreach ($orderIds as $key => $orderId) {
        $bindValues[] = &$orderIds[$key];
    }
    mysqli_stmt_bind_param($detailStatement, ...$bindValues);
    mysqli_stmt_execute($detailStatement);
    $detailResult = mysqli_stmt_get_result($detailStatement);

    while ($item = mysqli_fetch_assoc($detailResult)) {
        $orders[(int)$item['order_id']]['items'][] = $item;
    }
    mysqli_free_result($detailResult);
    mysqli_stmt_close($detailStatement);
}

function ordersEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function orderStatusLabel($status) {
    return match ((int)$status) {
        1 => 'Đang xử lý',
        2 => 'Đang giao hàng',
        3 => 'Đã giao',
        4 => 'Đã hủy',
        0, 1 => 'Đang xử lý',
        default => 'Đang xử lý'
    };
}

$utilsPageTitle = 'Đơn hàng của tôi | KhoaiLaptop';
require_once(__DIR__ . '/../assets/header.php');
?>
<main class="container utils-page orders-page">
    <a class="utils-back-link" href="./index.php">← Về trang chính</a>
    <div class="utils-heading">
        <p class="eyebrow">LỊCH SỬ MUA HÀNG</p>
        <h1>Đơn hàng của tôi</h1>
        <p>Xem lại các sản phẩm và thông tin những đơn hàng bạn đã đặt.</p>
    </div>

    <?php if (!$orders): ?>
        <section class="orders-empty">
            <h2>Bạn chưa có đơn hàng nào</h2>
            <p>Hãy chọn một sản phẩm và bắt đầu đơn hàng đầu tiên.</p>
            <a class="primary-button" href="./index.php#shop">Mua sắm ngay <span>↗</span></a>
        </section>
    <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
                <?php 
                    $displayCode = !empty($order['order_code']) ? $order['order_code'] : ('KL-' . str_pad((string)$order['id'], 6, '0', STR_PAD_LEFT));
                    $deliveryPin = !empty($order['delivery_pin']) ? $order['delivery_pin'] : null;
                ?>
                <article class="order-card">
                    <div class="order-card-header">
                        <div>
                            <p class="order-label">ĐƠN HÀNG #<?php echo ordersEscape($displayCode); ?></p>
                            <time datetime="<?php echo ordersEscape($order['order_date']); ?>"><?php echo ordersEscape($order['order_date']); ?></time>
                        </div>
                        <span class="order-status order-status--<?php echo (int)$order['status']; ?>"><?php echo orderStatusLabel($order['status']); ?></span>
                    </div>

                    <?php if ($deliveryPin): ?>
                    <div class="order-verify-badge" style="background: #fffbeb; border: 1px dashed #d97706; border-radius: 8px; padding: 10px 14px; margin: 10px 0 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: #92400e; display: block; letter-spacing: 0.5px;">🔐 MÃ PIN ĐỐI CHIẾU SHIPPER (CHỐNG LỪA ĐẢO):</span>
                            <strong style="font-size: 18px; font-family: monospace; color: #b45309; letter-spacing: 2px;">PIN: <?php echo ordersEscape($deliveryPin); ?></strong>
                        </div>
                        <span style="font-size: 12px; color: #78350f; max-width: 380px;">
                            🛡️ <em>Chỉ chuyển khoản hoặc nhận hàng khi shipper đọc đúng mã PIN này.</em>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="order-items">
                        <?php foreach ($order['items'] as $item): ?>
                            <div class="order-item">
                                <?php if ($item['thumbnail']): ?><img src="<?php echo ordersEscape($item['thumbnail']); ?>" alt="<?php echo ordersEscape($item['title']); ?>"><?php else: ?><span class="order-item-placeholder">K</span><?php endif; ?>
                                <div><strong><?php echo ordersEscape($item['title'] ?? 'Sản phẩm đã xóa'); ?></strong><span>Số lượng: <?php echo (int)$item['num']; ?></span></div>
                                <b><?php echo number_format((int)$item['total_money'], 0, ',', '.'); ?> đ</b>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="order-card-footer">
                        <span>Tổng đơn hàng</span>
                        <strong><?php echo number_format((int)$order['total_money'], 0, ',', '.'); ?> đ</strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require_once(__DIR__ . '/../assets/footer.php'); ?>
