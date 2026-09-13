<?php
require_once('../../../Private/security/session.php');
session_start();
require_once('../../database/dbhelper.php');
if (!isset($_SESSION['user_id'])) { header('Location: ../authen/admin_login.php'); exit; }
if ((int)($_SESSION['role_id'] ?? 0) !== 1) { header('Location: ../../index.php'); exit; }

// Fetch feedbacks
$conn = getDB();
$adminBase = '../';
$adminSection = 'feedback';
$feedbacks = [];
$result = mysqli_query($conn, 'SELECT id, firstname, lastname, email, phone_number, subject_name, note FROM `feedback` ORDER BY id DESC');
while ($row = mysqli_fetch_assoc($result)) { $feedbacks[] = $row; }
mysqli_free_result($result);
$displayName = htmlspecialchars((string)($_SESSION['fullname'] ?? 'Quản trị viên'), ENT_QUOTES, 'UTF-8');
require_once('../layouts/header.php');
?>
<div class="page-heading"><div><p class="eyebrow">KHU VỰC QUẢN TRỊ</p><h1>Phản hồi</h1><p>Theo dõi ý kiến gửi từ khách hàng.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Người gửi</th><th>Email</th><th>Điện thoại</th><th>Chủ đề</th><th>Nội dung</th></tr>
</thead><tbody><?php foreach ($feedbacks as $feedback): ?><tr><td>#<?php echo (int)$feedback['id']; ?></td><td><strong><?php echo htmlspecialchars(trim($feedback['firstname'] . ' ' . $feedback['lastname']), ENT_QUOTES, 'UTF-8'); ?></strong></td>
    <td><?php echo htmlspecialchars($feedback['email'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($feedback['phone_number'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($feedback['subject_name'], ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($feedback['note'], ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endforeach; ?><?php if (!$feedbacks): ?><tr><td colspan="6" class="empty">Chưa có phản hồi nào.</td></tr><?php endif; ?></tbody></table></div>
<?php require_once('../layouts/footer.php'); ?>
