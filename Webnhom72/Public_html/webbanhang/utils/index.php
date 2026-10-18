<?php
require_once(__DIR__ . '/../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../database/dbhelper.php');
require_once(__DIR__ . '/../../../Private/security/csrf.php');


$conn = getDB();
$error = '';
$selectedCategory = (int)($_GET['category_id'] ?? 0);
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_to_cart') {
  adminVerifyCsrf();
  authRequireUser($conn);
  $productId = (int)($_POST['product_id'] ?? 0);
  $userId = (int)$_SESSION['user_id'];
  $cartStatement = mysqli_prepare($conn, 'SELECT id FROM `cart` WHERE user_id = ? LIMIT 1');
  mysqli_stmt_bind_param($cartStatement, 'i', $userId);
  mysqli_stmt_execute($cartStatement);
  mysqli_stmt_bind_result($cartStatement, $cartId);
  $hasCart = mysqli_stmt_fetch($cartStatement);
  mysqli_stmt_close($cartStatement);

  if (!$hasCart) {
    $cartStatement = mysqli_prepare($conn, 'INSERT INTO `cart` (user_id) VALUES (?)');
    mysqli_stmt_bind_param($cartStatement, 'i', $userId);
    mysqli_stmt_execute($cartStatement);
    $cartId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($cartStatement);
  }

  $itemStatement = mysqli_prepare($conn, 'INSERT INTO `cart_item` (product_id, cart_id) SELECT ?, ? FROM `product` WHERE id = ? AND deleted = 0');
  mysqli_stmt_bind_param($itemStatement, 'iii', $productId, $cartId, $productId);
  mysqli_stmt_execute($itemStatement);
  mysqli_stmt_close($itemStatement);
  header('Location: index.php#shop');
  exit;
}

$categories = [];
$categoryResult = mysqli_query($conn, 'SELECT id, name FROM `category` ORDER BY id');
while ($row = mysqli_fetch_assoc($categoryResult)) {
  $categories[] = $row;
}
mysqli_free_result($categoryResult);

$products = [];
$conditions = ['deleted = 0'];
if ($selectedCategory > 0) $conditions[] = 'category_id = ' . $selectedCategory;
if ($search !== '') $conditions[] = "title LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
$productResult = mysqli_query($conn, 'SELECT id, category_id, title, price, thumbnail, description FROM `product` WHERE ' . implode(' AND ', $conditions) . ' ORDER BY id DESC');
while ($row = mysqli_fetch_assoc($productResult)) {
  $products[] = [
    'id' => (int)$row['id'],
    'name' => $row['title'],
    'price' => (float)$row['price'],
    'image' => $row['thumbnail'] ?: 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=800&q=80',
    'description' => $row['description'] ?: 'Sản phẩm chính hãng từ KhoaiLaptop.'
  ];
}
mysqli_free_result($productResult);

 $utilsPageTitle = 'KhoaiLaptop | Laptop & PC gear';
 $showCartButton = true;
 require_once(__DIR__ . '/../assets/header.php');
?>

