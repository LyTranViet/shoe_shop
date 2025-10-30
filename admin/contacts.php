<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

$db = get_db();

// --- Lấy danh sách liên hệ ---
try {
    $stmt = $db->query("SELECT * FROM contacts ORDER BY created_at DESC");
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $contacts = [];
}
?>

<div class="container py-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-primary mb-1">
                <i class="bi bi-chat-square-text me-2"></i> Quản lý liên hệ khách hàng
            </h3>
            <p class="text-muted mb-0">Theo dõi, xem và quản lý phản hồi của khách hàng.</p>
        </div>
        <span class="badge bg-gradient text-white px-4 py-2 fs-6 shadow-sm">
            <i class="bi bi-people-fill me-1"></i> <?= count($contacts) ?> liên hệ
        </span>
    </div>

    <!-- Nội dung -->
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="card-header bg-light py-3 px-4 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-semibold text-dark mb-0">
                <i class="bi bi-list-task me-2 text-primary"></i> Danh sách liên hệ
            </h5>
        </div>

        <div class="card-body p-0">
            <?php if (empty($contacts)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                    <h6 class="fw-semibold mb-1">Chưa có liên hệ nào</h6>
                    <p class="small mb-0">Khi khách hàng gửi phản hồi, dữ liệu sẽ hiển thị tại đây.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-header text-center small text-uppercase">
                            <tr>
                                <th>#</th>
                                <th>Họ và tên</th>
                                <th>Email</th>
                                <th>Nội dung</th>
                                <th>Ngày gửi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $index => $c): ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?= $index + 1 ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($c['email']) ?>" 
                                           class="text-decoration-none text-primary fw-semibold">
                                           <i class="bi bi-envelope-at me-1"></i><?= htmlspecialchars($c['email']) ?>
                                        </a>
                                    </td>
                                    <td class="small text-wrap"><?= nl2br(htmlspecialchars($c['message'])) ?></td>
                                    <td class="text-center text-muted small">
                                        <i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- CSS -->
<style>
body {
    background: #f4f6f9;
    font-family: "Inter", "Segoe UI", sans-serif;
}

.card {
    border-radius: 1rem;
    transition: 0.3s ease;
}
.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.badge.bg-gradient {
    background: linear-gradient(45deg, #4e73df, #1cc88a);
    border-radius: 1rem;
}

.table {
    border-collapse: separate;
    border-spacing: 0;
}

.table-header {
    background: linear-gradient(90deg, #4e73df 0%, #1cc88a 100%);
    color: #fff;
    letter-spacing: 0.5px;
}

.table th, .table td {
    padding: 1rem;
    vertical-align: middle;
}

.table-hover tbody tr {
    transition: all 0.25s ease;
}
.table-hover tbody tr:hover {
    background-color: #eef5ff !important;
    transform: scale(1.01);
}

.table tbody tr td:first-child {
    border-left: 4px solid transparent;
}
.table tbody tr:hover td:first-child {
    border-left: 4px solid #0d6efd;
}

a.text-primary:hover {
    color: #0056b3 !important;
    text-decoration: underline;
}

.bi {
    vertical-align: middle;
}

@media (max-width: 768px) {
    h3.fw-bold {
        font-size: 1.25rem;
    }
    .table th, .table td {
        font-size: 0.875rem;
        padding: 0.7rem;
    }
}
</style>
