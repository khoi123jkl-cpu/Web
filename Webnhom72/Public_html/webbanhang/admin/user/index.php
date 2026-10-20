<?php
require_once(__DIR__ . '/../../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../../database/dbhelper.php');
require_once(__DIR__ . '/../../../../Private/security/csrf.php');

$conn = getDB();
authRequireAdmin($conn);
$adminSection = $adminSection ?? 'users';
$view = 'users';
$search = trim($_GET['q'] ?? '');
$message = '';
$error = '';

function adminEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$_SESSION['user_id']) {
            $error = 'Không thể tự xóa tài khoản đang đăng nhập.';
        } else {
            $statement = mysqli_prepare($conn, 'UPDATE `user` SET deleted = 1, updated_at = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($statement, 'i', $id);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            if ($success) {
                $revokeTokens = mysqli_prepare($conn, 'DELETE FROM auth_tokens WHERE user_id = ?');
                mysqli_stmt_bind_param($revokeTokens, 'i', $id);
                mysqli_stmt_execute($revokeTokens);
                mysqli_stmt_close($revokeTokens);
            }
            $success ? $message = 'Đã ẩn tài khoản người dùng.' : $error = 'Không thể xóa tài khoản.';
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
            if ($success) {
                $revokeTokens = mysqli_prepare($conn, 'DELETE FROM auth_tokens WHERE user_id = ?');
                mysqli_stmt_bind_param($revokeTokens, 'i', $id);
                mysqli_stmt_execute($revokeTokens);
                mysqli_stmt_close($revokeTokens);
            }
            $success ? $message = 'Đã cập nhật tài khoản.' : $error = 'Không thể cập nhật tài khoản. Email có thể đã tồn tại.';
        } else {
            $passwordHash = password_hash($password !== '' ? $password : bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $statement = mysqli_prepare($conn, 'INSERT INTO `user` (fullname, email, phone_number, address, password, role_id, created_at, updated_at, deleted) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)');
            mysqli_stmt_bind_param($statement, 'sssssi', $fullname, $email, $phone, $address, $passwordHash, $roleId);
            $success = mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $success ? $message = 'Đã thêm tài khoản mới.' : $error = 'Không thể thêm tài khoản. Email có thể đã tồn tại.';
        }
    }
}

$roles = [];
$roleResult = mysqli_query($conn, 'SELECT id, name FROM `role` ORDER BY id');
while ($row = mysqli_fetch_assoc($roleResult)) {
    $roles[] = $row;
}
mysqli_free_result($roleResult);

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
<?php require_once(__DIR__ . '/../layouts/header.php'); ?>
        <div class="page-heading">
            <div><p class="eyebrow">KHU VỰC QUẢN TRỊ</p><h1>Tài khoản người dùng</h1><p>Tra cứu, thêm, sửa, xóa.</p></div>
            <a class="primary-button" href="?new_user=1#form">+ Thêm tài khoản</a>
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
            <form class="search-bar" method="get"><input name="q" value="<?php echo adminEscape($search); ?>" placeholder="Tìm theo tên, email, số điện thoại..."><button class="ghost-button">Tra cứu</button></form>
            <div class="table-wrap"><table><thead><tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Điện thoại</th><th>Vai trò</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td>#<?php echo (int)$user['id']; ?></td><td><strong><?php echo adminEscape($user['fullname']); ?></strong></td><td><?php echo adminEscape($user['email']); ?></td><td><?php echo adminEscape($user['phone_number']); ?></td><td><span class="pill"><?php echo adminEscape($user['role_name'] ?? ('Role ' . $user['role_id'])); ?></span></td><td><?php echo adminEscape($user['created_at']); ?></td><td class="actions"><a href="?view=users&edit_user=<?php echo (int)$user['id']; ?>#form">Sửa</a><form method="post" data-confirm-message="Ẩn tài khoản này nhé?"><?php echo adminCsrfField(); ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="view" value="users"><input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>"><button type="submit">Xóa</button></form></td></tr><?php endforeach; ?><?php if (!$users): ?><tr><td colspan="7" class="empty">Không tìm thấy tài khoản nào.</td></tr><?php endif; ?></tbody></table></div>
        <?php endif; ?>
<?php require_once(__DIR__ . '/../layouts/footer.php'); ?>