<main id="top">
  <section class="hero container">
    <div class="hero-copy reveal">
      <p class="eyebrow">Laptop &amp; Phụ kiện công nghệ</p>
      <h1>Hiệu năng đỉnh cao<br><em>Đồng hành cùng đam mê</em></h1>
      <p class="hero-text">Khám phá các dòng laptop gaming, văn phòng và phụ kiện công nghệ tuyển chọn với chất lượng hàng đầu và chính sách bảo hành tận tâm.</p>
      <a class="primary-button" href="#shop">Khám phá sản phẩm ngay <span>↗</span></a>
    </div>
    <div class="hero-art reveal">
      <div class="hero-label">01 / 04</div>
      <img src="https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&amp;fit=crop&amp;w=1200&amp;q=85" alt="Open laptop on a clean desk">
    </div>
  </section>

  <section class="ticker">
    <div class="ticker-container">
  <div class="ticker-track">
    <div class="ticker-content">
      <span class="ticker-item">Vẫn luôn là anh</span>
      <span class="ticker-item">Bây giờ</span>
      <span class="ticker-item">Muốn nói với em</span>
      <span class="ticker-item">Sau này</span>
    </div>
    <div class="ticker-content">
      <span class="ticker-item">Vẫn luôn là anh</span>
      <span class="ticker-item">Bây giờ</span>
      <span class="ticker-item">Muốn nói với em</span>
      <span class="ticker-item">Sau này</span>
    </div>
    <div class="ticker-content">
      <span class="ticker-item">Vẫn luôn là anh</span>
      <span class="ticker-item">Bây giờ</span>
      <span class="ticker-item">Muốn nói với em</span>
      <span class="ticker-item">Sau này</span>
    </div>
        <div class="ticker-content">
      <span class="ticker-item">Vẫn luôn là anh</span>
      <span class="ticker-item">Bây giờ</span>
      <span class="ticker-item">Muốn nói với em</span>
      <span class="ticker-item">Sau này</span>
    </div>
        <div class="ticker-content">
      <span class="ticker-item">Vẫn luôn là anh</span>
      <span class="ticker-item">Bây giờ</span>
      <span class="ticker-item">Muốn nói với em</span>
      <span class="ticker-item">Sau này</span>
    </div>
        <div class="ticker-content">
      <span class="ticker-item">Vẫn luôn là anh</span>
      <span class="ticker-item">Bây giờ</span>
      <span class="ticker-item">Muốn nói với em</span>
      <span class="ticker-item">Sau này</span>
    </div>
  </div>  
  </section>

  <section class="category-section container" id="categories">
    <div class="section-heading">
      <div><p class="eyebrow">DANH MỤC TUYỂN CHỌN</p><h2>Danh mục sản phẩm<br>Đa dạng &amp; Chính hãng</h2></div>
      <p class="section-intro">Tìm kiếm thiết bị và linh kiện phù hợp nhất cho công việc, học tập và giải trí của bạn.</p>
    </div>
    <div class="category-grid">
      <?php foreach ($categories as $index => $category): ?>
        <a class="category-card category-<?php echo ($index % 3 === 0 ? 'laptop' : ($index % 3 === 1 ? 'parts' : 'desk')); ?>" href="index.php?category_id=<?php echo (int)$category['id']; ?>#shop">
          <span><?php echo str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
          <h3><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
          <p>Khám phá sản phẩm</p><b>Mua ngay ↗</b>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

    <section class="products-section" id="shop">
      <div class="container">
        <div class="section-heading product-heading">
          <div>
            <p class="eyebrow">THE SHORTLIST</p>
            <h2>Good gear,<br>no guesswork.</h2>
          </div>

          <div class="filters" role="group" aria-label="Filter products">
              <a class="filter <?php echo $selectedCategory === 0 ? 'active' : ''; ?>" href="index.php#shop">Tất cả</a>
            <?php foreach ($categories as $category): ?>
                <a class="filter <?php echo $selectedCategory === (int)$category['id'] ? 'active' : ''; ?>" href="index.php?category_id=<?php echo (int)$category['id']; ?>#shop"><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="product-grid" id="product-grid">
          <?php foreach ($products as $product): ?>
            <article class="product-card">
              <div class="product-image">
                <a class="product-detail-link" href="detail.php?id=<?php echo (int)$product['id']; ?>"><img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"></a>
                <form method="post"><?php echo adminCsrfField(); ?><input type="hidden" name="action" value="add_to_cart"><input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>"><button class="add-button" type="submit" aria-label="Thêm vào giỏ">+</button></form>
              </div>
              <div class="product-info"><h3><a href="detail.php?id=<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></a></h3><p><?php echo htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?></p><span class="price"><?php echo number_format($product['price'], 0, ',', '.'); ?> đ</span><a class="detail-link" href="detail.php?id=<?php echo (int)$product['id']; ?>">Xem chi tiết ↗</a></div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="standard container" id="about">
      <div class="standard-image">
        <img
          src="https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&amp;fit=crop&amp;w=1000&amp;q=85"
          alt="Laptop and accessories on a table"
        >
      </div>

      <div class="standard-copy">
        <p class="eyebrow">TIÊU CHUẨN</p>
        <h2>CHỈ BÁN NHỮNG THỨ CHÚNG TÔI KHÔNG DÙNG</h2>
        <p>
          Chúng tôi bán những thứ chúng tôi không dùng. Chúng tôi không bán những thứ chúng tôi tin tưởng. Chúng tôi không bán những thứ chúng tôi muốn mua. Chúng tôi chỉ bán những thứ chúng tôi khum thích.
        </p>

        <div class="standard-list">
          <div><strong>01</strong><span>Tested in the real world</span></div>
          <div><strong>02</strong><span>Clear, human advice</span></div>
          <div><strong>03</strong><span>Support after checkout</span></div>
        </div>
      </div>
    </section>
  </main>

<?php require_once(__DIR__ . '/../assets/footer.php'); ?>