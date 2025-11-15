<?php
if (!isset($db)) {
	require_once __DIR__ . '/../includes/init.php'; // Thêm dòng này để khởi tạo biến $displayName
	header('Location: index.php');
	exit;
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];

// --- Fetch data for the form ---
$products = $db->query("SELECT id, name, code FROM products ORDER BY name LIMIT 1000")->fetchAll();


// --- VIEW ACTION ---
if ($action === 'view' && $id > 0) {
    // 1. Fetch main receipt info
    $stmt = $db->prepare("
        SELECT er.*, u.name as employeeName 
        FROM export_receipt er 
        LEFT JOIN users u ON er.employee_id = u.id 
        WHERE er.id = ?
    ");
    $stmt->execute([$id]);
    $receipt = $stmt->fetch();

    if (!$receipt) {
        flash_set('error', 'Phiếu xuất không tồn tại.');
        header('Location: index.php?page=stock_out');
        exit;
    }

    // 2. Fetch receipt details
    $detail_stmt = $db->prepare("
        SELECT erd.*, p.name as product_name, ps.size, pb.batch_code
        FROM export_receipt_detail erd
        JOIN product_sizes ps ON erd.productsize_id = ps.id
        JOIN products p ON ps.product_id = p.id
        JOIN product_batch pb ON erd.batch_id = pb.id
        WHERE erd.export_id = ?
    ");
    $detail_stmt->execute([$id]);
    $receipt_details = $detail_stmt->fetchAll();

    include __DIR__ . '/stock_out_view.php';
    return; // Stop here to only show the view
}

?>
<div class="admin-container">
<?php if ($action === 'add'): ?>
	<div class="form-container" style="max-width: 1000px;">
		<div class="form-header">
			<h3>📤 Tạo Phiếu Xuất Kho</h3>
			<a href="index.php?page=stock_out" class="btn-back">← Quay lại danh sách</a>
		</div>
		<div>
			<div id="form-error-container" class="alert alert-error" style="display: none;"></div>

			<form id="stock-out-form" method="POST" action="handle_stock_out.php">
                <h4>Thông tin chung</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Loại xuất kho</label>
                        <select name="export_type" required>
                            <option value="Xuất hủy">Xuất hủy</option>
                            <option value="Điều chuyển">Điều chuyển</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nhân viên xuất</label>
                        <input type="text" value="<?= htmlspecialchars($displayName) ?>" readonly>
                    </div>
                </div>
				<div class="form-group">
					<label>Ghi chú</label>
					<textarea name="note" rows="2"></textarea>
				</div>

                <h4 style="margin-top: 30px;">Chi tiết sản phẩm xuất</h4>
                <div class="table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Sản phẩm</th>
                                <th style="width: 15%;">Size</th>
                                <th style="width: 25%;">Lô hàng (Tồn kho)</th>
                                <th style="width: 15%;">Số lượng xuất</th>
                                <th style="width: 10%;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="product-items-container-stock-out">
                            <!-- Rows will be added here by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <button type="button" id="add-product-btn-stock-out" class="btn" style="margin-top: 10px;">➕ Thêm sản phẩm</button>


				<div class="form-actions" style="margin-top: 30px;">
					<button type="submit" class="btn-submit">💾 Tạo Phiếu Xuất</button>
				</div>
			</form>
		</div>
	</div>

<?php else: // --- LIST VIEW ---
    $search_query = trim($_GET['q'] ?? '');
    $filter_product_id = (int)($_GET['product_id'] ?? 0);
    $itemsPerPage = 10;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $filter_product_name = '';
    $params = [];
    $where_clauses = [];
    $joins = ['LEFT JOIN users u ON er.employee_id = u.id'];

    if ($filter_product_id > 0) {
        $where_clause = "WHERE er.id IN (
            SELECT DISTINCT erd.export_id 
            FROM export_receipt_detail erd
            JOIN product_sizes ps ON erd.productsize_id = ps.id
            WHERE ps.product_id = :product_id
        )";
        $params[':product_id'] = $filter_product_id;

        // Lấy tên sản phẩm để hiển thị
        $product_stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
        $product_stmt->execute([$filter_product_id]);
        $filter_product_name = $product_stmt->fetchColumn();
    }

    if (!empty($search_query)) {
        $where_clauses[] = "(er.id = :q_id OR er.receipt_code LIKE :q_like OR er.export_type LIKE :q_like OR u.name LIKE :q_like)";
        $params[':q_id'] = $search_query;
        $params[':q_like'] = "%$search_query%";
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    $join_sql = implode(' ', $joins);

    $countSql = "SELECT COUNT(er.id) FROM export_receipt er $join_sql $where_sql";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
    $offset = ($currentPage - 1) * $itemsPerPage;

    $sql = "SELECT er.id, er.receipt_code, er.export_date, er.export_type, er.status, er.total_amount, er.note, u.name as employeeName 
            FROM export_receipt er $join_sql $where_sql
            ORDER BY er.export_date DESC LIMIT :limit OFFSET :offset";
    /* $sql = "
        SELECT er.id, er.receipt_code, er.export_date, er.export_type, er.status, er.total_amount, er.note, u.name as employeeName 
        FROM export_receipt er
        LEFT JOIN users u ON er.employee_id = u.id
        $where_clause
        ORDER BY er.export_date DESC
        LIMIT :limit OFFSET :offset
    ";
    */
    $stmt = $db->prepare($sql);
    
    // Bind params
    foreach ($params as $key => &$value) $stmt->bindParam($key, $value);

    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $receipts = $stmt->fetchAll();
?>
	<header class="admin-header">
        <h2>📤 Danh sách Phiếu Xuất <?= $filter_product_name ? 'cho sản phẩm "' . htmlspecialchars($filter_product_name) . '"' : '' ?></h2>
        <div class="admin-tools">
            <form class="search-form" method="get">
                <input type="hidden" name="page" value="stock_out">
                <input type="text" id="search-input-stock-out" name="q" placeholder="Tìm theo mã phiếu, loại, nhân viên..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit">Tìm</button>
            </form>
            <a href="index.php?page=stock_out&action=add" class="add-btn">➕ Tạo Phiếu Xuất</a>
        </div>
    </header>

	<div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Mã Phiếu</th>
                    <th>Ngày Xuất</th>
                    <th>Loại Xuất</th>
                    <th>Nhân Viên</th>
                    <th>Tổng Tiền</th>
                    <th>Ghi Chú</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($receipts)): ?>
                <tr><td colspan="8" class="empty">Chưa có phiếu xuất nào.</td></tr>
            <?php else: foreach ($receipts as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['receipt_code']) ?></strong></td>
                    <td><?= date('d/m/Y H:i', strtotime($r['export_date'])) ?></td>
                    <td><?= htmlspecialchars($r['export_type']) ?></td>
                    <td><?= htmlspecialchars($r['employeeName'] ?? 'N/A') ?></td>
                    <td><span class="price"><?= number_format($r['total_amount'], 0) ?>₫</span></td>
                    <td><?= htmlspecialchars($r['note'] ?? '—') ?></td>
                    <td>
                        <?php if ($r['status'] === 'Đang xử lý' && is_superadmin()): ?>
                            <select class="status-select" data-id="<?= $r['id'] ?>">
                                <option value="Đang xử lý" selected>Đang xử lý</option>
                                <option value="Đã xuất kho">Xác nhận & Xuất kho</option>
                                <option value="Đã hủy">Hủy phiếu</option>
                            </select>
                        <?php else: // For Admin, Staff, or other statuses, just show the status as text ?>
                            <span class="status <?= strtolower(str_replace(' ', '-', $r['status'] ?? '')) ?>" id="status-span-<?= $r['id'] ?>">
                                <?= htmlspecialchars($r['status'] ?? 'N/A') ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="index.php?page=stock_out&action=view&id=<?= $r['id'] ?>" class="btn view" title="Xem chi tiết"><i class="fi fi-rr-eye"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div id="pagination-container-stock-out">
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $query_params = [];
            if ($filter_product_id > 0) $query_params['product_id'] = $filter_product_id;
            if (!empty($search_query)) $query_params['q'] = $search_query;
            
            for ($i = 1; $i <= $totalPages; $i++): 
                $query_params['p'] = $i;
                $queryString = http_build_query($query_params);
            ?>
                <a href="index.php?page=stock_out&<?= $queryString ?>" class="page-btn <?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stockOutForm = document.getElementById('stock-out-form');
    if (stockOutForm) {
        const container = document.getElementById('product-items-container-stock-out');
        const addBtn = document.getElementById('add-product-btn-stock-out');

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
                <td>
                    <select name="batch_id[]" class="batch-select" required disabled>
                        <option value="">-- Chọn size trước --</option>
                    </select>
                </td>
                <td><input type="number" name="quantity[]" min="1" value="1" required></td>
                <td><button type="button" class="btn-remove-row">❌</button></td>
            `;
            container.appendChild(newRow);
        }

        addProductRow(); // Add initial row
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
            const target = e.target;
            const currentRow = target.closest('tr');

            // When a product is selected, fetch its sizes
            if (target.classList.contains('product-select')) {
                const productId = target.value;
                const sizeSelect = currentRow.querySelector('.size-select');
                const batchSelect = currentRow.querySelector('.batch-select');

                sizeSelect.innerHTML = '<option value="">Đang tải...</option>';
                batchSelect.innerHTML = '<option value="">-- Chọn size trước --</option>';
                sizeSelect.disabled = true;
                batchSelect.disabled = true;

                if (productId) {
                    fetch(`api_get_sizes.php?product_id=${productId}`)
                        .then(res => {
                            if (!res.ok) throw new Error(`HTTP ${res.status}`);
                            const contentType = res.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                throw new Error('Response is not JSON');
                            }
                            return res.json();
                        })
                        .then(data => {
                            console.log('Sizes API Response:', data);
                            sizeSelect.innerHTML = '<option value="">-- Chọn size --</option>';
                            if (data.success && data.sizes && data.sizes.length > 0) {
                                data.sizes.forEach(size => {
                                    const option = new Option(
                                        `${size.size}`,
                                        size.id
                                    );
                                    sizeSelect.appendChild(option);
                                });
                                sizeSelect.disabled = false;
                            } else {
                                sizeSelect.innerHTML = '<option value="">Chưa có size</option>';
                                console.warn('No sizes found for product:', productId);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching sizes:', error);
                            sizeSelect.innerHTML = '<option value="">❌ Lỗi: ' + error.message + '</option>';
                            alert('Lỗi tải size: ' + error.message);
                        })
                        .finally(() => {
                            if (sizeSelect.options.length <= 1) {
                                sizeSelect.disabled = true;
                            }
                        });
                } else {
                    sizeSelect.innerHTML = '<option value="">-- Chọn sản phẩm trước --</option>';
                    sizeSelect.disabled = true;
                }
            }

            // When a size is selected, fetch its batches
            if (target.classList.contains('size-select')) {
                const sizeId = target.value;
                const batchSelect = currentRow.querySelector('.batch-select');
                
                batchSelect.innerHTML = '<option value="">Đang tải...</option>';
                batchSelect.disabled = true;

                if (sizeId) {
                    fetch(`api_get_batches.php?size_id=${sizeId}`)
                        .then(res => {
                            if (!res.ok) throw new Error(`HTTP ${res.status}`);
                            const contentType = res.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                throw new Error('Response is not JSON');
                            }
                            return res.json();
                        })
                        .then(data => {
                            console.log('Batches API Response:', data);
                            batchSelect.innerHTML = '<option value="">-- Chọn lô --</option>';
                            if (data.success && data.batches && data.batches.length > 0) {
                                data.batches.forEach(batch => {
                                    const optionText = `${batch.batch_code} (Tồn: ${batch.quantity_remaining})`;
                                    const option = new Option(optionText, batch.id);
                                    batchSelect.appendChild(option);
                                });
                                batchSelect.disabled = false;
                            } else {
                                batchSelect.innerHTML = '<option value="">Hết hàng</option>';
                                console.warn('No batches found for size:', sizeId);
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching batches:', error);
                            batchSelect.innerHTML = '<option value="">❌ Lỗi: ' + error.message + '</option>';
                            alert('Lỗi tải lô hàng: ' + error.message);
                        })
                        .finally(() => {
                            if (batchSelect.options.length <= 1) {
                                batchSelect.disabled = true;
                            }
                        });
                } else {
                    batchSelect.innerHTML = '<option value="">-- Chọn size trước --</option>';
                    batchSelect.disabled = true;
                }
            }
        });

        // AJAX Form Submission
        stockOutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('.btn-submit');
            const originalBtnText = submitBtn.textContent;
            const errorContainer = document.getElementById('form-error-container');

            // Client-side validation
            const rows = container.querySelectorAll('tr');
            let hasError = false;
            if (rows.length === 0 || (rows.length === 1 && !rows[0].querySelector('.product-select').value)) {
                errorContainer.textContent = 'Vui lòng thêm ít nhất một sản phẩm vào phiếu xuất.';
                errorContainer.style.display = 'block';
                hasError = true;
            } else {
                rows.forEach(row => {
                    const productId = row.querySelector('.product-select').value;
                    const sizeId = row.querySelector('.size-select').value;
                    const batchId = row.querySelector('.batch-select').value;
                    const quantity = parseInt(row.querySelector('input[name="quantity[]"]').value);
                    if (!productId || !sizeId || !batchId || isNaN(quantity) || quantity <= 0) {
                        errorContainer.textContent = 'Vui lòng điền đầy đủ thông tin sản phẩm, size, lô hàng và số lượng hợp lệ cho tất cả các dòng.';
                        errorContainer.style.display = 'block';
                        hasError = true;
                    }
                });
            }

            if (hasError) {
                setTimeout(() => { errorContainer.style.display = 'none'; }, 5000);
                return;
            }

            if (!confirm('Bạn có chắc muốn tạo phiếu xuất kho này?')) {
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang xử lý...';
            errorContainer.style.display = 'none';

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json' }
            })
            .then(res => {
                console.log('Response status:', res.status);
                console.log('Response headers:', res.headers.get('content-type'));
                return res.text(); // Đổi sang text để xem raw response
            })
            
            .then(text => {
                console.log('=== RAW RESPONSE START ===');
                console.log(text);
                console.log('=== RAW RESPONSE END ===');
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
                errorContainer.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
                errorContainer.style.display = 'block';
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            });
        });
    }

    // Status change script for the list view - This should be outside the stockOutForm check
    document.querySelectorAll('.status-select').forEach(select => {
        const initialStatus = select.value;
        select.className = 'status-select status ' + initialStatus.toLowerCase().replace(/ /g, '-');

        select.addEventListener('change', function() {
            const exportId = this.dataset.id;
            const newStatus = this.value;
            const originalStatus = 'Đang xử lý';

            if (newStatus === originalStatus) return;

            let confirmMessage = `Bạn có chắc muốn đổi trạng thái thành "${newStatus}"?`;
            if (newStatus === 'Đã xuất kho') {
                confirmMessage += "\nHành động này sẽ trừ tồn kho và không thể hoàn tác.";
            }

            if (!confirm(confirmMessage)) {
                this.value = originalStatus; // Hoàn tác lựa chọn nếu người dùng hủy
                return;
            }

            this.disabled = true;

            const formData = new FormData();
            formData.append('export_id', exportId);
            formData.append('status', newStatus);

            fetch('handle_update_export_status.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Thay thế dropdown bằng một thẻ span tĩnh sau khi thành công
                    const container = this.parentElement;
                    const newStatusSpan = document.createElement('span');
                    newStatusSpan.className = 'status ' + data.new_status.toLowerCase().replace(/ /g, '-');
                    newStatusSpan.textContent = data.new_status;
                    container.innerHTML = '';
                    container.appendChild(newStatusSpan);
                } else {
                    alert('Lỗi: ' + data.message);
                    this.value = originalStatus; // Hoàn tác nếu có lỗi
                }
            })
            .catch(err => {
                console.error('Status update error:', err);
                alert('Đã xảy ra lỗi kết nối. Vui lòng thử lại.');
                this.value = originalStatus;
            })
            .finally(() => {
                // Chỉ bật lại dropdown nếu có lỗi, nếu không nó đã bị thay thế
                if (this.parentElement) {
                    this.disabled = false;
                }
            });
        });
    });
});
</script>
<style>
.status-select { border: none; -webkit-appearance: none; -moz-appearance: none; appearance: none; padding-right: 20px; background-position: right 5px center; background-repeat: no-repeat; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23333' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E"); }
.status.dang-xu-ly { background: #ffc107; color: #212529; }
.status.da-xuat, .status.da-xuat-kho { background: #28a745; color: #fff; }
.status.da-huy { background: #6c757d; color: #fff; }
</style>