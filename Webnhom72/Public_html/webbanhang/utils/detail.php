<?php
require_once(__DIR__ . '/../../../Private/security/session.php');
session_start();
require_once(__DIR__ . '/../database/dbhelper.php');
require_once(__DIR__ . '/../../../Private/security/csrf.php');

$conn = getDB();
$id = (int)($_GET['id'] ?? 0);
$error = '';
$commentSent = false;

function detailEscape($value)
{
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$statement = mysqli_prepare($conn, 'SELECT p.id, p.title, p.price, p.thumbnail, p.description, c.name AS category_name FROM `product` p LEFT JOIN `category` c ON c.id = p.category_id WHERE p.id = ? AND p.deleted = 0 LIMIT 1');
mysqli_stmt_bind_param($statement, 'i', $id);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);
$product = mysqli_fetch_assoc($result);
mysqli_free_result($result);
mysqli_stmt_close($statement);

if ($product) {
	$schemaCheck = mysqli_query($conn, 'SELECT 1 FROM product_comments LIMIT 1');
	if (!$schemaCheck) {
		error_log('[webbanhang] Product comment tables are not installed: ' . mysqli_error($conn));
		$error = 'Chức năng bình luận hiện chưa được cài đặt trong cơ sở dữ liệu.';
	} else {
		mysqli_free_result($schemaCheck);
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment' && $error === '') {
		adminVerifyCsrf();
		if (!isset($_SESSION['user_id'])) {
			$error = 'Vui lòng đăng nhập để bình luận.';
		} else {
			authRequireUser($conn);
			$content = trim($_POST['content'] ?? '');
			$files = $_FILES['media'] ?? null;
			$hasFiles = $files && isset($files['name']) && is_array($files['name']);
			$mediaCount = $hasFiles ? count(array_filter($files['name'], static function ($name) {
				return $name !== '';
			})) : 0;

			if ($content === '' || strlen($content) > 5000) {
				$error = 'Nội dung bình luận phải từ 1 đến 5.000 ký tự.';
			} elseif ($mediaCount > 4) {
				$error = 'Mỗi bình luận chỉ được đăng tối đa 4 ảnh hoặc video.';
			} else {
				$uploadDirectory = __DIR__ . '/../uploads/comments';
				if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true)) {
					$error = 'Không thể tạo thư mục lưu tệp tải lên.';
				}
			}

			$mediaFiles = [];
			if ($error === '' && $hasFiles) {
				$finfo = new finfo(FILEINFO_MIME_TYPE);
				$allowedTypes = [
					'image/jpeg' => ['image', 'jpg', 5 * 1024 * 1024],
					'image/png' => ['image', 'png', 5 * 1024 * 1024],
					'image/gif' => ['image', 'gif', 5 * 1024 * 1024],
					'image/webp' => ['image', 'webp', 5 * 1024 * 1024],
					'video/mp4' => ['video', 'mp4', 50 * 1024 * 1024],
					'video/webm' => ['video', 'webm', 50 * 1024 * 1024],
					'video/quicktime' => ['video', 'mov', 50 * 1024 * 1024]
				];

				foreach ($files['name'] as $index => $originalName) {
					if ($originalName === '') {
						continue;
					}
					if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($files['tmp_name'][$index])) {
						$error = 'Một tệp tải lên không hợp lệ.';
						break;
					}
					$mimeType = $finfo->file($files['tmp_name'][$index]);
					if (!isset($allowedTypes[$mimeType]) || (int)$files['size'][$index] > $allowedTypes[$mimeType][2]) {
						$error = 'Chỉ hỗ trợ ảnh tối đa 5MB và video tối đa 50MB.';
						break;
					}
					$mediaFiles[] = [
						'type' => $allowedTypes[$mimeType][0],
						'path' => 'uploads/comments/' . bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType][1],
						'tmp' => $files['tmp_name'][$index]
					];
				}
			}

			if ($error === '') {
				$userId = (int)$_SESSION['user_id'];
				$commentStatement = mysqli_prepare($conn, 'INSERT INTO product_comments (product_id, user_id, content) SELECT ?, ?, ? FROM `user` WHERE id = ? AND deleted = 0');
				mysqli_stmt_bind_param($commentStatement, 'iisi', $id, $userId, $content, $userId);
				$commentSaved = mysqli_stmt_execute($commentStatement) && mysqli_stmt_affected_rows($commentStatement) === 1;
				$commentId = mysqli_insert_id($conn);
				mysqli_stmt_close($commentStatement);

				if (!$commentSaved) {
					$error = 'Không thể lưu bình luận.';
				} else {
					$mediaStatement = mysqli_prepare($conn, 'INSERT INTO product_comment_media (comment_id, media_type, file_path) VALUES (?, ?, ?)');
					foreach ($mediaFiles as $mediaFile) {
						$destination = __DIR__ . '/../' . $mediaFile['path'];
						if (!move_uploaded_file($mediaFile['tmp'], $destination)) {
							$error = 'Không thể lưu tệp đính kèm.';
							break;
						}
						mysqli_stmt_bind_param($mediaStatement, 'iss', $commentId, $mediaFile['type'], $mediaFile['path']);
						if (!mysqli_stmt_execute($mediaStatement)) {
							$error = 'Không thể lưu thông tin tệp đính kèm.';
							break;
						}
					}
					mysqli_stmt_close($mediaStatement);
					if ($error !== '') {
						mysqli_query($conn, 'DELETE FROM product_comments WHERE id = ' . (int)$commentId);
					} else {
						$commentSent = true;
					}
				}
			}
		}
	}
}

