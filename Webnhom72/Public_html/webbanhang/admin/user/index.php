<?php
require_once('../../../Private/security/session.php');
session_start();
require_once('../../database/dbhelper.php');
require_once('../../../Private/security/csrf.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authen/admin_login.php');
    exit;
}

if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ../../index.php');
    exit;
}

$conn = getDB();
$adminSection = $adminSection ?? 'users';
$view = $_GET['view'] ?? 'users';
$search = trim($_GET['q'] ?? '');
$message = '';
$error = '';

function adminEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function nextId($conn, $table) {
    $allowedTables = ['category', 'product', 'user'];
    if (!in_array($table, $allowedTables, true)) {
        return 1;
    }

    $result = mysqli_query($conn, "SELECT COALESCE(MAX(id), -1) + 1 AS next_id FROM `$table`");
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    return (int)$row['next_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $action = $_POST['action'] ?? '';
    $view = $_POST['view'] ?? $view;

    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        $statement = mysqli_prepare($conn, 'UPDATE `product` SET deleted = 1, updated_at = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($statement, 'i', $id);
        $success = mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        $success ? $message = 'Đã cho sản phẩm vào kho lưu trữ.' : $error = 'Không thể xóa sản phẩm.';
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            $error = 'Không thể tự xóa tài khoản đang đăng nhập.';
        } else {
            $statement = mysqli_prepare($conn, 'UPDATE `user` SET deleted = 1, updated_at = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($statement, 'i', $id);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $success ? $message = 'Đã ẩn tài khoản người dùng.' : $error = 'Không thể xóa tài khoản.';
        }
    }

    if ($action === 'save_product') {
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
            $newId = nextId($conn, 'product');
            $statement = mysqli_prepare($conn, 'INSERT INTO `product` (id, category_id, title, price, thumbnail, description, created_at, updated_at, deleted) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)');
            mysqli_stmt_bind_param($statement, 'iissss', $newId, $categoryId, $title, $price, $thumbnail, $description);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $success ? $message = 'Đã thêm sản phẩm mới.' : $error = 'Không thể thêm sản phẩm.';
        }
    }

    if ($action === 'save_user') {
        $id = (int)($_POST['id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 2);
        $password = $_POST['password'] ?? '';

        if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Họ tên và email hợp lệ là bắt buộc.';
        } elseif ($id > 0) {
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $statement = mysqli_prepare($conn, 'UPDATE `user` SET fullname = ?, email = ?, phone_number = ?, address = ?, role_id = ?, password = ?, updated_at = NOW(), deleted = 0 WHERE id = ?');
                mysqli_stmt_bind_param($statement, 'ssssisi', $fullname, $email, $phone, $address, $roleId, $passwordHash, $id);
            } else {
                $statement = mysqli_prepare($conn, 'UPDATE `user` SET fullname = ?, email = ?, phone_number = ?, address = ?, role_id = ?, updated_at = NOW(), deleted = 0 WHERE id = ?');
                mysqli_stmt_bind_param($statement, 'ssssii', $fullname, $email, $phone, $address, $roleId, $id);
            }
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $success ? $message = 'Đã cập nhật tài khoản.' : $error = 'Không thể cập nhật tài khoản. Email có thể đã tồn tại.';
        } else {
            $passwordHash = password_hash($password !== '' ? $password : bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $newId = nextId($conn, 'user');
            $statement = mysqli_prepare($conn, 'INSERT INTO `user` (id, fullname, email, phone_number, address, password, role_id, created_at, updated_at, deleted) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)');
            mysqli_stmt_bind_param($statement, 'isssssi', $newId, $fullname, $email, $phone, $address, $passwordHash, $roleId);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $success ? $message = 'Đã thêm tài khoản mới.' : $error = 'Không thể thêm tài khoản. Email có thể đã tồn tại.';
        }
    }
}

$defaultCategories = ['Điện thoại', 'Laptop'];
$categoryLookup = mysqli_prepare($conn, 'SELECT id FROM `category` WHERE name = ? LIMIT 1');
$categoryInsert = mysqli_prepare($conn, 'INSERT INTO `category` (id, name) VALUES (?, ?)');
foreach ($defaultCategories as $defaultCategory) {
    mysqli_stmt_bind_param($categoryLookup, 's', $defaultCategory);
    mysqli_stmt_execute($categoryLookup);
    $categoryResult = mysqli_stmt_get_result($categoryLookup);
    $categoryExists = mysqli_fetch_assoc($categoryResult);
    mysqli_free_result($categoryResult);

    if (!$categoryExists) {
        $newCategoryId = nextId($conn, 'category');
        mysqli_stmt_bind_param($categoryInsert, 'is', $newCategoryId, $defaultCategory);
        mysqli_stmt_execute($categoryInsert);
    }
}
mysqli_stmt_close($categoryLookup);
mysqli_stmt_close($categoryInsert);

$categories = [];
$categoryResult = mysqli_query($conn, 'SELECT id, name FROM `category` ORDER BY name');
while ($row = mysqli_fetch_assoc($categoryResult)) {
    $categories[] = $row;
}
mysqli_free_result($categoryResult);

$roles = [];
$roleResult = mysqli_query($conn, 'SELECT id, name FROM `role` ORDER BY id');
while ($row = mysqli_fetch_assoc($roleResult)) {
    $roles[] = $row;
}
mysqli_free_result($roleResult);

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

$editUser = null;
if (isset($_GET['edit_user'])) {
    $id = (int)$_GET['edit_user'];
    $statement = mysqli_prepare($conn, 'SELECT id, fullname, email, phone_number, address, role_id FROM `user` WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $id);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $editUser = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);
}

$products = [];
if ($view === 'products') {
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
}

$users = [];
if ($view === 'users') {
    $like = '%' . $search . '%';
    $statement = mysqli_prepare($conn, 'SELECT u.id, u.fullname, u.email, u.phone_number, u.address, u.role_id, r.name AS role_name, u.created_at FROM `user` u LEFT JOIN `role` r ON r.id = u.role_id WHERE u.deleted = 0 AND (u.fullname LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?) ORDER BY u.id DESC');
    mysqli_stmt_bind_param($statement, 'sss', $like, $like, $like);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    mysqli_free_result($result);
    mysqli_stmt_close($statement);
}

$displayName = adminEscape($_SESSION['fullname'] ?? 'Quản trị viên');
?>
<?php require_once('../layouts/header.php'); ?>
        <div class="page-heading">
            <div><p class="eyebrow">KHU VỰC QUẢN TRỊ</p><h1><?php echo $view === 'users' ? 'Tài khoản người dùng' : 'Kho sản phẩm'; ?></h1><p>Tra cứu, thêm, sửa, xóa.</p></div>
            <a class="primary-button" href="?view=<?php echo $view; ?>&<?php echo $view === 'users' ? 'new_user=1' : 'new_product=1'; ?>#form">+ Thêm mới</a>
        </div>
        <?php if ($message !== ''): ?><div class="notice success"><?php echo adminEscape($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="notice error"><?php echo adminEscape($error); ?></div><?php endif; ?>

        <?php if ($view === 'users'): ?>
            <?php if ($editUser !== null || isset($_GET['new_user'])): ?>
                <form class="editor" id="form" method="post">
                    <?php echo adminCsrfField(); ?><input type="hidden" name="action" value="save_user"><input type="hidden" name="view" value="users"><input type="hidden" name="id" value="<?php echo (int)($editUser['id'] ?? 0); ?>">
                    <h2><?php echo $editUser ? 'Sửa tài khoản' : 'Thêm tài khoản'; ?></h2>
                    <div class="form-grid"><label>Họ và tên<input name="fullname" required value="<?php echo adminEscape($editUser['fullname'] ?? ''); ?>"></label><label>Email<input type="email" name="email" required value="<?php echo adminEscape($editUser['email'] ?? ''); ?>"></label><label>Số điện thoại<input name="phone_number" value="<?php echo adminEscape($editUser['phone_number'] ?? ''); ?>"></label><label>Địa chỉ<input name="address" value="<?php echo adminEscape($editUser['address'] ?? ''); ?>"></label><label>Vai trò<select name="role_id"><?php foreach ($roles as $role): ?><option value="<?php echo (int)$role['id']; ?>" <?php echo (int)($editUser['role_id'] ?? 2) === (int)$role['id'] ? 'selected' : ''; ?>><?php echo adminEscape($role['name']); ?></option><?php endforeach; ?></select></label><label>Mật khẩu <?php echo $editUser ? '<small>(bỏ trống để giữ nguyên)</small>' : ''; ?><input type="password" name="password" <?php echo $editUser ? '' : 'required'; ?>></label></div>
                    <div class="form-actions"><button class="primary-button" type="submit">Lưu tài khoản</button><a class="ghost-button" href="?view=users">Hủy</a></div>
                </form>
            <?php endif; ?>
            <form class="search-bar" method="get"><input type="hidden" name="view" value="users"><input name="q" value="<?php echo adminEscape($search); ?>" placeholder="Tìm theo tên, email, số điện thoại..."><button class="ghost-button">Tra cứu</button></form>
            <div class="table-wrap"><table><thead><tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Vai trò</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td>#<?php echo (int)$user['id']; ?></td><td><strong><?php echo adminEscape($user['fullname']); ?></strong></td><td><?php echo adminEscape($user['email']); ?></td><td><?php echo adminEscape($user['phone_number']); ?></td><td><span class="pill"><?php echo adminEscape($user['role_name'] ?? ('Role ' . $user['role_id'])); ?></span></td><td><?php echo adminEscape($user['created_at']); ?></td><td class="actions"><a href="?view=users&edit_user=<?php echo (int)$user['id']; ?>#form">Sửa</a><form method="post" onsubmit="return confirm('Ẩn tài khoản này nhé?');"><?php echo adminCsrfField(); ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="view" value="users"><input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>"><button type="submit">Xóa</button></form></td></tr><?php endforeach; ?><?php if (!$users): ?><tr><td colspan="7" class="empty">Không tìm thấy tài khoản nào.</td></tr><?php endif; ?></tbody></table></div>
        <?php else: ?>
            <?php if ($editProduct !== null || isset($_GET['new_product'])): ?>
                <form class="editor" id="form" method="post">
                    <?php echo adminCsrfField(); ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="view" value="products"><input type="hidden" name="id" value="<?php echo (int)($editProduct['id'] ?? 0); ?>">
                    <h2><?php echo $editProduct ? 'Sửa sản phẩm' : 'Thêm sản phẩm'; ?></h2>
                    <div class="form-grid"><label>Tên sản phẩm<input name="title" required value="<?php echo adminEscape($editProduct['title'] ?? ''); ?>"></label><label>Danh mục<select name="category_id" required><option value="">Chọn danh mục</option><?php foreach ($categories as $category): ?><option value="<?php echo (int)$category['id']; ?>" <?php echo (int)($editProduct['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : ''; ?>><?php echo adminEscape($category['name']); ?></option><?php endforeach; ?></select></label><label>Giá<input name="price" required value="<?php echo adminEscape($editProduct['price'] ?? ''); ?>" placeholder="Ví dụ: 12990000"></label><label>Ảnh sản phẩm (URL)<input name="thumbnail" value="<?php echo adminEscape($editProduct['thumbnail'] ?? ''); ?>"></label><label>Mô tả<textarea name="description" rows="3"><?php echo adminEscape($editProduct['description'] ?? ''); ?></textarea></label></div>
                    <div class="form-actions"><button class="primary-button" type="submit">Lưu sản phẩm</button><a class="ghost-button" href="?view=products">Hủy</a></div>
                </form>
            <?php endif; ?>
            <form class="search-bar" method="get"><input type="hidden" name="view" value="products"><input name="q" value="<?php echo adminEscape($search); ?>" placeholder="Tìm theo tên hoặc mô tả sản phẩm..."><button class="ghost-button">Tra cứu</button></form>
            <div class="table-wrap"><table><thead><tr><th>ID</th><th>Sản phẩm</th><th>Danh mục</th><th>Giá</th><th>Cập nhật</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td>#<?php echo (int)$product['id']; ?></td><td><div class="product-cell"><?php if ($product['thumbnail']): ?><img src="<?php echo adminEscape($product['thumbnail']); ?>" alt=""><?php endif; ?><strong><?php echo adminEscape($product['title']); ?></strong></div></td><td><span class="pill"><?php echo adminEscape($product['category_name'] ?? 'Chưa phân loại'); ?></span></td><td><?php echo adminEscape($product['price']); ?></td><td><?php echo adminEscape($product['updated_at']); ?></td><td class="actions"><a href="?view=products&edit_product=<?php echo (int)$product['id']; ?>#form">Sửa</a><form method="post" onsubmit="return confirm('Đưa sản phẩm này vào kho lưu trữ nhé?');"><?php echo adminCsrfField(); ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="view" value="products"><input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>"><button type="submit">Xóa</button></form></td></tr><?php endforeach; ?><?php if (!$products): ?><tr><td colspan="6" class="empty">Không tìm thấy sản phẩm nào.</td></tr><?php endif; ?></tbody></table></div>
        <?php endif; ?>
<?php require_once('../layouts/footer.php'); ?>
