<?php
// This file is included by admin/index.php
if (!isset($db)) {
    header('Location: index.php');
    exit;
}

// --- Search + Pagination ---
$search_query = trim($_GET['q'] ?? '');
$itemsPerPage = 10;
$currentPage = (int)($_GET['p'] ?? 1);

$where_clause = "WHERE ur.role_id = 2"; // Role 'Customer'
$params = [];

if (!empty($search_query)) {
    $where_clause .= " AND (u.name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search_query%";
}

// --- Count total customers ---
$countStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM users u 
    INNER JOIN user_roles ur ON u.id = ur.user_id 
    $where_clause
");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

// --- Fetch customer list ---
$params[':limit'] = $itemsPerPage;
$params[':offset'] = $offset;

$stmt = $db->prepare("
    SELECT u.* 
    FROM users u 
    INNER JOIN user_roles ur ON u.id = ur.user_id 
    $where_clause 
    ORDER BY u.created_at DESC 
    LIMIT :limit OFFSET :offset
");

foreach ($params as $key => &$value)
    $stmt->bindParam($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
$stmt->execute();
$customers = $stmt->fetchAll();
?>
<div class="admin-container">
    <header class="admin-header">
        <h2><i class="fi fi-rr-users-alt"></i> Quản lý khách hàng</h2>
        <div class="admin-tools">
            <form method="GET" class="search-form" action="">
                <input type="hidden" name="page" value="customers">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Tìm theo tên hoặc email...">
                <button type="submit">Tìm</button>
            </form>
        </div>
    </header>

    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Họ tên</th>
                    <th>Email</th>
                    <th>Số điện thoại</th>
                    <th>Ngày đăng ký</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="6" class="empty">Không tìm thấy khách hàng nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><?php echo (int)$c['id']; ?></td>
                            <td><?php echo htmlspecialchars($c['name']); ?></td>
                            <td><?php echo htmlspecialchars($c['email']); ?></td>
                            <td><?php echo htmlspecialchars($c['phone'] ?? '—'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($c['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="index.php?page=orders&q=<?php echo urlencode($c['name']); ?>" 
                                    class="btn view" title="Xem đơn hàng của khách hàng này">
                                        <i class="fi fi-rr-shopping-cart"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $query_params = !empty($search_query) ? '&q=' . urlencode($search_query) : '';
        for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="index.php?page=customers&p=<?php echo $i . $query_params; ?>" 
            class="page-btn <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
