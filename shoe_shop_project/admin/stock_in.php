<?php
if (!isset($db)) {
	header('Location: index.php');
	exit;
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];

// --- Fetch data for the form ---
$suppliers = $db->query("SELECT supplier_id, supplierName FROM supplier ORDER BY supplierName")->fetchAll();
$products = $db->query("SELECT id, name, code FROM products ORDER BY name")->fetchAll();

// --- VIEW ACTION ---
if ($action === 'view' && $id > 0) {
    $stmt = $db->prepare("SELECT ir.*, s.supplierName, u.name as employeeName FROM import_receipt ir LEFT JOIN supplier s ON ir.supplier_id = s.supplier_id LEFT JOIN users u ON ir.employee_id = u.id WHERE ir.id = ?");
    $stmt->execute([$id]);
    $receipt = $stmt->fetch();

    if (!$receipt) {
        flash_set('error', 'Phiếu nhập không tồn tại.');
        header('Location: index.php?page=stock_in');
        exit;
    }

    $detail_stmt = $db->prepare("SELECT ird.*, p.name as product_name, ps.size, ird.batch_code FROM import_receipt_detail ird JOIN product_sizes ps ON ird.productsize_id = ps.id JOIN products p ON ps.product_id = p.id WHERE ird.import_id = ?");
    $detail_stmt->execute([$id]);
    $receipt_details = $detail_stmt->fetchAll();

    include __DIR__ . '/stock_in_view.php';
    return; // Dừng ở đây để không hiển thị phần còn lại
}

?>
<div class="admin-container">
<?php if ($action === 'add'): ?>
	<div class="form-container" style="max-width: 1000px;">
		<div class="form-header">
			<h3>➕ Tạo Phiếu Nhập Kho</h3>
			<a href="index.php?page=stock_in" class="btn-back">← Quay lại danh sách</a>
		</div>
		<div>
			<?php if (!empty($errors)): ?>
				<div class="alert alert-error"><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
			<?php endif; ?>

			<form id="stock-in-form" method="POST" action="index.php?page=handle_stock_in">
                <h4>Thông tin chung</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Nhà cung cấp</label>
                        <select name="supplier_id" required>
                            <option value="">-- Chọn nhà cung cấp --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['supplier_id'] ?>"><?= htmlspecialchars($s['supplierName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nhân viên nhập</label>
                        <input type="text" value="<?= htmlspecialchars($displayName) ?>" readonly>
                    </div>
                </div>
				<div class="form-group">
					<label>Ghi chú</label>
					<textarea name="note" rows="2"></textarea>
				</div>

                <h4 style="margin-top: 30px;">Chi tiết sản phẩm</h4>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Sản phẩm</th>
                                <th style="width: 15%;">Size</th>
                                <th style="width: 15%;">Số lượng</th>
                                <th style="width: 20%;">Giá nhập (VNĐ)</th>
                                <th style="width: 10%;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="product-items-container">
                            <!-- Product rows will be added here by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <button type="button" id="add-product-btn" class="btn" style="margin-top: 10px;">➕ Thêm sản phẩm</button>

                <template id="product-row-template">
                    <!-- This is a template for a new product row -->
                </template>

				<div class="form-actions" style="margin-top: 30px;">
					<button type="submit" class="btn-submit">💾 Tạo Phiếu Nhập</button>
				</div>
			</form>
		</div>
	</div>

