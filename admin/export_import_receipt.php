<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID không hợp lệ');
}

$id = (int)$_GET['id'];

// Lấy thông tin phiếu nhập
$stmt = $db->prepare("
    SELECT ir.*, s.supplierName, u.name as employeeName 
    FROM import_receipt ir 
    LEFT JOIN supplier s ON ir.supplier_id = s.supplier_id 
    LEFT JOIN users u ON ir.employee_id = u.id 
    WHERE ir.id = ?
");
$stmt->execute([$id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die('Không tìm thấy phiếu nhập');
}

// Lấy chi tiết sản phẩm
$detail_stmt = $db->prepare("
    SELECT ird.*, p.name as product_name, ps.size, ird.batch_code
    FROM import_receipt_detail ird
    JOIN product_sizes ps ON ird.productsize_id = ps.id
    JOIN products p ON ps.product_id = p.id
    WHERE ird.import_id = ?
");
$detail_stmt->execute([$id]);
$receipt_details = $detail_stmt->fetchAll();

// Tạo document Word
$phpWord = new PhpWord();

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
$section->addText(
    'PHIẾU NHẬP KHO',
    ['size' => 16, 'bold' => true],
    ['alignment' => 'center', 'spaceAfter' => 0]
);

// Ngày tháng và số phiếu
$importDate = new DateTime($receipt['import_date']);
$section->addText(
    'Ngày ' . $importDate->format('d') . ' tháng ' . $importDate->format('m') . ' năm ' . $importDate->format('Y'),
    ['size' => 13, 'italic' => true],
    ['alignment' => 'center', 'spaceAfter' => 0]
);
$section->addText(
    'Số: ' . htmlspecialchars($receipt['receipt_code']),
    ['size' => 13, 'bold' => true],
    ['alignment' => 'center']
);

$section->addTextBreak(1);

// Thông tin người giao
$section->addText('- Họ và tên người giao: ' . htmlspecialchars($receipt['supplierName'] ?? 'N/A'), ['size' => 13]);
$section->addText('- Theo ............ số ........... ngày ..... tháng ..... năm ..... của ......................', ['size' => 13]);
$section->addText('Nhập tại kho: Chính                 Địa điểm: .............................................', ['size' => 13]);

$section->addTextBreak(1);

// Bảng chi tiết sản phẩm
$tableStyle = [
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
    'width' => 100 * 50
];

$phpWord->addTableStyle('ProductTable', $tableStyle);
$table = $section->addTable('ProductTable');

// Header của bảng
$table->addRow(800);
$table->addCell(800, ['valign' => 'center'])->addText('STT', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
$table->addCell(3500, ['valign' => 'center'])->addText('Tên, nhãn hiệu, quy cách, phẩm chất vật tư, dụng cụ sản phẩm, hàng hoá', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
$table->addCell(1500, ['valign' => 'center'])->addText('Mã số', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
$table->addCell(1000, ['valign' => 'center'])->addText('Đơn vị tính', ['bold' => true, 'size' => 13], ['alignment' => 'center']);

// Cột số lượng với 2 cột con
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
foreach ($receipt_details as $item) {
    $table->addRow();
    $table->addCell(800)->addText($stt++, ['size' => 13], ['alignment' => 'center']);
    $table->addCell(3500)->addText(htmlspecialchars($item['product_name']) . ', Size: ' . htmlspecialchars($item['size']), ['size' => 13]);
    $table->addCell(1500)->addText(htmlspecialchars($item['batch_code']), ['size' => 13], ['alignment' => 'center']);
    $table->addCell(1000)->addText('Đôi', ['size' => 13], ['alignment' => 'center']);
    $table->addCell(1000)->addText(number_format($item['quantity'], 0), ['size' => 13], ['alignment' => 'center']);
    $table->addCell(1000)->addText(number_format($item['quantity'], 0), ['size' => 13], ['alignment' => 'center']);
    $table->addCell(1500)->addText(number_format($item['price'], 0), ['size' => 13], ['alignment' => 'right']);
    $table->addCell(2000)->addText(number_format($item['price'] * $item['quantity'], 0), ['size' => 13], ['alignment' => 'right']);
}

// Tổng cộng
$table->addRow();
$table->addCell(800)->addText('', ['size' => 13]);
$table->addCell(3500)->addText('Cộng', ['bold' => true, 'size' => 13], ['alignment' => 'right']);
$table->addCell(1500)->addText('x', ['size' => 13], ['alignment' => 'center']);
$table->addCell(1000)->addText('x', ['size' => 13], ['alignment' => 'center']);
$table->addCell(1000)->addText('x', ['size' => 13], ['alignment' => 'center']);
$table->addCell(1000)->addText('x', ['size' => 13], ['alignment' => 'center']);
$table->addCell(1500)->addText('x', ['size' => 13], ['alignment' => 'center']);
$table->addCell(2000)->addText(number_format($receipt['total_amount'], 0), ['bold' => true, 'size' => 13], ['alignment' => 'right']);

$section->addTextBreak(1);

// Chuyển số thành chữ (đơn giản)
function numberToWords($number) {
    $units = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    // Đây là hàm đơn giản, bạn có thể cải thiện
    return 'Số tiền bằng chữ'; // Placeholder
}

$section->addText('- Tổng số tiền (viết bằng chữ): ' . number_format($receipt['total_amount'], 0) . ' đồng', ['size' => 13, 'italic' => true]);
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

// Xuất file
$filename = 'Phieu_Nhap_Kho_' . $receipt['receipt_code'] . '.docx';

header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');
exit;