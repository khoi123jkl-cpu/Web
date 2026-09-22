<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once('../../database/dbhelper.php');
require_once(__DIR__ . '/../../../../Private/security/csrf.php');

$conn = getDB();
authRequireAdmin($conn);
$adminBase = '../';
$adminSection = 'orders';
$statusLabels = [
    1 => 'Đang xử lý',
    2 => 'Đang giao hàng',
    3 => 'Đã giao hàng'
];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();

    $action = $_POST['action'] ?? 'update_status';
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

    if ($orderId === false || $orderId === null || $orderId < 1) {
        $error = 'Mã đơn hàng không hợp lệ.';
    } elseif ($action === 'delete') {
        if (!mysqli_begin_transaction($conn)) {
            $error = 'Không thể bắt đầu xóa đơn hàng.';
        } else {
            $detailStatement = mysqli_prepare($conn, 'DELETE FROM `order_detail` WHERE order_id = ?');
            $orderStatement = mysqli_prepare($conn, 'DELETE FROM `order` WHERE id = ?');

            if ($detailStatement === false || $orderStatement === false) {
                mysqli_rollback($conn);
                $error = 'Không thể chuẩn bị xóa đơn hàng.';
            } else {
                mysqli_stmt_bind_param($detailStatement, 'i', $orderId);
                $detailsDeleted = mysqli_stmt_execute($detailStatement);
                mysqli_stmt_close($detailStatement);

                mysqli_stmt_bind_param($orderStatement, 'i', $orderId);
                $orderDeleted = mysqli_stmt_execute($orderStatement);
                $affectedRows = mysqli_stmt_affected_rows($orderStatement);
                mysqli_stmt_close($orderStatement);

                if ($detailsDeleted && $orderDeleted && $affectedRows === 1) {
                    mysqli_commit($conn);
                    $success = 'Đã xóa đơn hàng #' . $orderId . '.';
                } else {
                    mysqli_rollback($conn);
                    $error = 'Không thể xóa đơn hàng. Vui lòng thử lại.';
                }
            }
        }
    } else {
        $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

        if ($status === false || !isset($statusLabels[$status])) {
            $error = 'Thông tin cập nhật trạng thái không hợp lệ.';
        } else {
            $statement = mysqli_prepare($conn, 'UPDATE `order` SET status = ? WHERE id = ?');
            if ($statement === false) {
                $error = 'Không thể chuẩn bị cập nhật đơn hàng.';
            } else {
                mysqli_stmt_bind_param($statement, 'ii', $status, $orderId);
                if (mysqli_stmt_execute($statement) && mysqli_stmt_affected_rows($statement) >= 0) {
                    $success = 'Đã cập nhật trạng thái đơn hàng #' . $orderId . '.';
                } else {
                    $error = 'Không thể cập nhật trạng thái đơn hàng. Vui lòng thử lại.';
                }
                mysqli_stmt_close($statement);
            }
        }
    }
}

$orders = [];
$result = mysqli_query($conn, 'SELECT id, fullname, email, phone_number, address, total_money, order_date, status FROM `order` ORDER BY id DESC');
while ($row = mysqli_fetch_assoc($result)) { $orders[] = $row; }
mysqli_free_result($result);
$displayName = htmlspecialchars((string)($_SESSION['fullname'] ?? 'Quản trị viên'), ENT_QUOTES, 'UTF-8');
require_once('../layouts/header.php');
?>
<div class="page-heading"><div><p class="eyebrow">KHU VỰC QUẢN TRỊ</p><h1>Đơn hàng</h1><p>Kiểm tra và cập nhật trạng thái các đơn hàng đã phát sinh.</p></div></div>
<?php if ($error !== ''): ?><p class="notice error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<?php if ($success !== ''): ?><p class="notice success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Khách hàng</th><th>Email</th><th>Điện thoại</th><th>Địa chỉ</th><th>Tổng tiền</th><th>Ngày đặt</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td>#<?php echo (int)$order['id']; ?></td>
    <td><strong><?php echo htmlspecialchars($order['fullname'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
    <td><?php echo htmlspecialchars($order['email'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($order['phone_number'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($order['address'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo number_format((int)$order['total_money'], 0, ',', '.'); ?> đ</td>
    <td><?php echo htmlspecialchars($order['order_date'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td>
        <form method="post">
            <?php echo adminCsrfField(); ?>
            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
            <select name="status" aria-label="Trạng thái đơn hàng #<?php echo (int)$order['id']; ?>">
                <?php foreach ($statusLabels as $status => $label): ?>
                    <option value="<?php echo $status; ?>"<?php echo ((int)$order['status'] === $status || ((int)$order['status'] === 0 && $status === 1)) ? ' selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Cập nhật</button>
        </form>
        <form method="post" class="order-delete-form" data-confirm-message="Bạn có chắc muốn xóa đơn hàng này không?">
            <?php echo adminCsrfField(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
            <button type="submit" class="delete-button">Xóa đơn hàng</button>
        </form>
    </td>
</tr><?php endforeach; ?><?php if (!$orders): ?><tr><td colspan="9" class="empty">Chưa có đơn hàng nào.</td></tr><?php endif; ?></tbody></table></div>
<?php require_once('../layouts/footer.php'); ?>
