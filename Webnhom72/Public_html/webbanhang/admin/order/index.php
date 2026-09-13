<?php
require_once('../../../Private/security/session.php');
session_start();
require_once('../../database/dbhelper.php');
if (!isset($_SESSION['user_id'])) { header('Location: ../authen/admin_login.php'); exit; }
if ((int)($_SESSION['role_id'] ?? 0) !== 1) { header('Location: ../../index.php'); exit; }

// Fetch orders
$conn = getDB();
$adminBase = '../';
$adminSection = 'orders';
$orders = [];
$result = mysqli_query($conn, 'SELECT id, fullname, email, phone_number, address, total_money, order_date, status FROM `order` ORDER BY id DESC');
while ($row = mysqli_fetch_assoc($result)) { $orders[] = $row; }
mysqli_free_result($result);
$displayName = htmlspecialchars((string)($_SESSION['fullname'] ?? 'Quản trị viên'), ENT_QUOTES, 'UTF-8');
require_once('../layouts/header.php');
?>
<div class="page-heading"><div><p class="eyebrow">KHU VỰC QUẢN TRỊ</p><h1>Đơn hàng</h1><p>Kiểm tra các đơn hàng đã phát sinh.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Khách hàng</th><th>Email</th><th>Điện thoại</th><th>Địa chỉ</th><th>Tổng tiền</th><th>Ngày đặt</th><th>Trạng thái</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td>#<?php echo (int)$order['id']; ?></td>
    <td><strong><?php echo htmlspecialchars($order['fullname'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
    <td><?php echo htmlspecialchars($order['email'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($order['phone_number'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($order['address'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo number_format((int)$order['total_money'], 0, ',', '.'); ?> đ</td>
    <td><?php echo htmlspecialchars($order['order_date'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><span class="pill"><?php echo (int)$order['status']; ?></span></td>
</tr><?php endforeach; ?><?php if (!$orders): ?><tr><td colspan="8" class="empty">Chưa có đơn hàng nào.</td></tr><?php endif; ?></tbody></table></div>
<?php require_once('../layouts/footer.php'); ?>