<?php else: // --- LIST VIEW ---
    $itemsPerPage = 10;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));    
    $search_query = trim($_GET['q'] ?? '');
    $where_clause = '';
    $params = [];
    
    if (!empty($search_query)) {
        // Tìm kiếm giống trang đơn hàng: ID chính xác hoặc Tên/Mã phiếu tương đối
        $where_clause = "WHERE ir.id = :q_id OR ir.receipt_code LIKE :q_like OR s.supplierName LIKE :q_like OR u.name LIKE :q_like";
        $params[':q_id'] = $search_query; // Cho ID
        $params[':q_like'] = "%$search_query%"; // Cho LIKE
    }
    $countSql = "SELECT COUNT(ir.id) FROM import_receipt ir LEFT JOIN supplier s ON ir.supplier_id = s.supplier_id LEFT JOIN users u ON ir.employee_id = u.id $where_clause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
    $offset = ($currentPage - 1) * $itemsPerPage;

	$sql = "
        SELECT ir.*, s.supplierName, u.name as employeeName 
        FROM import_receipt ir
        LEFT JOIN supplier s ON ir.supplier_id = s.supplier_id
        LEFT JOIN users u ON ir.employee_id = u.id $where_clause
        ORDER BY ir.import_date DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($sql);
    
    // Bind params cho câu lệnh chính
    foreach ($params as $key => &$value) $stmt->bindParam($key, $value);

    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
	$receipts = $stmt->fetchAll();
