<?php
require_once('../database/dbhelper.php');
$conn = getDB();
$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$nameParts = preg_split('/\s+/', trim($_POST['name'] ?? ''), 2);
	$firstname = $nameParts[0] ?? '';
	$lastname = $nameParts[1] ?? '';
	$email = trim($_POST['email'] ?? '');
	$message = trim($_POST['message'] ?? '');

	if ($firstname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
		$error = 'Vui lòng nhập họ tên, email hợp lệ và nội dung.';
	} else {
		$statement = mysqli_prepare($conn, 'INSERT INTO `feedback` (id, firstname, lastname, email, phone_number, subject_name, note) VALUES (?, ?, ?, ?, ?, ?, ?)');
		$result = mysqli_query($conn, 'SELECT COALESCE(MAX(id), -1) + 1 AS next_id FROM `feedback`');
		$feedbackId = (int)mysqli_fetch_assoc($result)['next_id'];
		mysqli_free_result($result);
		$phone = '';
		$subject = 'Liên hệ từ trang cửa hàng';
		mysqli_stmt_bind_param($statement, 'issssss', $feedbackId, $firstname, $lastname, $email, $phone, $subject, $message);
		$sent = mysqli_stmt_execute($statement);
		mysqli_stmt_close($statement);
		if (!$sent) {
			$error = 'Không thể gửi liên hệ.';
		}
	}
}
$utilsPageTitle = 'Liên hệ | KhoaiLaptop';
require_once('../assets/header.php');
?>
	<main class="container utils-page utils-page--narrow">
		<a class="utils-back-link" href="index.php">← Về trang chính</a>
		<div class="utils-heading">
			<p class="eyebrow">LIÊN HỆ</p>
			<h1>Gửi lời nhắn cho chúng tôi</h1>
		</div>
		<?php if ($sent): ?><p class="utils-notice utils-notice--success">Đã nhận thông tin liên hệ của bạn.</p><?php endif; ?>
		<?php if ($error !== ''): ?><p class="utils-notice"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
		<form class="utils-form" method="post">
			<label>Họ và tên<input name="name" required></label>
			<label>Email<input type="email" name="email" required></label>
			<label>Nội dung<textarea name="message" rows="6" required></textarea></label>
			<button class="primary-button" type="submit">Gửi liên hệ</button>
		</form>
	</main>
<?php require_once('../assets/footer.php'); ?>
