<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../../database/dbhelper.php');
require_once(__DIR__ . '/../../../../Private/security/csrf.php');

$conn = getDB();
authRequireAdmin($conn);
$adminBase = '../';
$adminSection = 'products';
$search = trim($_GET['q'] ?? '');
$message = '';
$error = '';

function productEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function productNextId($conn) {
    $result = mysqli_query($conn, 'SELECT COALESCE(MAX(id), -1) + 1 AS next_id FROM `product`');
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    return (int)$row['next_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        $statement = mysqli_prepare($conn, 'UPDATE `product` SET deleted = 1, updated_at = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($statement, 'i', $id);
        $success = mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        $success ? $message = 'Đã cho sản phẩm vào kho lưu trữ.' : $error = 'Không thể xóa sản phẩm.';
    } elseif ($action === 'save_product') {
        $id = (int)($_POST['id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $categoryStatement = mysqli_prepare($conn, 'SELECT id FROM `category` WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($categoryStatement, 'i', $categoryId);
        mysqli_stmt_execute($categoryStatement);
        $categoryResult = mysqli_stmt_get_result($categoryStatement);
        $categoryExists = mysqli_fetch_assoc($categoryResult) !== null;
        mysqli_free_result($categoryResult);
        mysqli_stmt_close($categoryStatement);

        if ($title === '' || !$categoryExists || $price === '') {
            $error = 'Tên sản phẩm, danh mục và giá là bắt buộc.';
        } elseif ($id > 0) {
            $statement = mysqli_prepare($conn, 'UPDATE `product` SET category_id = ?, title = ?, price = ?, thumbnail = ?, description = ?, updated_at = NOW(), deleted = 0 WHERE id = ?');
            mysqli_stmt_bind_param($statement, 'issssi', $categoryId, $title, $price, $thumbnail, $description, $id);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $success ? $message = 'Đã cập nhật sản phẩm.' : $error = 'Không thể cập nhật sản phẩm.';
        } else {
            $newId = productNextId($conn);
            $statement = mysqli_prepare($conn, 'INSERT INTO `product` (id, category_id, title, price, thumbnail, description, created_at, updated_at, deleted) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)');
            mysqli_stmt_bind_param($statement, 'iissss', $newId, $categoryId, $title, $price, $thumbnail, $description);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $success ? $message = 'Đã thêm sản phẩm mới.' : $error = 'Không thể thêm sản phẩm.';
        }
    }
}

$categories = [];
$categoryResult = mysqli_query($conn, 'SELECT id, name FROM `category` ORDER BY name');
while ($row = mysqli_fetch_assoc($categoryResult)) {
    $categories[] = $row;
}
mysqli_free_result($categoryResult);

$editProduct = null;
if (isset($_GET['edit_product'])) {
    $id = (int)$_GET['edit_product'];
    $statement = mysqli_prepare($conn, 'SELECT * FROM `product` WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $id);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $editProduct = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);
}

$products = [];
$like = '%' . $search . '%';
$statement = mysqli_prepare($conn, 'SELECT p.*, c.name AS category_name FROM `product` p LEFT JOIN `category` c ON c.id = p.category_id WHERE p.deleted = 0 AND (p.title LIKE ? OR p.description LIKE ?) ORDER BY p.id DESC');
mysqli_stmt_bind_param($statement, 'ss', $like, $like);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
mysqli_free_result($result);
mysqli_stmt_close($statement);

$displayName = productEscape($_SESSION['fullname'] ?? 'Quản trị viên');
?>
<?php require_once(__DIR__ . '/../layouts/header.php'); ?>
<div class="page-heading">
    <div><p class="eyebrow">KHU VỰC QUẢN TRỊ</p><h1>Kho sản phẩm</h1><p>Tra cứu, thêm, sửa và lưu trữ sản phẩm.</p></div>
    <a class="primary-button" href="?new_product=1#form">+ Thêm sản phẩm</a>
</div>
<?php if ($message !== ''): ?><div class="notice success"><?php echo productEscape($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error"><?php echo productEscape($error); ?></div><?php endif; ?>
<?php if ($editProduct !== null || isset($_GET['new_product'])): ?>
    <form class="editor" id="form" method="post">
        <?php echo adminCsrfField(); ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?php echo (int)($editProduct['id'] ?? 0); ?>">
        <h2><?php echo $editProduct ? 'Sửa sản phẩm' : 'Thêm sản phẩm'; ?></h2>
        <div class="form-grid"><label>Tên sản phẩm<input name="title" required value="<?php echo productEscape($editProduct['title'] ?? ''); ?>"></label><label>Danh mục<select name="category_id" required><option value="">Chọn danh mục</option><?php foreach ($categories as $category): ?><option value="<?php echo (int)$category['id']; ?>" <?php echo (int)($editProduct['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : ''; ?>><?php echo productEscape($category['name']); ?></option><?php endforeach; ?></select></label><label>Giá<input name="price" required value="<?php echo productEscape($editProduct['price'] ?? ''); ?>" placeholder="Ví dụ: 12990000"></label><label>Ảnh sản phẩm (URL)<input name="thumbnail" value="<?php echo productEscape($editProduct['thumbnail'] ?? ''); ?>"></label><label>Mô tả<textarea name="description" rows="3"><?php echo productEscape($editProduct['description'] ?? ''); ?></textarea></label></div>
        <div class="form-actions"><button class="primary-button" type="submit">Lưu sản phẩm</button><a class="ghost-button" href="index.php">Hủy</a></div>
    </form>
<?php endif; ?>
<form class="search-bar" method="get"><input name="q" value="<?php echo productEscape($search); ?>" placeholder="Tìm theo tên hoặc mô tả sản phẩm..."><button class="ghost-button">Tra cứu</button></form>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Sản phẩm</th><th>Danh mục</th><th>Giá</th><th>Cập nhật</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td>#<?php echo (int)$product['id']; ?></td><td><div class="product-cell"><?php if ($product['thumbnail']): ?><img src="<?php echo productEscape($product['thumbnail']); ?>" alt=""><?php endif; ?><strong><?php echo productEscape($product['title']); ?></strong></div></td><td><span class="pill"><?php echo productEscape($product['category_name'] ?? 'Chưa phân loại'); ?></span></td><td><?php echo productEscape($product['price']); ?></td><td><?php echo productEscape($product['updated_at']); ?></td><td class="actions"><a href="?edit_product=<?php echo (int)$product['id']; ?>#form">Sửa</a><form method="post" onsubmit="return confirm('Đưa sản phẩm này vào kho lưu trữ nhé?');"><?php echo adminCsrfField(); ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>"><button type="submit">Xóa</button></form></td></tr><?php endforeach; ?><?php if (!$products): ?><tr><td colspan="6" class="empty">Không tìm thấy sản phẩm nào.</td></tr><?php endif; ?></tbody></table></div>
<?php require_once(__DIR__ . '/../layouts/footer.php'); ?>
