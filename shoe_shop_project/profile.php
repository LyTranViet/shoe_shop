<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// require login
if (!is_logged_in()) {
    flash_set('info', 'Please login to view your profile.');
    header('Location: login.php');
    exit;
}

$db = get_db();
$userId = current_user_id();

// Handle address actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $errors = [];

    // Handle profile update
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name)) $errors[] = 'Họ và tên là bắt buộc.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';

        // Check if email is already taken by another user
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'Địa chỉ email này đã được sử dụng.';
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $email, $phone, $userId]);
                flash_set('success', 'Thông tin cá nhân đã được cập nhật.');
            } catch (Exception $e) {
                flash_set('error', 'Không thể cập nhật thông tin. Vui lòng thử lại.');
            }
        }
    }
    // Handle password update
    elseif ($action === 'update_password') {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($password)) {
            $errors[] = 'Vui lòng nhập mật khẩu mới.';
        } elseif ($password !== $password_confirm) {
            $errors[] = 'Mật khẩu xác nhận không khớp.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
        }

        if (empty($errors)) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $userId]);
                flash_set('success', 'Mật khẩu đã được thay đổi thành công.');
            } catch (Exception $e) {
                flash_set('error', 'Không thể thay đổi mật khẩu. Vui lòng thử lại.');
            }
        }
    }
    // Handle address actions
    elseif ($action === 'add_address' || $action === 'edit_address') {
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $ward = trim($_POST['ward'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        // Lấy các mã ID số cho việc tính phí vận chuyển từ POST data (từ hidden fields)
        $ghn_province_id = (int)($_POST['ghn_province_id'] ?? 0);
        $ghn_district_id = (int)($_POST['ghn_district_id'] ?? 0);
        $ghn_ward_code = trim($_POST['ghn_ward_code'] ?? ''); 
        
        // === Sửa Lỗi: ĐÃ LOẠI BỎ LOGIC PHP CỐ GẮNG LẤY MÃ GHN CŨ TỪ DB KHI EDIT ===
        // Hệ thống BẮT BUỘC phải dựa vào mã GHN mới do Javascript lấy từ API.
        // =========================================================================
        
        if (empty($address)) $errors[] = 'Địa chỉ cụ thể là bắt buộc.';
        if (empty($city)) $errors[] = 'Tỉnh/Thành phố là bắt buộc.';
        if (empty($district)) $errors[] = 'Quận/Huyện là bắt buộc.';
        if (empty($ward)) $errors[] = 'Phường/Xã là bắt buộc.';
        if (empty($phone)) $errors[] = 'Số điện thoại là bắt buộc.';
        
        // Validation cuối cùng: Cần có District ID và Ward Code để tính phí vận chuyển
        if ($ghn_district_id === 0 || empty($ghn_ward_code)) {
             $errors[] = 'Thiếu mã định danh vị trí. Vui lòng chọn Phường/Xã hợp lệ.';
        }

        if (empty($errors)) {
            try {
                // 1. Xử lý cờ mặc định (is_default)
                if ($is_default) {
                    // Reset other defaults
                    $stmt = $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
                    $stmt->execute([$userId]);
                }

                if ($action === 'add_address') {
                    // 2. Chèn địa chỉ mới vào bảng 'addresses'
                    $stmt = $db->prepare('INSERT INTO addresses (user_id, address, city, district, ward, postal_code, phone, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$userId, $address, $city, $district, $ward, $postal_code, $phone, $is_default]);
                    
                    $address_id = $db->lastInsertId(); // Lấy ID của địa chỉ vừa tạo

                    flash_set('success', 'Address added successfully.');

                } elseif ($action === 'edit_address' && isset($_POST['address_id'])) {
                    // 2. Cập nhật địa chỉ đã có trong bảng 'addresses'
                    $address_id = (int)$_POST['address_id'];
                    $stmt = $db->prepare('UPDATE addresses SET address = ?, city = ?, district = ?, ward = ?, postal_code = ?, phone = ?, is_default = ? WHERE id = ? AND user_id = ?');
                    $stmt->execute([$address, $city, $district, $ward, $postal_code, $phone, $is_default, $address_id, $userId]);
                    
                    flash_set('success', 'Address updated successfully.');
                }
                
                // 3. LƯU/CẬP NHẬT MÃ CODE vào bảng 'address_codes'
                if (isset($address_id) && $address_id > 0 && $ghn_district_id > 0 && !empty($ghn_ward_code)) {
                    $st_code = $db->prepare('
                        INSERT INTO address_codes (address_id, ghn_province_id, ghn_district_id, ghn_ward_code)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            ghn_province_id = VALUES(ghn_province_id),
                            ghn_district_id = VALUES(ghn_district_id),
                            ghn_ward_code = VALUES(ghn_ward_code)
                    ');
                    $st_code->execute([$address_id, $ghn_province_id, $ghn_district_id, $ghn_ward_code]);
                }

            } catch (Exception $e) {
                error_log("Address Save Error: " . $e->getMessage());
                flash_set('error', 'Không thể lưu địa chỉ. Vui lòng thử lại.');
            }
        } 
    } elseif ($action === 'delete_address' && isset($_POST['address_id'])) {
        // ... (Giữ nguyên logic Delete)
        $address_id = (int)$_POST['address_id'];
        try {
            // Khi xóa địa chỉ khỏi bảng 'addresses', các mã code trong 'address_codes'
            // sẽ tự động bị xóa nhờ FOREIGN KEY ON DELETE CASCADE
            $stmt = $db->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?');
            $stmt->execute([$address_id, $userId]);
            flash_set('success', 'Đã xóa địa chỉ thành công.');
        } catch (Exception $e) {
            flash_set('error', 'Không thể xóa địa chỉ. Vui lòng thử lại.');
        }
    } elseif ($action === 'set_default' && isset($_POST['address_id'])) {
        // ... (Giữ nguyên logic Set Default)
        $address_id = (int)$_POST['address_id'];
        try {
            $stmt = $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
            $stmt->execute([$userId]);
            $stmt = $db->prepare('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$address_id, $userId]);
            flash_set('success', 'Đã cập nhật địa chỉ mặc định.');
        } catch (Exception $e) {
            flash_set('error', 'Không thể cập nhật địa chỉ mặc định.');
        }
    }

    // Nếu có lỗi, flash và reload
    if (!empty($errors)) {
        foreach ($errors as $error) { flash_set('error', $error); }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']); // Reload the current page with its query params
    exit;
}

// Fetch current user data
$stmt = $db->prepare('SELECT name, email, phone FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Fetch user addresses with GHN codes (NEW: Join with address_codes)
$stmt = $db->prepare('
    SELECT a.*, c.ghn_province_id, c.ghn_district_id, c.ghn_ward_code 
    FROM addresses a 
    LEFT JOIN address_codes c ON a.id = c.address_id 
    WHERE a.user_id = ? 
    ORDER BY a.is_default DESC, a.created_at DESC
');
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-page-wrapper">
    <div class="profile-card">
        <h2 class="profile-title">Tài khoản của tôi</h2>
        <?php if ($m = flash_get('error')): echo "<div class='alert error'>$m</div>"; endif; ?>
        <?php if ($m = flash_get('success')): echo "<div class='alert success'>$m</div>"; endif; ?>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs" id="profileTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">Thông tin cá nhân</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-change" type="button" role="tab" aria-controls="password-change" aria-selected="false">Đổi mật khẩu</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address-book" type="button" role="tab" aria-controls="address-book" aria-selected="false">Sổ địa chỉ</button>
        </li>
    </ul>

    <!-- Tab content -->
    <div class="tab-content" id="profileTabContent">
        <!-- Personal Info Tab -->
        <div class="tab-pane show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                    <h5 class="tab-title">Thông tin cá nhân</h5>
                    <form method="post" action="profile.php#info">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-row">
                            <label for="name">Họ và tên</label>
                            <input type="text" class="input" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="email">Email</label>
                            <input type="email" class="input" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="phone">Số điện thoại</label>
                            <input type="tel" class="input" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn">Lưu thay đổi</button>
                        </div>
                    </form>
        </div>

        <!-- Change Password Tab -->
        <div class="tab-pane" id="password-change" role="tabpanel" aria-labelledby="password-tab">
                    <h5 class="tab-title">Đổi mật khẩu</h5>
                    <form method="post" action="profile.php#password-change">
                        <input type="hidden" name="action" value="update_password">
                        <div class="form-row">
                            <label for="password">Mật khẩu mới</label>
                            <input type="password" class="input" id="password" name="password" required>
                            <div class="helper">Mật khẩu nên có ít nhất 6 ký tự để đảm bảo an toàn.</div>
                        </div>
                        <div class="form-row">
                            <label for="password_confirm">Xác nhận mật khẩu mới</label>
                            <input type="password" class="input" id="password_confirm" name="password_confirm" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn">Đổi mật khẩu</button>
                        </div>
                    </form>
        </div>

        <!-- Address Book Tab -->
        <div class="tab-pane" id="address-book" role="tabpanel" aria-labelledby="address-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="tab-title" style="margin-bottom: 0;">Sổ địa chỉ của bạn</h5>
                <button type="button" class="btn small" id="open-add-address-modal">
                    <i class="fi fi-rr-plus"></i> Thêm địa chỉ mới
                </button>
            </div>

            <div class="addresses-list mt-3">
                <?php if (empty($addresses)): ?>
                    <div class="alert info">Bạn chưa có địa chỉ nào.</div>
                <?php else: ?>
                    <?php foreach ($addresses as $addr): ?>
                        <div class="address-block <?php if ($addr['is_default']) echo 'default'; ?>">
                                <div class="address-content">
                                    <p class="fw-bold mb-1"><?php echo htmlspecialchars($user['name']); ?> | <?php echo htmlspecialchars($addr['phone']); ?></p>
                                    <p class="text-muted mb-1">
                                        <?php echo htmlspecialchars($addr['address']); ?>, <?php echo htmlspecialchars($addr['ward']); ?>, <?php echo htmlspecialchars($addr['district']); ?>, <?php echo htmlspecialchars($addr['city']); ?>
                                    </p>
                                </div>
                                <div class="address-actions-wrapper">
                                    <div class="address-actions-main">
                                        <button class="btn small secondary edit-address-btn" data-address-json='<?php echo htmlspecialchars(json_encode($addr), ENT_QUOTES, 'UTF-8'); ?>'>Sửa</button>
                                        <form method="post" class="d-inline-block">
                                            <input type="hidden" name="action" value="delete_address">
                                            <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                            <button type="submit" class="btn small danger" onclick="return confirm('Bạn có chắc muốn xóa địa chỉ này?');">Xóa</button>
                                        </form>
                                    </div>
                                    <?php if (!$addr['is_default']): ?>
                                        <form method="post" class="d-inline-block">
                                            <input type="hidden" name="action" value="set_default">
                                            <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                            <button type="submit" class="btn small link">Đặt làm mặc định</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Address Modal (for both Add and Edit) -->
<div class="modal fade" id="address-modal" tabindex="-1" aria-labelledby="addressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addressModalLabel">Thêm địa chỉ mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="address-form" method="post">
                    <input type="hidden" name="action" value="add_address">
                    <input type="hidden" name="address_id" value="">

                    <div class="form-row-flex">
                        <div class="form-row" style="flex: 1;">
                            <label for="modal_phone">Số điện thoại</label>
                            <input type="tel" class="input" id="modal_phone" name="phone" required>
                        </div>
                        <div class="form-row" style="flex: 1;">
                            <label for="modal_postal_code">Mã bưu chính (Tùy chọn)</label>
                            <input type="text" class="input" id="modal_postal_code" name="postal_code">
                        </div>
                    </div>

                    <div class="form-row-flex">
                        <div class="form-row" style="flex: 1;">
                            <label for="modal_province">Tỉnh/Thành phố</label>
                            <select id="modal_province" name="city" class="input province-select" required>
                                <option value="">-- Chọn tỉnh/thành --</option>
                            </select>
                        </div>
                        <div class="form-row" style="flex: 1;">
                            <label for="modal_district">Quận/Huyện</label>
                            <select id="modal_district" name="district" class="input district-select" required disabled>
                                <option value="">-- Chọn quận/huyện --</option>
                            </select>
                        </div>
                        <div class="form-row" style="flex: 1;">
                            <label for="modal_ward">Phường/Xã</label>
                            <select id="modal_ward" name="ward" class="input ward-select" required disabled>
                                <option value="">-- Chọn phường/xã --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="modal_address">Địa chỉ cụ thể (Số nhà, tên đường...)</label>
                        <input type="text" class="input" id="modal_address" name="address" required>
                    </div>

                    <div class="form-row-check">
                        <input type="checkbox" name="is_default" id="modal_is_default">
                        <label for="modal_is_default">
                            Đặt làm địa chỉ mặc định
                        </label>
                    </div>

                    <!-- Hidden fields for GHN IDs -->
                    <input type="hidden" name="ghn_province_id" id="modal_ghn_province_id">
                    <input type="hidden" name="ghn_district_id" id="modal_ghn_district_id">
                    <input type="hidden" name="ghn_ward_code" id="modal_ghn_ward_code">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" form="address-form" class="btn">Lưu địa chỉ</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addressModalEl = document.getElementById('address-modal');

    // Logic to activate the correct tab on page load (e.g., after a form submission)
    const hash = window.location.hash;
    if (hash) {
        const tabTrigger = document.querySelector(`.nav-tabs button[data-bs-target="${hash}"]`);
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
            // Scroll to the tab content after it's shown
            setTimeout(() => {
                document.querySelector(hash).scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        }
    }

    const addressModal = new bootstrap.Modal(addressModalEl);
    const addressForm = document.getElementById('address-form');
    const modalTitle = document.getElementById('addressModalLabel');

    // GHN API integration for address forms
    let provinceMap = {}; // Global map for province name to ID

    const loadProvinces = new Promise((resolve, reject) => {
        // Giả định api/ghn_provinces.php gọi API GHN /master-data/province
        jQuery.getJSON('api/ghn_provinces.php', function(data) {
            const select = jQuery('#modal_province');
            select.empty().append('<option value="">-- Chọn tỉnh/thành --</option>');
            data.forEach(p => {
                provinceMap[p.ProvinceName] = p.ProvinceID;
                select.append(`<option value="${p.ProvinceName}">${p.ProvinceName}</option>`);
            });
            resolve();
        }).fail(reject);
    });

    async function loadDistricts(provinceId, districtSelect) {
        return new Promise((resolve, reject) => {
            // Giả định api/ghn_districts.php gọi API GHN /master-data/district
            jQuery.getJSON(`api/ghn_districts.php?province_id=${provinceId}`, function(data) {
                districtSelect.empty().append('<option value="">-- Chọn quận/huyện --</option>').prop('disabled', false);
                
                let map = {};
                data.forEach(d => {
                    map[d.DistrictName] = d.DistrictID;
                    districtSelect.append(`<option value="${d.DistrictName}">${d.DistrictName}</option>`);
                });

                districtSelect.data('map', map);
                resolve();
            }).fail(reject);
        });
    }
    async function loadWards(districtId, wardSelect) {
        return new Promise((resolve, reject) => {
            // Giả định api/ghn_wards.php gọi API GHN /master-data/ward
            jQuery.getJSON(`api/ghn_wards.php?district_id=${districtId}`, function(data) {
                wardSelect.empty().append('<option value="">-- Chọn phường/xã --</option>').prop('disabled', false);
                data.forEach(w => {
                    // Lấy WardCode chính xác và lưu vào data attribute
                    wardSelect.append(`<option value="${w.WardName}" data-ward-code="${w.WardCode}">${w.WardName}</option>`);
                });
                resolve();
            }).fail(reject);
        });
    }

    // Attach change events to modal dropdowns
    jQuery('#modal_province').on('change', async function() {
        const provinceName = jQuery(this).val();
        const districtSelect = jQuery('#modal_district');
        const wardSelect = jQuery('#modal_ward');

        districtSelect.prop('disabled', true);
        wardSelect.empty().append('<option value="">-- Chọn phường/xã --</option>').prop('disabled', true);
        jQuery('#modal_ghn_province_id, #modal_ghn_district_id, #modal_ghn_ward_code').val('');

        if (provinceName && provinceMap[provinceName]) {
            const provinceId = provinceMap[provinceName];
            jQuery('#modal_ghn_province_id').val(provinceId);
            await loadDistricts(provinceId, districtSelect);
        }
    });

    jQuery('#modal_district').on('change', async function() {
        const districtName = jQuery(this).val();
        const wardSelect = jQuery('#modal_ward');

        wardSelect.prop('disabled', true);
        jQuery('#modal_ghn_district_id, #modal_ghn_ward_code').val('');

        if (districtName) {
            const map = jQuery(this).data('map');
            const districtId = map[districtName];
            jQuery('#modal_ghn_district_id').val(districtId);
            await loadWards(districtId, wardSelect);
        }
    });

    jQuery('#modal_ward').on('change', function() {
        const wardName = jQuery(this).val();
        jQuery('#modal_ghn_ward_code').val('');
        if (wardName) {
            const selectedOption = jQuery(this).find('option:selected');
            const wardCode = selectedOption.data('ward-code');
            jQuery('#modal_ghn_ward_code').val(wardCode);
        }
    });

    // Handle "Add New Address" button
    document.getElementById('open-add-address-modal').addEventListener('click', async () => {
        await loadProvinces;
        modalTitle.textContent = 'Thêm địa chỉ mới';
        addressForm.reset();
        addressForm.querySelector('input[name="action"]').value = 'add_address';
        addressForm.querySelector('input[name="address_id"]').value = '';
        jQuery('#modal_district, #modal_ward').empty().prop('disabled', true);
        addressModal.show();
    });

    // Handle "Edit" buttons
    document.querySelectorAll('.edit-address-btn').forEach(button => {
        button.addEventListener('click', async function() {
            await loadProvinces;
            const addr = JSON.parse(this.dataset.addressJson);

            modalTitle.textContent = 'Chỉnh sửa địa chỉ';
            addressForm.reset();
            addressForm.querySelector('input[name="action"]').value = 'edit_address';
            addressForm.querySelector('input[name="address_id"]').value = addr.id;

            // Populate form
            jQuery('#modal_phone').val(addr.phone);
            jQuery('#modal_postal_code').val(addr.postal_code);
            jQuery('#modal_address').val(addr.address);
            jQuery('#modal_is_default').prop('checked', addr.is_default == 1);

            // Set and trigger dropdowns
            const provinceSelect = jQuery('#modal_province');
            const districtSelect = jQuery('#modal_district');
            const wardSelect = jQuery('#modal_ward');

            if (addr.city && provinceMap[addr.city]) {
                provinceSelect.val(addr.city);
                const provinceId = provinceMap[addr.city];
                jQuery('#modal_ghn_province_id').val(provinceId);

                await loadDistricts(provinceId, districtSelect);
                if (addr.district) {
                    districtSelect.val(addr.district);
                    const dmap = districtSelect.data('map');
                    if (dmap && dmap[addr.district]) {
                        const districtId = dmap[addr.district];
                        jQuery('#modal_ghn_district_id').val(districtId);

                        await loadWards(districtId, wardSelect);
                        if (addr.ward) {
                            wardSelect.val(addr.ward);
                            const selectedOption = wardSelect.find('option:selected');
                            const wardCode = selectedOption.data('ward-code');
                            jQuery('#modal_ghn_ward_code').val(wardCode);
                        }
                    }
                }
            }

            addressModal.show();
        });
    });

}); // Kết thúc DOMContentLoaded
</script>
<style>
/* General Layout */
.profile-page-wrapper {
    display: flex;
    justify-content: center;
    padding: 2rem 1rem;
}
.profile-card {
    width: 100%;
    max-width: 800px;
    background: #fff;
    border-radius: 18px;
    padding: 2rem;
    box-shadow: 0 8px 32px rgba(15,23,42,0.13);
    border: 1px solid #e3e8ee;
}
.profile-title {
    text-align: center;
    font-size: 2rem;
    color: var(--text-dark);
    font-weight: 800;
    margin-bottom: 1.5rem;
}

/* Tab Styles */
.nav-tabs {
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 1.5rem;
}
.nav-tabs .nav-item {
    margin-bottom: -2px;
}
.nav-tabs .nav-link {
    border: none;
    border-bottom: 2px solid transparent;
    color: #64748b;
    font-weight: 600;
    padding: 0.75rem 1.25rem;
    transition: all 0.2s ease-in-out;
}
.nav-tabs .nav-link:hover {
    color: var(--primary);
}
.nav-tabs .nav-link.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background-color: transparent;
}
.tab-pane {
    display: none;
}
.tab-pane.active {
    display: block;
    animation: fadeIn 0.5s;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.tab-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 1rem;
}

/* Form Styles (from login.php) */
.form-row { margin-bottom: 20px; }
.form-row label { display: block; font-weight: 700; margin-bottom: 7px; color: #334155; font-size: 15px; }
.input {
  width: 100%;
  padding: 13px 15px;
  border-radius: 10px;
  border: 1.5px solid #e6eefb;
  background: #f8fafc;
  font-size: 16px;
  box-sizing: border-box;
  transition: box-shadow .13s, border-color .13s;
}
.input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 2px var(--primary-light);
}
select.input {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
}
.form-actions {
  margin-top: 28px;
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: flex-start;
}
.form-row-flex {
    display: flex;
    gap: 1rem;
}
.form-row-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.form-row-check input[type="checkbox"] {
    width: 1.1em;
    height: 1.1em;
}
.form-row-check label {
    font-weight: 500;
}
.helper {
    font-size: 13.5px;
    color: var(--text-muted);
    margin-top: 4px;
}

/* Button Styles (from login.php) */
.btn {
  display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px;
  border-radius: 10px; background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%);
  color: #fff; border: 0; cursor: pointer; font-weight: 700; font-size: 1rem;
  box-shadow: 0 2px 8px rgba(14,165,233,0.07); transition: background 0.18s; text-decoration: none;
}
.btn:hover { color: #fff; }
.btn.secondary { background: #64748b; color: #fff; font-weight: 600; }
.btn.danger { background: var(--danger); }
.btn.small { padding: 8px 12px; font-size: 14px; }
.btn.link {
    background: none; color: var(--primary); box-shadow: none;
    padding: 4px; text-decoration: none; font-weight: 600;
}
.btn.link:hover { text-decoration: underline; }

/* Alert Styles (from login.php) */
.alert {
  padding: 13px 16px; border-radius: 9px; margin-bottom: 18px; font-size: 1rem;
  display: flex; align-items: center; gap: 10px; font-weight: 600;
}
.alert.error {
  background: #fff1f2; color: #b91c1c; border: 1.5px solid #fecaca;
}
.alert.error::before { content: '\26A0'; font-size: 1.3em; }
.alert.success {
  background: #ecfdf5; color: #047857; border: 1.5px solid #bbf7d0;
}
.alert.success::before { content: '\2714'; font-size: 1.2em; }
.alert.info {
    background: #f0f9ff; color: #0369a1; border: 1.5px solid #bae6fd;
}

/* Address List Styles */
.addresses-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.address-block {
    border: 1.5px solid #e6eefb;
    background: #f8fafc;
    border-radius: 10px;
    padding: 1rem;
    display: flex;
    position: relative; /* Cần thiết để định vị badge */
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    transition: box-shadow 0.2s;
}
.address-block.default {
    border-left: 4px solid var(--primary);
    background: #f0f9ff;
}
.address-block:hover {
    box-shadow: 0 0 0 2px var(--primary-light);
}
.address-actions-wrapper {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
    flex-shrink: 0;
}
.address-actions-main {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.address-actions-wrapper .btn, .address-actions-wrapper form {
    white-space: nowrap;
}

/* Modal Styles */
.modal-content {
    border-radius: 18px;
    border: none;
    box-shadow: 0 8px 32px rgba(15,23,42,0.13);
}
.modal-header {
    border-bottom: 1px solid #e3e8ee;
    padding: 1.25rem 1.5rem;
}
.modal-header .modal-title {
    font-weight: 700;
    font-size: 1.25rem;
}
.modal-body {
    padding: 1.5rem;
}
.modal-footer {
    border-top: 1px solid #e3e8ee;
    padding: 1rem 1.5rem;
}
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>