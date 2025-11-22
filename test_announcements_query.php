<?php
require_once 'includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

$page = 1;
$perPage = 12;
$search = '';
$category = '';

$where = ["is_active = 1"];
$params = [];

if (!empty($search)) {
    $where[] = "(title LIKE ? OR content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $where[] = "category = ?";
    $params[] = $category;
}

$whereClause = implode(' AND ', $where);
$offset = ($page - 1) * $perPage;
$query = "SELECT * FROM announcements WHERE $whereClause ORDER BY display_order ASC, date DESC, created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

echo "Query: $query\n";
echo "Params: " . implode(', ', $params) . "\n";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Number of announcements: " . count($announcements) . "\n";
foreach ($announcements as $ann) {
    echo "ID: {$ann['id']}, Title: {$ann['title']}, Date: {$ann['date']}, Active: {$ann['is_active']}\n";
}
?>
