<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

// Nạp thư viện PHPWord
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;

// Cấu hình PHPWord để sử dụng DomPDF
\PhpOffice\PhpWord\Settings::setPdfRendererPath(__DIR__ . '/../vendor/dompdf/dompdf');
\PhpOffice\PhpWord\Settings::setPdfRendererName('DomPDF');

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if (($type !== 'stock_in' && $type !== 'stock_out') || $id <= 0)
{
    die('Yêu cầu không hợp lệ.');
}

$db = get_db();
$phpWord = new PhpWord();
$filename = 'document';

try {
    // Thiết lập font mặc định
    $phpWord->setDefaultFontName('Times New Roman');
    $phpWord->setDefaultFontSize(13);

    // Tạo section
    $section = $phpWord->addSection([
        'marginLeft' => Converter::cmToTwip(2),
        'marginRight' => Converter::cmToTwip(2),
        'marginTop' => Converter::cmToTwip(2),
        'marginBottom' => Converter::cmToTwip(2),
    ]);

    if ($type === 'stock_in')
    {
        // 1. Lấy thông tin phiếu nhập
        $stmt = $db->prepare("SELECT ir.*, s.supplierName, u.name as employeeName FROM import_receipt ir LEFT JOIN supplier s ON ir.supplier_id = s.supplier_id LEFT JOIN users u ON ir.employee_id = u.id WHERE ir.id = ?");
        $stmt->execute([$id]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt)
            die('Phiếu nhập không tồn tại.');

        // 2. Lấy chi tiết sản phẩm
        $detail_stmt = $db->prepare("SELECT ird.*, p.name as product_name, ps.size, ird.batch_code FROM import_receipt_detail ird JOIN product_sizes ps ON ird.productsize_id = ps.id JOIN products p ON ps.product_id = p.id WHERE ird.import_id = ?");
        $detail_stmt->execute([$id]);
        $details = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Header - Thông tin đơn vị
        $headerTable = $section->addTable();
        $headerTable->addRow();
        $leftCell = $headerTable->addCell(5000);
        $leftCell->addText('Đơn vị: ..................', ['size' => 13]);
        $leftCell->addText('Bộ phận: ................', ['size' => 13]);

        $rightCell = $headerTable->addCell(5000);
        $rightCell->addText('Mẫu số 01 - VT', ['size' => 13, 'bold' => true], ['alignment' => 'right']);
        $rightCell->addText('(Ban hành theo Thông tư số 24/2017/TT-BTC', ['size' => 11], ['alignment' => 'right']);
        $rightCell->addText('ngày 28/3/2017 của Bộ Tài chính)', ['size' => 11], ['alignment' => 'right']);

        // Tiêu đề
        $section->addTextBreak(1);
        $section->addText('PHIẾU NHẬP KHO', ['size' => 16, 'bold' => true], ['alignment' => 'center', 'spaceAfter' => 0]);

        // Ngày tháng và số phiếu
        $importDate = new DateTime($receipt['import_date']);
        $section->addText('Ngày ' . $importDate->format('d') . ' tháng ' . $importDate->format('m') . ' năm ' . $importDate->format('Y'), ['size' => 13, 'italic' => true], ['alignment' => 'center', 'spaceAfter' => 0]);
        $section->addText('Số: ' . htmlspecialchars($receipt['receipt_code']), ['size' => 13, 'bold' => true], ['alignment' => 'center']);

        $section->addTextBreak(1);

        // Thông tin người giao
        $section->addText('- Họ và tên người giao: ' . htmlspecialchars($receipt['supplierName'] ?? 'N/A'), ['size' => 13]);
        $section->addText('- Theo ............ số ........... ngày ..... tháng ..... năm ..... của ......................', ['size' => 13]);
        $section->addText('Nhập tại kho: Chính                 Địa điểm: .............................................', ['size' => 13]);

        $section->addTextBreak(1);

        // Bảng chi tiết sản phẩm
        $tableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80, 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER, 'width' => 100 * 50];
        $phpWord->addTableStyle('ProductTable', $tableStyle);
        $table = $section->addTable('ProductTable');

        // Header của bảng
        $table->addRow(800);
        $table->addCell(800, ['valign' => 'center'])->addText('STT', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(3500, ['valign' => 'center'])->addText('Tên, nhãn hiệu, quy cách, phẩm chất vật tư, dụng cụ sản phẩm, hàng hoá', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1500, ['valign' => 'center'])->addText('Mã số', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1000, ['valign' => 'center'])->addText('Đơn vị tính', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $cellQuantity = $table->addCell(2000, ['valign' => 'center', 'gridSpan' => 2]);
        $cellQuantity->addText('Số lượng', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1500, ['valign' => 'center'])->addText('Đơn giá', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(2000, ['valign' => 'center'])->addText('Thành tiền', ['bold' => true, 'size' => 13], ['alignment' => 'center']);

        // Row phụ cho "Theo chứng từ" và "Thực nhập"
        $table->addRow(500);
        $table->addCell(800, ['valign' => 'center'])->addText('A', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(3500, ['valign' => 'center'])->addText('B', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1500, ['valign' => 'center'])->addText('C', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1000, ['valign' => 'center'])->addText('D', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1000, ['valign' => 'center'])->addText('Theo chứng từ', ['size' => 11], ['alignment' => 'center']);
        $table->addCell(1000, ['valign' => 'center'])->addText('Thực nhập', ['size' => 11], ['alignment' => 'center']);
        $table->addCell(1500, ['valign' => 'center'])->addText('1', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(2000, ['valign' => 'center'])->addText('2', ['bold' => true, 'size' => 13], ['alignment' => 'center']);

        // Dữ liệu sản phẩm
        $stt = 1;
        foreach ($details as $item)
        {
            $table->addRow();
            $table->addCell(800)->addText($stt++, ['size' => 13], ['alignment' => 'center']);
            $table->addCell(3500)->addText(htmlspecialchars($item['product_name']) . ', Size: ' . htmlspecialchars($item['size']), ['size' => 13]);
            $table->addCell(1500)->addText(htmlspecialchars($item['batch_code']), ['size' => 13], ['alignment' => 'center']);
            $table->addCell(1000)->addText('Đôi', ['size' => 13], ['alignment' => 'center']);
            $table->addCell(1000)->addText(number_format($item['quantity'], 0), ['size' => 13], ['alignment' => 'center']);
            $table->addCell(1000)->addText(number_format($item['quantity'], 0), ['size' => 13], ['alignment' => 'center']);
            $table->addCell(1500)->addText(number_format($item['price'], 0, ',', '.'), ['size' => 13], ['alignment' => 'right']);
            $table->addCell(2000)->addText(number_format($item['price'] * $item['quantity'], 0, ',', '.'), ['size' => 13], ['alignment' => 'right']);
        }

        // Tổng cộng
        $table->addRow();
        $table->addCell(null, ['gridSpan' => 7])->addText('Cộng', ['bold' => true, 'size' => 13], ['alignment' => 'right']);
        $table->addCell(2000)->addText(number_format($receipt['total_amount'], 0, ',', '.'), ['bold' => true, 'size' => 13], ['alignment' => 'right']);

        $section->addTextBreak(1);

        $section->addText('- Tổng số tiền (viết bằng chữ): ' . convert_number_to_words($receipt['total_amount']), ['size' => 13, 'italic' => true]);
        $section->addText('- Số chứng từ gốc kèm theo: ....................................................................', ['size' => 13]);

        $section->addTextBreak(2);

        // Chữ ký
        $signatureTable = $section->addTable();
        $signatureTable->addRow();
        $signatureTable->addCell(2500)->addText('Người lập phiếu', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('Người giao hàng', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('Thủ kho', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('Kế toán trưởng', ['bold' => true, 'size' => 13], ['alignment' => 'center']);

        $signatureTable->addRow(1000);
        $signatureTable->addCell(2500)->addText('(Ký, họ tên)', ['size' => 11, 'italic' => true], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('(Ký, họ tên)', ['size' => 11, 'italic' => true], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('(Ký, họ tên)', ['size' => 11, 'italic' => true], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('(Ký, họ tên)', ['size' => 11, 'italic' => true], ['alignment' => 'center']);

        $signatureTable->addRow();
        $signatureTable->addCell(2500)->addText(htmlspecialchars($receipt['employeeName'] ?? ''), ['size' => 13], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('', ['size' => 13]);
        $signatureTable->addCell(2500)->addText('', ['size' => 13]);
        $signatureTable->addCell(2500)->addText('', ['size' => 13]);

        $filename = 'Phieu_Nhap_Kho_' . $receipt['receipt_code'];

    }
    elseif ($type === 'stock_out')
    {
        // 1. Lấy thông tin phiếu xuất
        $stmt = $db->prepare("SELECT er.*, u.name as employeeName FROM export_receipt er LEFT JOIN users u ON er.employee_id = u.id WHERE er.id = ?");
        $stmt->execute([$id]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt)
            die('Phiếu xuất không tồn tại.');

        // 2. Lấy chi tiết sản phẩm
        $detail_stmt = $db->prepare("SELECT erd.*, p.name as product_name, ps.size, pb.batch_code FROM export_receipt_detail erd JOIN product_sizes ps ON erd.productsize_id = ps.id JOIN products p ON ps.product_id = p.id JOIN product_batch pb ON erd.batch_id = pb.id WHERE erd.export_id = ?");
        $detail_stmt->execute([$id]);
        $details = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Header - Thông tin đơn vị
        $headerTable = $section->addTable();
        $headerTable->addRow();
        $leftCell = $headerTable->addCell(5000);
        $leftCell->addText('HỘ, CÁ NHÂN KINH DOANH:', ['size' => 13, 'bold' => true]);
        $leftCell->addText('Địa chỉ: ...................................................', ['size' => 13]);

        $rightCell = $headerTable->addCell(5000);
        $rightCell->addText('Mẫu số 04 - VT', ['size' => 13, 'bold' => true], ['alignment' => 'right']);
        $rightCell->addText('(Ban hành kèm theo Thông tư số', ['size' => 11], ['alignment' => 'right']);
        $rightCell->addText('88/2021/TT-BTC ngày 11/10/2021 của Bộ Tài chính)', ['size' => 11], ['alignment' => 'right']);

        // Tiêu đề
        $section->addTextBreak(1);
        $section->addText('PHIẾU XUẤT KHO', ['size' => 16, 'bold' => true], ['alignment' => 'center', 'spaceAfter' => 0]);

        // Ngày tháng và số phiếu
        $exportDate = new DateTime($receipt['export_date']);
        $section->addText('Ngày ' . $exportDate->format('d') . ' tháng ' . $exportDate->format('m') . ' năm ' . $exportDate->format('Y'), ['size' => 13, 'italic' => true], ['alignment' => 'center', 'spaceAfter' => 0]);
        $section->addText('Số: ' . htmlspecialchars($receipt['receipt_code']), ['size' => 13, 'bold' => true], ['alignment' => 'center']);

        $section->addTextBreak(1);

        // Thông tin người nhận
        $section->addText('- Họ và tên người nhận hàng: ..................                  Địa chỉ (bộ phận): ..................', ['size' => 13]);
        $section->addText('- Lý do xuất kho: ' . htmlspecialchars($receipt['export_type']), ['size' => 13]);
        $section->addText('- Địa điểm xuất kho: Kho chính ...........................................', ['size' => 13]);

        $section->addTextBreak(1);

        // Bảng chi tiết sản phẩm
        $tableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80, 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER, 'width' => 100 * 50];
        $phpWord->addTableStyle('ProductTable', $tableStyle);
        $table = $section->addTable('ProductTable');

        // Header của bảng
        $table->addRow(800);
        $table->addCell(800, ['valign' => 'center'])->addText('STT', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(3500, ['valign' => 'center'])->addText('Tên, nhãn hiệu, quy cách, phẩm chất vật liệu, dụng cụ, sản phẩm, hàng hoá', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1500, ['valign' => 'center'])->addText('Mã số', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1000, ['valign' => 'center'])->addText('Đơn vị tính', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $cellQuantity = $table->addCell(2000, ['valign' => 'center', 'gridSpan' => 2]);
        $cellQuantity->addText('Số lượng', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1500, ['valign' => 'center'])->addText('Đơn giá', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(2000, ['valign' => 'center'])->addText('Thành tiền', ['bold' => true, 'size' => 13], ['alignment' => 'center']);

        // Row phụ cho "Yêu cầu" và "Thực xuất"
        $table->addRow(500);
        $table->addCell(800, ['valign' => 'center'])->addText('A', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(3500, ['valign' => 'center'])->addText('B', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1500, ['valign' => 'center'])->addText('C', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1000, ['valign' => 'center'])->addText('D', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(1000, ['valign' => 'center'])->addText('Yêu cầu', ['size' => 11], ['alignment' => 'center']);
        $table->addCell(1000, ['valign' => 'center'])->addText('Thực xuất', ['size' => 11], ['alignment' => 'center']);
        $table->addCell(1500, ['valign' => 'center'])->addText('3', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $table->addCell(2000, ['valign' => 'center'])->addText('4', ['bold' => true, 'size' => 13], ['alignment' => 'center']);

        // Dữ liệu sản phẩm
        $stt = 1;
        foreach ($details as $item)
        {
            $table->addRow();
            $table->addCell(800)->addText($stt++, ['size' => 13], ['alignment' => 'center']);
            $table->addCell(3500)->addText(htmlspecialchars($item['product_name']) . ', Size: ' . htmlspecialchars($item['size']), ['size' => 13]);
            $table->addCell(1500)->addText(htmlspecialchars($item['batch_code']), ['size' => 13], ['alignment' => 'center']);
            $table->addCell(1000)->addText('Đôi', ['size' => 13], ['alignment' => 'center']);
            $table->addCell(1000)->addText(number_format($item['quantity'], 0), ['size' => 13], ['alignment' => 'center']);
            $table->addCell(1000)->addText(number_format($item['quantity'], 0), ['size' => 13], ['alignment' => 'center']);
            $table->addCell(1500)->addText(number_format($item['price'], 0, ',', '.'), ['size' => 13], ['alignment' => 'right']);
            $table->addCell(2000)->addText(number_format($item['price'] * $item['quantity'], 0, ',', '.'), ['size' => 13], ['alignment' => 'right']);
        }

        // Tổng cộng
        $table->addRow();
        $table->addCell(null, ['gridSpan' => 7])->addText('Cộng', ['bold' => true, 'size' => 13], ['alignment' => 'right']);
        $table->addCell(2000)->addText(number_format($receipt['total_amount'], 0, ',', '.'), ['bold' => true, 'size' => 13], ['alignment' => 'right']);

        $section->addTextBreak(1);

        $section->addText('- Tổng số tiền (viết bằng chữ): ' . convert_number_to_words($receipt['total_amount']), ['size' => 13, 'italic' => true]);
        $section->addText('- Số chứng từ gốc kèm theo: ....................................................................', ['size' => 13]);

        $section->addTextBreak(2);

        // Chữ ký
        $signatureTable = $section->addTable();
        $signatureTable->addRow();
        $signatureTable->addCell(2500)->addText('Người nhận hàng', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('Thủ kho', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('Người lập biểu', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('Người đại diện hộ kinh doanh/cá nhân kinh doanh', ['bold' => true, 'size' => 11], ['alignment' => 'center']);

        $signatureTable->addRow(1000);
        $signatureTable->addCell(2500)->addText('(Ký, họ tên)', ['size' => 11, 'italic' => true], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('(Ký, họ tên)', ['size' => 11, 'italic' => true], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('(Ký, họ tên)', ['size' => 11, 'italic' => true], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('(Ký, họ tên)', ['size' => 11, 'italic' => true], ['alignment' => 'center']);

        $signatureTable->addRow();
        $signatureTable->addCell(2500)->addText('', ['size' => 13]);
        $signatureTable->addCell(2500)->addText('', ['size' => 13]);
        $signatureTable->addCell(2500)->addText(htmlspecialchars($receipt['employeeName'] ?? ''), ['size' => 13], ['alignment' => 'center']);
        $signatureTable->addCell(2500)->addText('', ['size' => 13]);

        $filename = 'Phieu_Xuat_Kho_' . $receipt['receipt_code'];
    }

    // 4. Lưu file tạm và xuất PDF
    $tempDocxFile = sys_get_temp_dir() . '/' . $filename . '.docx';
    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($tempDocxFile);

    // Chuyển đổi DOCX sang PDF
    $pdfPhpWord = \PhpOffice\PhpWord\IOFactory::load($tempDocxFile);
    $pdfWriter = \PhpOffice\PhpWord\IOFactory::createWriter($pdfPhpWord, 'PDF');

    // Gửi header để trình duyệt tải file PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Cache-Control: max-age=0');

    $pdfWriter->save('php://output');

    // Xóa file tạm
    unlink($tempDocxFile);
    exit;
}
catch (Exception $e)
{
    die('Lỗi: ' . $e->getMessage());
}

/**
 * Hàm chuyển số thành chữ (đơn giản)
 */
function convert_number_to_words($number)
{
    if (class_exists('NumberFormatter')) {
        $f = new NumberFormatter("vi", NumberFormatter::SPELLOUT);
        return ucfirst($f->format($number)) . ' đồng';
    }
    return '... đồng (NumberFormatter extension is not enabled)';
}

?>