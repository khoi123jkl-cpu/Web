<?php
require_once(__DIR__ . '/../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../database/dbhelper.php');

$conn = getDB();
authRequireAdmin($conn, 'authen/admin_login.php');
$adminBase = './';
$adminSection = 'dashboard';
function getCount($conn,$table, $condition = '') {$sql = "SELECT COUNT(id) FROM `$table` $condition";
    $result = mysqli_query($conn,$sql);
    if ($result) {
        $count = mysqli_fetch_row($result)[0];
        mysqli_free_result($result);
        return (int)$count;
    }
    return 0;
}

$stats = [
    'users'      => getCount($conn, 'user', 'WHERE deleted = 0'),
    'products'   => getCount($conn, 'product', 'WHERE deleted = 0'),
    'categories' => getCount($conn, 'category'),
    'orders'     => getCount($conn, 'order'),
    'feedbacks'  => getCount($conn, 'feedback')
];

$displayName = htmlspecialchars($_SESSION['fullname'] ?? 'Quản trị viên', ENT_QUOTES, 'UTF-8');
?>

<?php require_once(__DIR__ . '/layouts/header.php'); ?>

<div class="page-heading">
    <div>
        <p class="eyebrow">BẢNG ĐIỀU KHIỂN</p>
        <h1>Tổng quan hệ thống</h1>
        <p>Chào mừng trở lại, <strong><?php echo $displayName; ?></strong>. Bạn muốn quản lý gì hôm nay?</p>
    </div>
</div>

<div class="dashboard-grid">
    
    <!-- 1. Quản lý Đơn hàng -->
    <div class="dash-card">
        <div class="dash-card-title">Đơn hàng</div>
        <div class="dash-card-desc">Xử lý các đơn đặt hàng mới, cập nhật trạng thái vận chuyển.</div>
        <div class="dash-card-stat"><?php echo number_format($stats['orders']); ?></div>
        <!-- Điền bừa đường dẫn, bạn tự sửa lại nhé -->
        <a class="primary-button" href="order/index.php">Quản lý Đơn hàng</a>
    </div>

    <!-- 2. Quản lý Sản phẩm -->
    <div class="dash-card">
        <div class="dash-card-title">Kho Sản phẩm</div>
        <div class="dash-card-desc">Thêm sản phẩm mới, cập nhật giá và số lượng tồn kho.</div>
        <div class="dash-card-stat"><?php echo number_format($stats['products']); ?></div>
        <!-- Điền bừa đường dẫn, bạn tự sửa lại nhé -->
        <a class="ghost-button" href="product/index.php?view=products">Quản lý Sản phẩm</a>
    </div>

    <!-- 3. Quản lý Danh mục -->
    <div class="dash-card">
        <div class="dash-card-title">Danh mục</div>
        <div class="dash-card-desc">Tạo hoặc chỉnh sửa các nhóm ngành hàng.</div>
        <div class="dash-card-stat"><?php echo number_format($stats['categories']); ?></div>
        <!-- Điền bừa đường dẫn, bạn tự sửa lại nhé -->
        <a class="ghost-button" href="category/index.php">Quản lý Danh mục</a>
    </div>

    <!-- 4. Quản lý Tài khoản (Dùng lại file index hiện tại của bạn) -->
    <div class="dash-card">
        <div class="dash-card-title">Tài khoản User</div>
        <div class="dash-card-desc">Quản lý thông tin khách hàng và phân quyền quản trị viên.</div>
        <div class="dash-card-stat"><?php echo number_format($stats['users']); ?></div>
        <!-- Điền bừa đường dẫn, bạn tự sửa lại nhé -->
        <a class="ghost-button" href="user/index.php?view=users">Quản lý Tài khoản</a>
    </div>

    <!-- 5. Phản hồi / Đánh giá -->
    <div class="dash-card">
        <div class="dash-card-title">Hộp thư Phản hồi</div>
        <div class="dash-card-desc">Xem và trả lời các tin nhắn, đánh giá từ khách hàng.</div>
        <div class="dash-card-stat"><?php echo number_format($stats['feedbacks']); ?></div>
        <!-- Điền bừa đường dẫn, bạn tự sửa lại nhé -->
        <a class="ghost-button" href="feedback/index.php">Xem Phản hồi</a>
    </div>

</div>

<?php require_once(__DIR__ . '/layouts/footer.php'); ?>