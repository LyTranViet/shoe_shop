<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

$db = get_db();

// --- Lọc và tìm kiếm ---
$q = trim($_GET['q'] ?? '');

try {
	if ($q) {
		$stmt = $db->prepare("
			SELECT * FROM contacts
			WHERE name LIKE :q OR email LIKE :q OR message LIKE :q
			ORDER BY created_at DESC
		");
		$stmt->execute(['q' => "%$q%"]);
	} else {
		$stmt = $db->query("SELECT * FROM contacts ORDER BY created_at DESC");
	}
	$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$contacts = [];
}
?>

<div class="contact-admin">
	<header class="contact-header">
		<div>
			<h2>📨 Liên hệ khách hàng</h2>
			<p class="text-muted">Xem và phản hồi tin nhắn của khách hàng.</p>
		</div>
		<form method="get" class="search-bar">
			<input type="hidden" name="page" value="contacts">
			<input type="text" name="q" placeholder="🔍 Tìm theo tên, email, nội dung..."
				value="<?= htmlspecialchars($q) ?>">
			<button type="submit">Tìm</button>
		</form>
	</header>

	<div class="contact-table">
		<?php if (empty($contacts)): ?>
			<div class="empty-state">
				<i class="fi fi-rr-inbox"></i>
				<h4>Chưa có liên hệ nào</h4>
				<p>Dữ liệu sẽ hiển thị khi khách hàng gửi phản hồi.</p>
			</div>
		<?php else: ?>
			<table>
				<thead>
					<tr>
						<th>#</th>
						<th>Khách hàng</th>
						<th>Email</th>
						<th>Nội dung</th>
						<th>Ngày gửi</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($contacts as $i => $c): ?>
					<tr>
						<td><?= $i + 1 ?></td>
						<td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
						<td><a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a></td>
						<td><?= nl2br(htmlspecialchars($c['message'])) ?></td>
						<td><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
						<td class="text-center">
							<a href="send_single_mailjet.php?to=<?= urlencode($c['email']) ?>" class="btn-reply">Phản hồi</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<style>
/* ===== Tổng thể ===== */
.contact-admin {
	background: #ffffff;
	padding: 30px;
	border-radius: 16px;
	box-shadow: 0 2px 12px rgba(0,0,0,0.06);
	font-family: "Inter", "Segoe UI", sans-serif;
	color: #333;
}

/* ===== Header ===== */
.contact-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	margin-bottom: 20px;
	gap: 10px;
}
.contact-header h2 {
	font-weight: 600;
	color: #2d5fff;
	margin: 0;
}
.text-muted {
	color: #6c757d;
	font-size: 14px;
}

/* ===== Tìm kiếm ===== */
.search-bar {
	display: flex;
	gap: 8px;
	align-items: center;
}
.search-bar input {
	padding: 8px 14px;
	border-radius: 8px;
	border: 1px solid #ddd;
	min-width: 240px;
	transition: 0.2s;
}
.search-bar input:focus {
	border-color: #2d5fff;
	box-shadow: 0 0 0 0.15rem rgba(45,95,255,0.15);
	outline: none;
}
.search-bar button {
	background: #2d5fff;
	color: white;
	border: none;
	padding: 8px 16px;
	border-radius: 8px;
	cursor: pointer;
	transition: 0.3s;
}
.search-bar button:hover {
	background: #2448d8;
}

/* ===== Bảng ===== */
.contact-table table {
	width: 100%;
	border-collapse: collapse;
}
.contact-table th {
	background: #f5f7ff;
	color: #444;
	padding: 12px;
	text-align: left;
	font-weight: 600;
	font-size: 14px;
	border-bottom: 2px solid #e3e6f0;
}
.contact-table td {
	padding: 12px;
	border-bottom: 1px solid #eee;
	vertical-align: top;
	font-size: 14px;
}
.contact-table tr:hover {
	background: #f9fbff;
}
.contact-table a {
	color: #2d5fff;
	text-decoration: none;
}
.contact-table a:hover {
	text-decoration: underline;
}

/* ===== Nút phản hồi ===== */
.btn-reply {
	background: #e8f0ff;
	color: #2d5fff;
	padding: 6px 14px;
	border-radius: 6px;
	text-decoration: none;
	font-size: 13px;
	font-weight: 500;
	transition: 0.3s;
}
.btn-reply:hover {
	background: #2d5fff;
	color: #fff;
}

/* ===== Empty state ===== */
.empty-state {
	text-align: center;
	padding: 50px 10px;
	color: #777;
}
.empty-state i {
	font-size: 48px;
	color: #2d5fff;
	display: block;
	margin-bottom: 10px;
}

/* ===== Responsive ===== */
@media (max-width: 768px) {
	.contact-header {
		flex-direction: column;
		align-items: flex-start;
	}
	.search-bar {
		width: 100%;
	}
	table {
		font-size: 13px;
	}
}
</style>
