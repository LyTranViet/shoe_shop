<?php
// === SAU KHI TẠO ĐƠN HÀNG THÀNH CÔNG ===
if ($order_id) {
    // Gửi lệnh JS xóa localStorage về frontend
    echo "<script>
        // XÓA HOÀN TOÀN MÃ GIẢM GIÁ
        localStorage.removeItem('product_coupon_code');
        localStorage.removeItem('product_coupon_data');
        localStorage.removeItem('shipping_coupon_code');
        localStorage.removeItem('shipping_coupon_data');
        console.log('[OK] Đã xóa toàn bộ mã giảm giá sau khi đặt hàng');
    </script>";

    // Chuyển hướng
    $_SESSION['order_success'] = $order_id;
    header("Location: checkout.php?order_success=$order_id");
    exit;
}
$couponCode = trim($_POST['coupon_code'] ?? '');
$shippingCouponCode = trim($_POST['shipping_coupon_code'] ?? '');
