<?php
require_once('../database/dbhelper.php');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strlen($query) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Search query is too long'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$conn = getDB();
$like = '%' . $query . '%';
$statement = mysqli_prepare($conn, 'SELECT id, title, price, thumbnail FROM `product` WHERE deleted = 0 AND title LIKE ? ORDER BY id DESC LIMIT 8');
mysqli_stmt_bind_param($statement, 's', $like);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);
$items = [];

while ($row = mysqli_fetch_assoc($result)) {
    $items[] = [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'price' => number_format((float)$row['price'], 0, ',', '.') . ' đ',
        'image' => $row['thumbnail'] ?: ''
    ];
}

mysqli_free_result($result);
mysqli_stmt_close($statement);
echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
