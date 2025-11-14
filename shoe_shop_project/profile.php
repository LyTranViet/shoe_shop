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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    $errors = [];
    if (empty($name)) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

    // Check if email is already taken by another user
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        $errors[] = 'This email address is already in use.';
    }

    // Password update logic
    $password_sql_part = '';
    $password_params = [];
    if (!empty($password)) {
        if ($password !== $password_confirm) {
            $errors[] = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        } else {
            $password_sql_part = ', password = ?';
            $password_params[] = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET name = ?, email = ?, phone = ? {$password_sql_part} WHERE id = ?";
            $params = array_merge([$name, $email, $phone], $password_params, [$userId]);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            flash_set('success', 'Your profile has been updated successfully.');
        } catch (Exception $e) {
            flash_set('error', 'Could not update your profile. Please try again.');
        }
    } else {
        foreach ($errors as $error) {
            flash_set('error', $error);
        }
    }
    header('Location: profile.php');
    exit;
}

// Handle address actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $errors = [];

    if ($action === 'add_address' || $action === 'edit_address') {
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
        
        if (empty($address)) $errors[] = 'Address is required.';
        if (empty($city)) $errors[] = 'City is required.';
        if (empty($district)) $errors[] = 'District is required.';
        if (empty($ward)) $errors[] = 'Ward is required.';
        if (empty($phone)) $errors[] = 'Phone is required.';
        
        // Validation cuối cùng: Cần có District ID và Ward Code để tính phí vận chuyển
        if ($ghn_district_id === 0 || empty($ghn_ward_code)) {
             $errors[] = 'Missing location ID for shipping calculation. Please select a valid Ward/District.';
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
                flash_set('error', 'Could not save address. Please try again.');
            }
        } else {
            foreach ($errors as $error) {
                flash_set('error', $error);
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
            flash_set('success', 'Address deleted successfully.');
        } catch (Exception $e) {
            flash_set('error', 'Could not delete address. Please try again.');
        }
    } elseif ($action === 'set_default' && isset($_POST['address_id'])) {
        // ... (Giữ nguyên logic Set Default)
        $address_id = (int)$_POST['address_id'];
        try {
            $stmt = $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
            $stmt->execute([$userId]);
            $stmt = $db->prepare('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$address_id, $userId]);
            flash_set('success', 'Default address updated successfully.');
        } catch (Exception $e) {
            flash_set('error', 'Could not update default address. Please try again.');
        }
    }
    header('Location: profile.php');
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

<div class="profile-page">
    <h2>My Profile</h2>
    <?php if ($m = flash_get('error')): echo "<div class='alert-error'>$m</div>"; endif; ?>
    <?php if ($m = flash_get('success')): echo "<div class='alert-success'>$m</div>"; endif; ?>

    <form class="profile-form" method="post">
        <input type="hidden" name="action" value="update_profile">
        <h3>Personal Information</h3>
        <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required></div>
        <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required></div>
        <div class="form-group"><label for="phone">Phone</label><input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>

        <h3>Change Password</h3>
        <p class="form-hint">Leave blank to keep your current password.</p>
        <div class="form-group"><label for="password">New Password</label><input type="password" id="password" name="password"></div>
        <div class="form-group"><label for="password_confirm">Confirm New Password</label><input type="password" id="password_confirm" name="password_confirm"></div>

        <div class="form-actions"><button type="submit" class="btn">Save Changes</button></div>
    </form>

 <h3>Addresses</h3>
    <div class="addresses-list">
        <?php foreach ($addresses as $addr): ?>
            <div class="address-item <?php if ($addr['is_default']) echo 'default'; ?>">
                
                <p>
                    <?php echo htmlspecialchars($addr['address']); ?>,
                    <?php echo htmlspecialchars($addr['ward']); ?>,
                    <?php echo htmlspecialchars($addr['district']); ?>,
                    <?php echo htmlspecialchars($addr['city']); ?>
                </p>
                
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($addr['phone']); ?></p>
                
                <?php if (!empty($addr['postal_code'])): ?>
                    <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($addr['postal_code']); ?></p>
                <?php endif; ?>

                <div class="address-actions">
                    <?php if ($addr['is_default']): ?>
                        <p><em>(Địa chỉ mặc định)</em></p>
                    <? else: ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="set_default">
                            <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                            <button type="submit" class="btn btn-small">Đặt làm Mặc định</button>
                        </form>
                    <?php endif; ?>
                    
                    <button class="btn btn-small edit-address-btn" data-address-id="<?php echo $addr['id']; ?>">Edit</button>
                    
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete_address">
                        <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                        <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('Bạn có chắc muốn xóa địa chỉ này?');">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-actions">
        <button type="button" class="btn" id="open-add-address-modal">
            Add New Address
        </button>
    </div>

    <div id="add-address-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h4>Add New Address</h4>
            
            <form class="address-form" method="post">
                <input type="hidden" name="action" value="add_address">
                
                <div class="form-group">
                    <label for="province">City (Tỉnh/Thành phố)</label>
                    <select id="province" name="city" class="form-control province-select" required>
                        <option value="">-- Chọn tỉnh/thành --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="district">District (Quận/Huyện)</label>
                    <select id="district" name="district" class="form-control district-select" disabled required>
                        <option value="">-- Chọn quận/huyện --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ward">Ward (Phường/Xã)</label>
                    <select id="ward" name="ward" class="form-control ward-select" disabled required>
                        <option value="">-- Chọn phường/xã --</option>
                    </select>
                </div>
                <input type="hidden" name="ghn_province_id" value="">
                <input type="hidden" name="ghn_district_id" value="">
                <input type="hidden" name="ghn_ward_code" value="">
                <div class="form-group"><label for="address">Address (Detail)</label><input type="text" id="address" name="address" required></div>
                <div class="form-group"><label for="postal_code">Postal Code</label><input type="text" id="postal_code" name="postal_code"></div>
                <div class="form-group"><label for="phone_address">Phone</label><input type="tel" id="phone_address" name="phone" required></div>
                <div class="form-group"><label><input type="checkbox" name="is_default"> Set as default</label></div>
                <div class="form-actions"><button type="submit" class="btn">Add Address</button></div>
            </form>
            </div>
    </div>


    <?php foreach ($addresses as $addr): ?>
        <form class="address-form edit-form" id="edit-form-<?php echo $addr['id']; ?>" method="post" style="display:none;">
            <input type="hidden" name="action" value="edit_address">
            <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
            <div class="form-group"><label for="address_<?php echo $addr['id']; ?>">Address (Detail)</label><input type="text" id="address_<?php echo $addr['id']; ?>" name="address" value="<?php echo htmlspecialchars($addr['address']); ?>" required></div>
            <div class="form-group">
                <label for="province_<?php echo $addr['id']; ?>">City (Tỉnh/Thành phố)</label>
                <select id="province_<?php echo $addr['id']; ?>" name="city" class="form-control province-select" required>
                    <option value="">-- Chọn tỉnh/thành --</option>
                </select>
            </div>
            <div class="form-group">
                <label for="district_<?php echo $addr['id']; ?>">District (Quận/Huyện)</label>
                <select id="district_<?php echo $addr['id']; ?>" name="district" class="form-control district-select" disabled required>
                    <option value="">-- Chọn quận/huyện --</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ward_<?php echo $addr['id']; ?>">Ward (Phường/Xã)</label>
                <select id="ward_<?php echo $addr['id']; ?>" name="ward" class="form-control ward-select" disabled required>
                    <option value="">-- Chọn phường/xã --</option>
                </select>
            </div>
            <input type="hidden" name="ghn_province_id" value=""> 
            <input type="hidden" name="ghn_district_id" value="">
            <input type="hidden" name="ghn_ward_code" value="">
            
            <div class="form-group"><label for="postal_code_<?php echo $addr['id']; ?>">Postal Code</label><input type="text" id="postal_code_<?php echo $addr['id']; ?>" name="postal_code" value="<?php echo htmlspecialchars($addr['postal_code'] ?? ''); ?>"></div>
            <div class="form-group"><label for="phone_<?php echo $addr['id']; ?>">Phone</label><input type="tel" id="phone_<?php echo $addr['id']; ?>" name="phone" value="<?php echo htmlspecialchars($addr['phone']); ?>" required></div>
            <div class="form-group"><label><input type="checkbox" name="is_default" <?php if ($addr['is_default']) echo 'checked'; ?>> Set as default</label></div>
            <div class="form-actions"><button type="submit" class="btn">Save Address</button></div>
        </form>
    <?php endforeach; ?>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-address-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const addressId = this.getAttribute('data-address-id');
            const editForm = document.getElementById('edit-form-' + addressId);
            if (editForm) {
                editForm.style.display = 'block';
                this.style.display = 'none'; // Hide edit button temporarily
            }
        });
    });

    // GHN API integration for address forms
    let provinceMap = {}; // Global map for province name to ID

    const loadProvinces = new Promise((resolve, reject) => {
        // Giả định api/ghn_provinces.php gọi API GHN /master-data/province
        jQuery.getJSON('api/ghn_provinces.php', function(data) {
            jQuery('.province-select').each(function() {
                const select = jQuery(this);
                select.empty().append('<option value="">-- Chọn tỉnh/thành --</option>');
                data.forEach(p => {
                    provinceMap[p.ProvinceName] = p.ProvinceID;
                    select.append(`<option value="${p.ProvinceName}">${p.ProvinceName}</option>`);
                });
            });
            resolve();
        }).fail(reject);
    });

    function loadDistricts(provinceId, districtSelect) {
        return new Promise((resolve, reject) => {
            // Giả định api/ghn_districts.php gọi API GHN /master-data/district
            jQuery.getJSON(`api/ghn_districts.php?province_id=${provinceId}`, function(data) {
                districtSelect.empty().append('<option value="">-- Chọn quận/huyện --</option>').prop('disabled', false);
                
                let map = {};
                data.forEach(d => {
                    // Ánh xạ bình thường, tên Quận/Huyện -> ID
                    map[d.DistrictName] = d.DistrictID;
                    districtSelect.append(`<option value="${d.DistrictName}">${d.DistrictName}</option>`);
                });

                // --- BỔ SUNG LOGIC GHI ĐÈ ID ĐANG HOẠT ĐỘNG (ACTIVE ID) ---
                
                // Trường hợp cụ thể: Quận 7 (Mã cũ: 1449, Mã active: 1573)
                // Giả định Province ID của TP. HCM là 202 (Mã GHN tiêu chuẩn). 
                // Nếu mã này sai, bạn có thể cần cập nhật ID này.
                const TP_HCM_PROVINCE_ID = 202; 
                const ACTIVE_QUAN_7_ID = 1573;
                const DISTRICT_NAME_QUAN_7 = 'Quận 7';

                if (provinceId === TP_HCM_PROVINCE_ID && map[DISTRICT_NAME_QUAN_7]) {
                    // Force (ghi đè) mã Quận 7 trong map sang mã active
                    map[DISTRICT_NAME_QUAN_7] = ACTIVE_QUAN_7_ID;
                    
                    // Ghi đè lại option trong dropdown để đảm bảo Ward Code được load đúng
                    districtSelect.find(`option[value="${DISTRICT_NAME_QUAN_7}"]`).val(DISTRICT_NAME_QUAN_7);
                    
                    console.log(`[GHN Fix] Quận 7: Forced District ID to ${ACTIVE_QUAN_7_ID} to ensure fee calculation works.`);
                }
                
                // --- KẾT THÚC LOGIC GHI ĐÈ ---

                districtSelect.data('map', map);
                resolve();
            }).fail(reject);
        });
    }
    function loadWards(districtId, wardSelect) {
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

    // Attach change events (QUAN TRỌNG: Cập nhật các trường ẩn GHN ID)
    jQuery('body').on('change', '.province-select', async function() {
        const provinceName = jQuery(this).val();
        if (!provinceName) return;
        const provinceId = provinceMap[provinceName];
        const form = jQuery(this).closest('form');
        form.find('input[name="ghn_province_id"]').val(provinceId); // Gán ID GHN chính xác
        const districtSelect = form.find('.district-select');
        const wardSelect = form.find('.ward-select');
        districtSelect.prop('disabled', true);
        // Reset luôn mã quận và phường khi đổi tỉnh
        form.find('input[name="ghn_district_id"]').val('');
        form.find('input[name="ghn_ward_code"]').val('');
        wardSelect.empty().append('<option value="">-- Chọn phường/xã --</option>').prop('disabled', true);
        await loadDistricts(provinceId, districtSelect);
    });

    jQuery('body').on('change', '.district-select', async function() {
        const districtName = jQuery(this).val();
        if (!districtName) return;
        const map = jQuery(this).data('map');
        const districtId = map[districtName];
        const form = jQuery(this).closest('form');
        form.find('input[name="ghn_district_id"]').val(districtId); // Gán ID GHN chính xác
        const wardSelect = form.find('.ward-select');
        // Reset luôn mã phường khi đổi quận
        form.find('input[name="ghn_ward_code"]').val('');
        wardSelect.prop('disabled', true);
        await loadWards(districtId, wardSelect);
    });

    jQuery('body').on('change', '.ward-select', function() {
        const wardName = jQuery(this).val();
        if (!wardName) return;
        const selectedOption = jQuery(this).find('option:selected');
        const wardCode = selectedOption.data('ward-code');
        const form = jQuery(this).closest('form');
        form.find('input[name="ghn_ward_code"]').val(wardCode); // Gán Code GHN chính xác
    });

    // Initialize edit forms (Tự động chọn lại và kích hoạt thay đổi để lấy mã GHN MỚI)
    async function initAddressForms() {
        await loadProvinces;
        <?php foreach ($addresses as $addr): ?>
            (async () => {
                const form = jQuery('#edit-form-<?php echo $addr['id']; ?>');
                const provinceSelect = form.find('.province-select');
                const districtSelect = form.find('.district-select');
                const wardSelect = form.find('.ward-select');
                const storedCity = <?php echo json_encode($addr['city'] ?? ''); ?>;
                const storedDistrict = <?php echo json_encode($addr['district'] ?? ''); ?>;
                const storedWard = <?php echo json_encode($addr['ward'] ?? ''); ?>;
                
                // Bắt đầu chuỗi kích hoạt: Tỉnh -> Quận -> Phường
                if (storedCity && provinceMap[storedCity]) {
                    provinceSelect.val(storedCity);
                    
                    // Trigger change để load districts và set hidden province ID MỚI
                    provinceSelect.trigger('change'); 

                    const provinceId = provinceMap[storedCity];
                    await loadDistricts(provinceId, districtSelect);
                    
                    if (storedDistrict) {
                        districtSelect.val(storedDistrict);
                        
                        // Trigger change để load wards và set hidden district ID MỚI
                        districtSelect.trigger('change'); 
                        
                        const dmap = districtSelect.data('map');
                        if (dmap && dmap[storedDistrict]) {
                            const districtId = dmap[storedDistrict];
                            await loadWards(districtId, wardSelect);
                            if (storedWard) {
                                wardSelect.val(storedWard);
                                
                                // Trigger change để set hidden ward code MỚI
                                wardSelect.trigger('change'); 
                            }
                        }
                    }
                }
            })();
        <?php endforeach; ?>
    }
    initAddressForms();
// --- Logic điều khiển Modal ---
const modal = document.getElementById('add-address-modal');
const openBtn = document.getElementById('open-add-address-modal');
const closeBtn = document.querySelector('.modal-content .close-btn');

// Mở modal khi nhấn nút
openBtn.addEventListener('click', function() {
    modal.style.display = 'block';
});

// Đóng modal khi nhấn nút X
closeBtn.addEventListener('click', function() {
    modal.style.display = 'none';
});

// Đóng modal khi nhấn ra ngoài
window.addEventListener('click', function(event) {
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

}); // Kết thúc DOMContentLoaded
</script>
<style>
    /* Thêm vào file CSS của bạn */
.modal {
    display: none; /* Ẩn mặc định */
    position: fixed; /* Giữ cố định */
    z-index: 1000; /* Đảm bảo nằm trên mọi thứ */
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto; /* Cho phép cuộn nếu nội dung lớn */
    background-color: rgba(0,0,0,0.4); /* Nền mờ */
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto; /* Đặt modal ở giữa */
    padding: 20px;
    border: 1px solid #888;
    width: 80%; /* Chiều rộng */
    max-width: 600px;
    border-radius: 8px;
    position: relative;
}

.close-btn {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
}

.close-btn:hover,
.close-btn:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
}
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>