<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once('../../database/dbhelper.php');
require_once(__DIR__ . '/../../../../Private/security/csrf.php');

$conn = getDB();
authRequireAdmin($conn);
$adminBase = '../';
$adminSection = 'categories';
$message = '';
$error = '';

function categoryEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'save_category') {
        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $error = 'Tên danh mục là bắt buộc.';
        } elseif ($id > 0) {
            $statement = mysqli_prepare($conn, 'UPDATE `category` SET name = ? WHERE id = ?');
            mysqli_stmt_bind_param($statement, 'si', $name, $id);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $message = $success ? 'Đã lưu danh mục.' : 'Không thể lưu danh mục.';
        } else {
            $statement = mysqli_prepare($conn, 'INSERT INTO `category` (name) VALUES (?)');
            mysqli_stmt_bind_param($statement, 's', $name);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $message = $success ? 'Đã thêm danh mục.' : 'Không thể thêm danh mục.';
        }
    } elseif ($action === 'delete_category' && $id > 0) {
        $statement = mysqli_prepare($conn, 'DELETE FROM `category` WHERE id = ?');
        mysqli_stmt_bind_param($statement, 'i', $id);

        try {
            $success = mysqli_stmt_execute($statement);
            if ($success && mysqli_stmt_affected_rows($statement) > 0) {
                $message = 'Đã xóa danh mục.';
            } else {
                $error = 'Danh mục không tồn tại hoặc đã bị xóa.';
            }
        } catch (mysqli_sql_exception $e) {
            $error = ($e->getCode() === 1451)
                ? 'Không thể xóa danh mục đang được sử dụng.'
                : 'Lỗi hệ thống khi xóa danh mục.';
        }
        mysqli_stmt_close($statement);
    }
}

$editCategory = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $statement = mysqli_prepare($conn, 'SELECT id, name FROM `category` WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($statement, 'i', $id);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    $editCategory = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($statement);
}

$categories = [];
$result = mysqli_query($conn, 'SELECT id, name FROM `category` ORDER BY id');
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}
mysqli_free_result($result);
$displayName = categoryEscape($_SESSION['fullname'] ?? 'Quản trị viên');
require_once('../layouts/header.php');
?>
<div class="page-heading">
    <div><p class="eyebrow">KHU VỰC QUẢN TRỊ</p><h1>Danh mục</h1><p>Quản lý nhóm sản phẩm của cửa hàng.</p></div>
    <a class="primary-button" href="?new=1#form">+ Thêm danh mục</a>
</div>
<?php if ($message !== ''): ?><div class="notice success"><?php echo categoryEscape($message); ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="notice error"><?php echo categoryEscape($error); ?></div><?php endif; ?>
<?php if ($editCategory !== null || isset($_GET['new'])): ?><form class="editor" id="form" method="post"><?php echo adminCsrfField(); ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?php echo (int)($editCategory['id'] ?? 0); ?>"><h2><?php echo $editCategory ? 'Sửa danh mục' : 'Thêm danh mục'; ?></h2><div class="form-grid"><label>Tên danh mục<input name="name" required value="<?php echo categoryEscape($editCategory['name'] ?? ''); ?>"></label></div><div class="form-actions"><button class="primary-button" type="submit">Lưu danh mục</button><a class="ghost-button" href="index.php">Hủy</a></div></form><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Tên danh mục</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($categories as $category): ?><tr><td>#<?php echo (int)$category['id']; ?></td><td><strong><?php echo categoryEscape($category['name']); ?></strong></td><td class="actions"><a href="?edit=<?php echo (int)$category['id']; ?>#form">Sửa</a><form method="post" data-confirm-message="Xóa danh mục này?"><?php echo adminCsrfField(); ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>"><button type="submit">Xóa</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php require_once('../layouts/footer.php'); ?>