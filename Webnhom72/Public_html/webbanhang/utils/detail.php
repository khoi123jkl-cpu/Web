<?php
require_once('../database/dbhelper.php');
$conn = getDB();
$id = (int)($_GET['id'] ?? 0);
$product = null;
$statement = mysqli_prepare($conn, 'SELECT p.id, p.title, p.price, p.thumbnail, p.description, c.name AS category_name FROM `product` p LEFT JOIN `category` c ON c.id = p.category_id WHERE p.id = ? AND p.deleted = 0 LIMIT 1');
mysqli_stmt_bind_param($statement, 'i', $id);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);
$product = mysqli_fetch_assoc($result);
mysqli_free_result($result);
mysqli_stmt_close($statement);

function detailEscape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$productImage = $product['thumbnail'] ?? '';
if ($productImage === '') {
	$productImage = 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=1000&q=80';
}
$utilsPageTitle = $product ? $product['title'] . ' | KhoaiLaptop' : 'Không tìm thấy sản phẩm | KhoaiLaptop';
require_once('../assets/header.php');
?>
	<main class="container utils-page detail-page">
		<a class="utils-back-link" href="index.php">← Về trang chính</a>
		<?php if ($product): ?>
			<section class="standard product-detail">
				<div class="standard-image">
					<img src="<?php echo detailEscape($productImage); ?>" alt="<?php echo detailEscape($product['title']); ?>">
				</div>
				<div class="standard-copy">
					<p class="eyebrow"><?php echo detailEscape($product['category_name'] ?? 'Sản phẩm'); ?></p>
					<h1><?php echo detailEscape($product['title']); ?></h1>
					<p class="product-description"><?php echo nl2br(detailEscape($product['description'] ?: 'Sản phẩm chính hãng từ KhoaiLaptop.')); ?></p>
					<strong class="price"><?php echo number_format((float)$product['price'], 0, ',', '.'); ?> đ</strong>
					<div class="detail-actions">
						<a class="primary-button" href="index.php#shop">Mua sản phẩm</a>
						<a class="primary-button" href="index.php#shop">← Tiếp tục mua sắm</a>
					</div>
				</div>
			</section>
		<?php else: ?>
			<h1>Không tìm thấy sản phẩm</h1>
		<?php endif; ?>
	</main>
<?php require_once('../assets/footer.php'); ?>