$comments = [];
if ($product && $error !== 'Chức năng bình luận hiện chưa sẵn sàng.') {
	$commentQuery = mysqli_prepare($conn, 'SELECT c.id, c.content, c.created_at, u.fullname FROM product_comments c INNER JOIN `user` u ON u.id = c.user_id WHERE c.product_id = ? AND u.deleted = 0 ORDER BY c.created_at DESC, c.id DESC');
	if ($commentQuery) {
		mysqli_stmt_bind_param($commentQuery, 'i', $id);
		mysqli_stmt_execute($commentQuery);
		$commentResult = mysqli_stmt_get_result($commentQuery);
		while ($comment = mysqli_fetch_assoc($commentResult)) {
			$comment['media'] = [];
			$mediaQuery = mysqli_prepare($conn, 'SELECT media_type, file_path FROM product_comment_media WHERE comment_id = ? ORDER BY id');
			mysqli_stmt_bind_param($mediaQuery, 'i', $comment['id']);
			mysqli_stmt_execute($mediaQuery);
			$mediaResult = mysqli_stmt_get_result($mediaQuery);
			while ($media = mysqli_fetch_assoc($mediaResult)) {
				$comment['media'][] = $media;
			}
			mysqli_free_result($mediaResult);
			mysqli_stmt_close($mediaQuery);
			$comments[] = $comment;
		}
		mysqli_free_result($commentResult);
		mysqli_stmt_close($commentQuery);
	}
}

$productImage = $product['thumbnail'] ?? '';
if ($productImage === '') {
	$productImage = 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=1000&q=80';
}
$utilsPageTitle = $product ? $product['title'] . ' | KhoaiLaptop' : 'Không tìm thấy sản phẩm | KhoaiLaptop';
require_once(__DIR__ . '/../assets/header.php');
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

			<section class="comments-section">
				<h2>Bình luận sản phẩm</h2>
				<?php if ($commentSent): ?><p class="utils-notice utils-notice--success">Bình luận của bạn đã được đăng.</p><?php endif; ?>
				<?php if ($error !== ''): ?><p class="utils-notice"><?php echo detailEscape($error); ?></p><?php endif; ?>
				<?php if (isset($_SESSION['user_id']) && authSessionIsValid($conn)): ?>
					<form class="utils-form comment-form" method="post" enctype="multipart/form-data">
						<?php echo adminCsrfField(); ?>
						<input type="hidden" name="action" value="add_comment">
						<label>Nội dung bình luận<textarea name="content" rows="5" maxlength="5000" required></textarea></label>
						<label>Ảnh hoặc video <small>(tối đa 4 tệp; ảnh 5MB, video 50MB)</small><input type="file" name="media[]" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime" multiple></label>
						<button class="primary-button" type="submit">Đăng bình luận</button>
					</form>
				<?php else: ?>
					<p>Vui lòng <a href="../admin/authen/loginregister.php">đăng nhập</a> để bình luận và đăng ảnh/video.</p>
				<?php endif; ?>
				<div class="comment-list">
					<?php foreach ($comments as $comment): ?>
						<article class="comment-card">
							<strong><?php echo detailEscape($comment['fullname']); ?></strong>
							<time datetime="<?php echo detailEscape($comment['created_at']); ?>"><?php echo detailEscape($comment['created_at']); ?></time>
							<p><?php echo nl2br(detailEscape($comment['content'])); ?></p>
							<?php if ($comment['media']): ?><div class="comment-media"><?php foreach ($comment['media'] as $media): ?><?php if ($media['media_type'] === 'image'): ?><img class="comment-media-image" src="<?php echo detailEscape('../' . $media['file_path']); ?>" alt="Ảnh đính kèm bình luận" tabindex="0" role="button" aria-label="Xem ảnh đính kèm kích thước lớn"><?php else: ?><video controls preload="metadata"><source src="<?php echo detailEscape('../' . $media['file_path']); ?>"></video><?php endif; ?><?php endforeach; ?></div><?php endif; ?>
						</article>
					<?php endforeach; ?>
					<?php if (!$comments): ?><p>Chưa có bình luận nào.</p><?php endif; ?>
				</div>
			</section>
		<?php else: ?>
			<h1>Không tìm thấy sản phẩm</h1>
		<?php endif; ?>
	</main>
<script src="../assets/js/comment-media.js" defer></script>
<?php require_once(__DIR__ . '/../assets/footer.php'); ?>