?>
	<header class="admin-header">
        <h2>🧾 Danh sách Phiếu Nhập</h2>
        <div class="admin-tools">
            <form class="search-form" method="get">
                <input type="hidden" name="page" value="stock_in">
                <input type="text" id="search-input-stock-in" name="q" placeholder="Tìm theo mã phiếu, NCC, nhân viên..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit">Tìm</button>
            </form>
            <a href="index.php?page=stock_in&action=add" class="add-btn">➕ Tạo Phiếu Nhập</a>
        </div>
    </header>

	<div class="table-wrapper">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success" style="margin: 10px;"><?= htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Mã Phiếu</th>
                    <th>Ngày Nhập</th>
                    <th>Nhà Cung Cấp</th>
                    <th>Nhân Viên</th>
                    <th>Tổng Tiền</th>
                    <th>Ghi Chú</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($receipts)): ?>
                <tr><td colspan="6" class="empty">Chưa có phiếu nhập nào.</td></tr>
            <?php else: foreach ($receipts as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['receipt_code']) ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($r['import_date'])) ?></td>
                    <td><?= htmlspecialchars($r['supplierName'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($r['employeeName'] ?? 'N/A') ?></td>
                    <td><span class="price"><?= number_format($r['total_amount'], 0) ?>₫</span></td>
                    <td><?= htmlspecialchars($r['note'] ?? '—') ?></td>
                    <td>
                        <?php if ($r['status'] === 'Đang chờ xác nhận' && is_superadmin()): ?>
                            <div class="status-dropdown-container">
                                <select class="status-select status-<?= strtolower(str_replace(' ', '-', $r['status'] ?? '')) ?>" data-id="<?= $r['id'] ?>">
                                    <option value="Đang chờ xác nhận" selected>Đang chờ xác nhận</option>
                                    <option value="Xác nhận">Xác nhận</option>
                                    <option value="Hủy">Hủy</option>
                                </select>
                            </div>
                        <?php else: // For Admin, Staff, or other statuses, just show the status as text ?>
                            <span class="status <?= strtolower(str_replace(' ', '-', $r['status'] ?? '')) ?>" id="status-span-<?= $r['id'] ?>">
                                <?= htmlspecialchars($r['status'] ?? 'N/A') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="index.php?page=stock_in&action=view&id=<?= $r['id'] ?>" class="btn view" title="Xem chi tiết"><i class="fi fi-rr-eye"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div id="pagination-container-stock-in">
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="index.php?page=stock_in&p=1&q=<?= urlencode($search_query) ?>">« Đầu</a>
                <a href="index.php?page=stock_in&p=<?= $currentPage - 1 ?>&q=<?= urlencode($search_query) ?>">‹ Trước</a>
            <?php endif; ?>
            <?php
            $window = 5;
            $half = floor($window / 2);
            $start = $currentPage - $half;
            $end = $currentPage + $half;
            if ($start < 1) {
                $start = 1;
                $end = min($window, $totalPages);
            }
            if ($end > $totalPages) {
                $end = $totalPages;
                $start = max(1, $end - $window + 1);
            }
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="index.php?page=stock_in&p=<?= $i ?>&q=<?= urlencode($search_query) ?>" class="<?= $i == $currentPage ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="index.php?page=stock_in&p=<?= $currentPage + 1 ?>&q=<?= urlencode($search_query) ?>">Tiếp ›</a>
                <a href="index.php?page=stock_in&p=<?= $totalPages ?>&q=<?= urlencode($search_query) ?>">Cuối »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('stock-in-form')) {
        const container = document.getElementById('product-items-container');
        const addBtn = document.getElementById('add-product-btn');

        function addProductRow() {
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <select name="product_id[]" class="product-select" required>
                        <option value="">-- Chọn sản phẩm --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name'] . ' (' . $p['code'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="size_id[]" class="size-select" required disabled>
                        <option value="">-- Chọn sản phẩm trước --</option>
                    </select>
                </td>
                <td><input type="number" name="quantity[]" min="1" value="1" required></td>
                <td><input type="number" name="price[]" min="0" value="0" required></td>
                <td><button type="button" class="btn-remove-row">❌</button></td>
            `;
            container.appendChild(newRow);
        }

        addProductRow();
        addBtn.addEventListener('click', addProductRow);

        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove-row')) {
                if (container.children.length > 1) {
                    e.target.closest('tr').remove();
                } else {
                    alert('Phải có ít nhất một sản phẩm!');
                }
            }
        });

        container.addEventListener('change', function(e) {
            if (e.target.classList.contains('product-select')) {
                const productId = e.target.value;
                const sizeSelect = e.target.closest('tr').querySelector('.size-select');
                
                sizeSelect.innerHTML = '<option value="">Đang tải...</option>';
                sizeSelect.disabled = true;

                if (productId) {
                    fetch(`api_get_sizes.php?product_id=${productId}`)
                        .then(response => {
                            if (!response.ok) throw new Error(`HTTP ${response.status}`);
                            const contentType = response.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                throw new Error('Response is not JSON');
                            }
                            return response.json();
                        })
                        .then(data => {
                            sizeSelect.innerHTML = '<option value="">-- Chọn size --</option>';
                            if (data.success && data.sizes && data.sizes.length > 0) {
                                data.sizes.forEach(size => {
                                    sizeSelect.appendChild(new Option(size.size, size.id));
                                });
                                sizeSelect.disabled = false;
                            } else {
                                sizeSelect.innerHTML = '<option value="">Sản phẩm chưa có size</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            sizeSelect.innerHTML = '<option value="">❌ Lỗi: ' + error.message + '</option>';
                        });
                } else {
                    sizeSelect.innerHTML = '<option value="">-- Chọn sản phẩm trước --</option>';
                    sizeSelect.disabled = true;
                }
            }
        });

        // ===== XỬ LÝ SUBMIT FORM =====
        const stockInForm = document.getElementById('stock-in-form');
        if (stockInForm) {
            // Thêm error container nếu chưa có
            let errorContainer = document.querySelector('.alert-error');
            if (!errorContainer) {
                errorContainer = document.createElement('div');
                errorContainer.className = 'alert alert-error';
                errorContainer.style.display = 'none';
                stockInForm.insertBefore(errorContainer, stockInForm.firstChild);
            }

            stockInForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const form = e.target;
                const submitBtn = form.querySelector('.btn-submit');
                const originalBtnText = submitBtn.textContent;

                // ===== VALIDATION CODE (đã bổ sung lại) =====
                const rows = container.querySelectorAll('tr');
                let hasError = false;

                if (rows.length === 0 || (rows.length === 1 && !rows[0].querySelector('.product-select').value)) {
                    alert('Vui lòng thêm ít nhất một sản phẩm vào phiếu nhập.');
                    return;
                }

                rows.forEach((row, index) => {
                    const productId = row.querySelector('.product-select').value;
                    const sizeId = row.querySelector('.size-select').value;
                    const quantity = parseInt(row.querySelector('input[name="quantity[]"]').value);
                    const price = parseFloat(row.querySelector('input[name="price[]"]').value);
                    
                    if (!productId || !sizeId || isNaN(quantity) || quantity <= 0 || isNaN(price) || price < 0) {
                        alert(`Dòng ${index + 1}: Vui lòng điền đầy đủ thông tin hợp lệ.`);
                        hasError = true;
                    }
                });

                if (hasError) return;
                if (!confirm('Bạn có chắc muốn tạo phiếu nhập kho này?')) return;

                submitBtn.disabled = true;
                submitBtn.textContent = 'Đang xử lý...';
                errorContainer.style.display = 'none';

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.text())
                .then(text => {
                    console.log('=== RAW RESPONSE ===');
                    console.log(text);
                    
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            alert(data.message);
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            }
                        } else {
                            errorContainer.textContent = data.message || 'Đã xảy ra lỗi không xác định.';
                            errorContainer.style.display = 'block';
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        errorContainer.textContent = 'Lỗi phản hồi từ server. Kiểm tra console.';
                        errorContainer.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Submit error:', err);
                    alert('Lỗi kết nối. Vui lòng thử lại.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                });
            });
        }

    } // end if (document.getElementById('stock-in-form'))

    // Status change script for the list view - This should be outside the stockInForm check
    document.querySelectorAll('.status-select').forEach(select => {
        const initialStatus = select.value;
        // Apply initial styling based on status
        select.className = 'status-select status ' + initialStatus.toLowerCase().replace(/ /g, '-');

        select.addEventListener('change', function() {
            const importId = this.dataset.id;
            const newStatus = this.value;
            const originalStatus = initialStatus; // Use initialStatus from closure

            if (newStatus === originalStatus) return;

            let confirmMessage = `Bạn có chắc muốn đổi trạng thái phiếu nhập thành "${newStatus}"?`;
            if (newStatus === 'Xác nhận') {
                confirmMessage += "\nHành động này sẽ cập nhật tồn kho và không thể hoàn tác.";
            } else if (newStatus === 'Hủy' && originalStatus === 'Xác nhận') {
                confirmMessage += "\nHành động này sẽ hoàn lại tồn kho đã nhập.";
            }

            if (!confirm(confirmMessage)) {
                this.value = originalStatus; // Revert selection if user cancels
                return;
            }

            this.disabled = true;

            const formData = new FormData();
            formData.append('import_id', importId);
            formData.append('status', newStatus);

            fetch('handle_update_import_status.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Replace dropdown with a static span after successful update
                    const container = this.parentElement;
                    const newStatusSpan = document.createElement('span');
                    newStatusSpan.className = 'status ' + data.new_status.toLowerCase().replace(/ /g, '-');
                    newStatusSpan.textContent = data.new_status;
                    container.innerHTML = ''; // Clear dropdown
                    container.appendChild(newStatusSpan);
                } else {
                    alert('Lỗi: ' + data.message);
                    this.value = originalStatus; // Revert if error
                }
            })
            .catch(err => {
                console.error('Status update error:', err);
                alert('Đã xảy ra lỗi kết nối. Vui lòng thử lại.');
                this.value = originalStatus;
            })
            .finally(() => {
                // Only re-enable dropdown if there was an error and it wasn't replaced
                if (this.parentElement && this.parentElement.querySelector('.status-select')) {
                    this.disabled = false;
                }
            });
        });
    });
});
</script>
<style>
.status-select { border: none; -webkit-appearance: none; -moz-appearance: none; appearance: none; padding-right: 20px; background-position: right 5px center; background-repeat: no-repeat; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E"); }
.status.dang-cho-xac-nhan { background: #ffc107; color: #212529; } /* Yellow for pending */
.status.xac-nhan { background: #28a745; color: #fff; } /* Green for confirmed */
.status.huy { background: #dc3545; color: #fff; } /* Red for cancelled */
</style>