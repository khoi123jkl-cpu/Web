<?php
require_once('../database/dbhelper.php');
$conn = getDB();
$categories = [];
$result = mysqli_query($conn, 'SELECT id, name FROM `category` ORDER BY name');
while ($row = mysqli_fetch_assoc($result)) {
	$categories[] = $row;
}
mysqli_free_result($result);

$selectedCategory = (int)($_GET['id'] ?? 0);
$products = [];
if ($selectedCategory > 0) {
	$statement = mysqli_prepare($conn, 'SELECT id, title, price, thumbnail FROM `product` WHERE category_id = ? AND deleted = 0 ORDER BY id DESC');
	mysqli_stmt_bind_param($statement, 'i', $selectedCategory);
	mysqli_stmt_execute($statement);
	$productResult = mysqli_stmt_get_result($statement);
	while ($row = mysqli_fetch_assoc($productResult)) {
		$products[] = $row;
	}
	mysqli_free_result($productResult);
	mysqli_stmt_close($statement);
}

function categoryPageEscape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
$utilsPageTitle = 'Danh mục | KhoaiLaptop';
require_once('../assets/header.php');
?>
	<main class="container utils-page category-page">
		<a class="utils-back-link" href="index.php">← Về trang chính</a>
		<div class="utils-heading">
			<p class="eyebrow">DANH MỤC SẢN PHẨM</p>
			<h1>Chọn danh mục</h1>
		</div>
		<div class="filters">
			<?php foreach ($categories as $category): ?>
				<a class="filter" href="?id=<?php echo (int)$category['id']; ?>"><?php echo categoryPageEscape($category['name']); ?></a>
			<?php endforeach; ?>
		</div>
		<?php if ($selectedCategory > 0): ?>
			<div class="product-grid">
				<?php foreach ($products as $product): ?>
					<article class="product-card">
						<div class="product-image">
							<?php if ($product['thumbnail'] !== ''): ?><img src="<?php echo categoryPageEscape($product['thumbnail']); ?>" alt="<?php echo categoryPageEscape($product['title']); ?>"><?php endif; ?>
						</div>
						<div class="product-info">
							<h3><a href="detail.php?id=<?php echo (int)$product['id']; ?>"><?php echo categoryPageEscape($product['title']); ?></a></h3>
							<span class="price"><?php echo categoryPageEscape($product['price']); ?></span>
							<a class="detail-link" href="detail.php?id=<?php echo (int)$product['id']; ?>">Xem chi tiết ↗</a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if (!$products): ?><p>Danh mục này chưa có sản phẩm.</p><?php endif; ?>
		<?php endif; ?>
	</main>
<?php require_once('../assets/footer.php'); ?>
