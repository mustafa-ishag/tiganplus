<?php
/**
 * معاينة الفاتورة الضريبية
 * Invoice Preview Page
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    $db = getDB();
} catch (Exception $e) {
    echo "خطأ في الاتصال: " . $e->getMessage();
    exit();
}

/**
 * دالة لإنشاء HTML للمعاينة
 */
function generatePreviewHTML($extract, $settings, $workOrders) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>فاتورة ضريبة - <?php echo htmlspecialchars($extract['extract_number']); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Cairo', Arial, sans-serif;
                direction: rtl;
                background: #f5f5f5;
                padding: 20px;
            }
            .invoice-container {
                max-width: 210mm;
                margin: 0 auto;
                background: white;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, <?php echo $settings['header_color']; ?>, <?php echo adjustColorBrightness($settings['header_color'], -20); ?>);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                font-size: 28px;
                font-weight: 800;
                margin-bottom: 10px;
            }
            .header p {
                font-size: 16px;
                opacity: 0.9;
            }
            .section {
                padding: 20px 30px;
                border-bottom: 1px solid #e9ecef;
            }
            .section-title {
                font-size: 18px;
                font-weight: 700;
                color: <?php echo $settings['header_color']; ?>;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid <?php echo $settings['accent_color']; ?>;
            }
            .info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            .info-box {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                border-right: 4px solid <?php echo $settings['accent_color']; ?>;
            }
            .info-label {
                font-weight: 600;
                color: <?php echo $settings['header_color']; ?>;
                margin-bottom: 5px;
            }
            .info-value {
                color: #2c3e50;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th {
                background: <?php echo $settings['header_color']; ?>;
                color: white;
                padding: 12px 8px;
                text-align: center;
                font-size: 13px;
                font-weight: 700;
            }
            td {
                padding: 10px 8px;
                text-align: center;
                border: 1px solid #dee2e6;
                font-size: 12px;
            }
            tr:nth-child(even) {
                background: #f8f9fa;
            }
            .summary {
                background: #f8f9fa;
                padding: 20px 30px;
            }
            .summary-table {
                max-width: 500px;
                margin: 0 auto;
            }
            .summary-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #dee2e6;
            }
            .summary-row.total {
                background: <?php echo $settings['header_color']; ?>;
                color: white;
                padding: 15px 20px;
                margin-top: 10px;
                border-radius: 8px;
                font-size: 18px;
                font-weight: 700;
            }
            .footer {
                text-align: center;
                padding: 20px;
                color: #6c757d;
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <!-- الرأس -->
            <div class="header">
                <h1><?php echo htmlspecialchars($settings['invoice_title']); ?></h1>
                <p>فاتورة جزئية - مستخلص رقم <?php echo htmlspecialchars($extract['extract_number']); ?></p>
            </div>

            <!-- بيانات الشركة والعميل -->
            <div class="section">
                <div class="info-grid">
                    <div>
                        <div class="section-title">🏢 بيانات المورد</div>
                        <div class="info-box">
                            <div class="info-label">الشركة:</div>
                            <div class="info-value"><?php echo htmlspecialchars($settings['supplier_name']); ?></div>
                        </div>
                        <div class="info-box" style="margin-top: 10px;">
                            <div class="info-label">العنوان:</div>
                            <div class="info-value"><?php echo htmlspecialchars($settings['supplier_address']); ?></div>
                        </div>
                        <div class="info-box" style="margin-top: 10px;">
                            <div class="info-label">الرقم الضريبي:</div>
                            <div class="info-value"><?php echo htmlspecialchars($settings['supplier_tax_number']); ?></div>
                        </div>
                    </div>
                    <div>
                        <div class="section-title">👤 بيانات العميل</div>
                        <div class="info-box">
                            <div class="info-label">العميل:</div>
                            <div class="info-value"><?php echo htmlspecialchars($settings['client_name']); ?></div>
                        </div>
                        <div class="info-box" style="margin-top: 10px;">
                            <div class="info-label">العنوان:</div>
                            <div class="info-value"><?php echo htmlspecialchars($settings['client_address']); ?></div>
                        </div>
                        <div class="info-box" style="margin-top: 10px;">
                            <div class="info-label">الرقم الضريبي:</div>
                            <div class="info-value"><?php echo htmlspecialchars($settings['client_tax_number']); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- تفاصيل الفاتورة -->
            <div class="section">
                <div class="section-title">📋 تفاصيل الفاتورة</div>
                <div class="info-grid">
                    <div class="info-box">
                        <div class="info-label">رقم الفاتورة:</div>
                        <div class="info-value">INV-<?php echo htmlspecialchars($extract['extract_number']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">تاريخ الفاتورة:</div>
                        <div class="info-value"><?php echo date('Y-m-d'); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">رقم العقد:</div>
                        <div class="info-value"><?php echo htmlspecialchars($settings['contract_number']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">صافي المستخلص:</div>
                        <div class="info-value"><?php echo number_format($extract['net_amount'], 2); ?> <?php echo htmlspecialchars($settings['currency']); ?></div>
                    </div>
                </div>
            </div>

            <!-- جدول أوامر العمل -->
            <div class="section">
                <div class="section-title">📊 تفاصيل أوامر العمل</div>
                <table>
                    <thead>
                        <tr>
                            <th>م</th>
                            <th>رقم أمر العمل</th>
                            <th>النوع</th>
                            <th>الوصف</th>
                            <th>المبلغ الخاضع للضريبة</th>
                            <th>نسبة الضريبة</th>
                            <th>قيمة الضريبة</th>
                            <th>المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $index = 1;
                        foreach ($workOrders as $wo) {
                            $taxableAmount = $wo['extract_value'];
                            $taxRate = $settings['tax_rate'];
                            $taxAmount = $taxableAmount * ($taxRate / 100);
                            $total = $taxableAmount + $taxAmount;
                        ?>
                        <tr>
                            <td><?php echo $index++; ?></td>
                            <td><?php echo htmlspecialchars($wo['work_order_number']); ?></td>
                            <td><?php echo htmlspecialchars($wo['type_code']); ?></td>
                            <td><?php echo htmlspecialchars($wo['work_order_type_description']); ?></td>
                            <td><?php echo number_format($taxableAmount, 2); ?></td>
                            <td><?php echo number_format($taxRate, 1); ?>%</td>
                            <td><?php echo number_format($taxAmount, 2); ?></td>
                            <td><?php echo number_format($total, 2); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- ملخص الفاتورة -->
            <div class="summary">
                <div class="section-title">💰 ملخص الفاتورة</div>
                <div class="summary-table">
                    <?php
                    $totalTaxable = 0;
                    $totalTax = 0;
                    foreach ($workOrders as $wo) {
                        $totalTaxable += $wo['extract_value'];
                        $totalTax += $wo['extract_value'] * ($settings['tax_rate'] / 100);
                    }
                    $grandTotal = $totalTaxable + $totalTax;
                    ?>
                    <div class="summary-row">
                        <span>إجمالي المبلغ الخاضع للضريبة:</span>
                        <span><?php echo number_format($totalTaxable, 2); ?> <?php echo $settings['currency']; ?></span>
                    </div>
                    <div class="summary-row">
                        <span>مجموع ضريبة القيمة المضافة <?php echo $settings['tax_rate']; ?>%:</span>
                        <span><?php echo number_format($totalTax, 2); ?> <?php echo $settings['currency']; ?></span>
                    </div>
                    <div class="summary-row">
                        <span>الحسومات:</span>
                        <span>0.00 <?php echo $settings['currency']; ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>المبلغ الإجمالي مع الضريبة:</span>
                        <span><?php echo number_format($grandTotal, 2); ?> <?php echo $settings['currency']; ?></span>
                    </div>
                </div>
            </div>

            <!-- الختام -->
            <div class="footer">
                <p>تم إنشاء هذه الفاتورة بواسطة نظام تِقان لإدارة المقاولات</p>
                <p>تاريخ الإنشاء: <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * دالة لتعديل سطوع اللون
 */
function adjustColorBrightness($hex, $steps) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));

    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
              . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
              . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

// جلب المستخلص الجزئي للمعاينة
try {
    // التحقق من وجود معرف المستخلص في URL
    $extractId = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($extractId) {
        // جلب مستخلص محدد
        $extractQuery = "
            SELECT pe.*,
                   b.name as branch_name,
                   b.code as branch_code,
                   u.full_name as created_by_name
            FROM partial_extracts pe
            LEFT JOIN branches b ON pe.branch_id = b.id
            LEFT JOIN users u ON pe.created_by = u.id
            WHERE pe.id = ?
        ";
        $stmt = $db->prepare($extractQuery);
        $stmt->execute([$extractId]);
        $extract = $stmt->fetch();
    } else {
        // جلب أول مستخلص جزئي للمعاينة
        $extractQuery = "
            SELECT pe.*,
                   b.name as branch_name,
                   b.code as branch_code,
                   u.full_name as created_by_name
            FROM partial_extracts pe
            LEFT JOIN branches b ON pe.branch_id = b.id
            LEFT JOIN users u ON pe.created_by = u.id
            ORDER BY pe.created_at DESC
            LIMIT 1
        ";
        $stmt = $db->query($extractQuery);
        $extract = $stmt->fetch();
    }

    if (!$extract) {
        throw new Exception('لا توجد مستخلصات جزئية للمعاينة');
    }

    // جلب أوامر العمل المرتبطة بالمستخلص
    $workOrdersQuery = "
        SELECT pewo.*, 
               wo.work_order_number,
               wot.type_code, 
               wot.description as work_order_type_name,
               wot.description as work_order_type_description
        FROM partial_extract_work_orders pewo
        LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        WHERE pewo.partial_extract_id = ?
        ORDER BY wo.work_order_number
    ";

    $stmt = $db->prepare($workOrdersQuery);
    $stmt->execute([$extract['id']]);
    $workOrders = $stmt->fetchAll();

    // جلب إعدادات الفواتير
    $settingsQuery = "SELECT * FROM invoice_settings WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1";
    $stmt = $db->query($settingsQuery);
    $settings = $stmt->fetch();

    if (!$settings) {
        throw new Exception('لم يتم العثور على إعدادات الفواتير. يرجى إعداد بيانات الشركة والعميل أولاً.');
    }

    // إنشاء HTML للمعاينة (بدون استخدام PhpSpreadsheet)
    // سنقوم بإنشاء HTML بسيط للمعاينة
    $invoiceHTML = generatePreviewHTML($extract, $settings, $workOrders);

} catch (Exception $e) {
    $error = $e->getMessage();
}

if (isset($error)) {
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <title>خطأ في المعاينة</title>
        <style>
            body { font-family: Cairo, Arial, sans-serif; padding: 20px; direction: rtl; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>خطأ في معاينة الفاتورة</h2>
            <p>{$error}</p>
            <a href='settings/invoice-settings.php'>إعدادات الفواتير</a> |
            <a href='test-invoice-export.php'>صفحة الاختبار</a>
        </div>
    </body>
    </html>";
    exit();
}

// عرض الفاتورة للمعاينة مع أزرار التحكم
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معاينة الفاتورة الضريبية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .control-panel {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: center;
            gap: 15px;
            align-items: center;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-family: 'Cairo', Arial, sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .btn-export {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        }

        .btn-back {
            background: linear-gradient(135deg, #9E9E9E, #757575);
            color: white;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(158, 158, 158, 0.4);
        }

        @media print {
            .control-panel {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="control-panel">
        <button onclick="window.print()" class="btn btn-print">
            🖨️ طباعة / حفظ PDF
        </button>
        <a href="extracts/partial/export-invoice.php?id=<?php echo $extract['id']; ?>" class="btn btn-export" download>
            📥 تحميل HTML
        </a>
        <a href="test-invoice-export.php" class="btn btn-back">
            ← رجوع
        </a>
    </div>

    <div class="control-panel" style="background: #fff3cd; border-bottom: 2px solid #ffc107; padding: 10px 20px;">
        <small style="color: #856404;">
            💡 <strong>نصيحة:</strong> لحفظ الفاتورة كـ PDF، اضغط على "طباعة / حفظ PDF" ثم اختر "حفظ كـ PDF" من خيارات الطابعة
        </small>
    </div>

    <?php echo $invoiceHTML; ?>
</body>
</html>
