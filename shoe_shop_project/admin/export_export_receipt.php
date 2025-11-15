<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID không hợp lệ');
}

$id = (int)$_GET['id'];

// Lấy thông tin phiếu xuất
$stmt = $db->prepare("
    SELECT er.*, u.name as employeeName 
    FROM export_receipt er 
    LEFT JOIN users u ON er.employee_id = u.id 
    WHERE er.id = ?
");
$stmt->execute([$id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die('Không tìm thấy phiếu xuất');
}

// Lấy chi tiết sản phẩm
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
$leftCell->addText('HỘ, CÁ NHÂN KINH DOANH:', ['size' => 13, 'bold' => true]);
$leftCell->addText('Địa chỉ: ...................................................', ['size' => 13]);

$rightCell = $headerTable->addCell(5000);
$rightCell->addText('Mẫu số 02 - VT', ['size' => 13, 'bold' => true], ['alignment' => 'center']);
$rightCell->addText('(Ban hành kèm theo Thông tư số', ['size' => 11], ['alignment' => 'right']);
$rightCell->addText('88/2021/TT-BTC ngày 11/10/2021 của Bộ Tài chính)', ['size' => 11], ['alignment' => 'right']);

// Tiêu đề
$section->addTextBreak(1);
$section->addText(
    'PHIẾU XUẤT KHO',
    ['size' => 16, 'bold' => true],
    ['alignment' => 'center', 'spaceAfter' => 0]
);

// Ngày tháng và số phiếu
$exportDate = new DateTime($receipt['export_date']);
$section->addText(
    'Ngày ' . $exportDate->format('d') . ' tháng ' . $exportDate->format('m') . ' năm ' . $exportDate->format('Y'),
    ['size' => 13, 'italic' => true],
    ['alignment' => 'center', 'spaceAfter' => 0]
);
$section->addText(
    'Số: ' . htmlspecialchars($receipt['receipt_code']),
    ['size' => 13, 'bold' => true],
    ['alignment' => 'center']
);

$section->addTextBreak(1);

// Thông tin người nhận
$section->addText('- Họ và tên người nhận hàng: ..................                  Địa chỉ (bộ phận): ..................', ['size' => 13]);
$section->addText('- Lý do xuất kho: ' . htmlspecialchars($receipt['export_type']), ['size' => 13]);
$section->addText('- Địa điểm xuất kho: Kho chính ...........................................', ['size' => 13]);

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
$table->addCell(3500, ['valign' => 'center'])->addText('Tên, nhãn hiệu, quy cách, phẩm chất vật liệu, dụng cụ, sản phẩm, hàng hoá', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
$table->addCell(1500, ['valign' => 'center'])->addText('Mã số', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
$table->addCell(1000, ['valign' => 'center'])->addText('Đơn vị tính', ['bold' => true, 'size' => 13], ['alignment' => 'center']);

// Cột số lượng với 2 cột con
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

$section->addText('- Tổng số tiền (viết bằng chữ): ' . number_format($receipt['total_amount'], 0) . ' đồng', ['size' => 13, 'italic' => true]);
$section->addText('- Số chứng từ gốc kèm theo: ....................................................................', ['size' => 13]);

$section->addTextBreak(2);

// Chữ ký
$signatureTable = $section->addTable();
$signatureTable->addRow();
$signatureTable->addCell(2500)->addText('Người nhận hàng', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
$signatureTable->addCell(2500)->addText('Thủ kho', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
$signatureTable->addCell(2500)->addText('Người lập biểu', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
$signatureTable->addCell(2500)->addText('Kế toán trưởng (Hoặc bộ phận có nhu cầu nhập)', ['bold' => true, 'size' => 11], ['alignment' => 'center']);

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

// Xuất file
$filename = 'Phieu_Xuat_Kho_' . $receipt['receipt_code'] . '.docx';

header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');
exit;