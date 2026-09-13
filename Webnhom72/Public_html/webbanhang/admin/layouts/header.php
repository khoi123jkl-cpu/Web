<?php
$adminBase = $adminBase ?? '../';
$adminSection = $adminSection ?? '';
require_once(__DIR__ . '/../../../Private/security/csrf.php');
?>
<!doctype html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Quản trị | KhoaiLaptop</title>
	<link rel="stylesheet" href="../../assets/css/admin.css">
	<link rel="stylesheet" href="../../assets/css/footer.css">
</head>
<body>
<header class="admin-header">
	<a class="admin-brand" href="<?php echo $adminBase; ?>index.php">KHOAI<span>LAPTOP</span></a>
	<div class="admin-account"><span><?php echo $displayName; ?></span><form action="<?php echo $adminBase; ?>authen/logout.php" method="post" style="display:inline"><?php echo adminCsrfField(); ?><button type="submit">Đăng xuất</button></form></div>
</header>
<main class="admin-shell">
	<aside class="sidebar">
		<p class="sidebar-label">Bảng điều khiển</p>
		<a class="side-link <?php echo $adminSection === 'products' ? 'active' : ''; ?>" href="<?php echo $adminBase; ?>product/index.php">Sản phẩm</a>
		<a class="side-link <?php echo $adminSection === 'categories' ? 'active' : ''; ?>" href="<?php echo $adminBase; ?>category/index.php">Danh mục</a>
		<a class="side-link <?php echo $adminSection === 'orders' ? 'active' : ''; ?>" href="<?php echo $adminBase; ?>order/index.php">Đơn hàng</a>
		<a class="side-link <?php echo $adminSection === 'feedback' ? 'active' : ''; ?>" href="<?php echo $adminBase; ?>feedback/index.php">Phản hồi</a>
		<a class="side-link <?php echo $adminSection === 'users' ? 'active' : ''; ?>" href="<?php echo $adminBase; ?>user/index.php">Tài khoản người dùng</a>
	</aside>
	<section class="content">
