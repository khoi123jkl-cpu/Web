<?php
require_once(__DIR__ . '/../../../Private/security/session.php');
session_start();
require_once('../database/dbhelper.php');
require_once(__DIR__ . '/../../../Private/security/csrf.php');


$conn = getDB();
authRequireUser($conn);
$userId = (int)$_SESSION['user_id'];
$orders = [];

$orderStatement = mysqli_prepare($conn, 'SELECT id, fullname, email, phone_number, address, order_date, status, total_money FROM `order` WHERE user_id = ? ORDER BY id DESC');
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
require_once('../assets/header.php');
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
                <article class="order-card">
                    <div class="order-card-header">
                        <div>
                            <p class="order-label">ĐƠN HÀNG #<?php echo (int)$order['id']; ?></p>
                            <time datetime="<?php echo ordersEscape($order['order_date']); ?>"><?php echo ordersEscape($order['order_date']); ?></time>
                        </div>
                        <span class="order-status order-status--<?php echo (int)$order['status']; ?>"><?php echo orderStatusLabel($order['status']); ?></span>
                    </div>

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
<?php require_once('../assets/footer.php'); ?>
