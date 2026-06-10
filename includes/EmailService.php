<?php
/**
 * خدمة إرسال البريد الإلكتروني
 * Email Service using PHPMailer + Gmail SMTP
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
  private string $fromEmail = 'asd86064@gmail.com';
  private string $fromName = 'نظام تِقان - Etgan ERP';
  private string $appPassword = 'tqxrbosj rlabhcbt'; // App Password without spaces

  /**
   * إرسال بريد إشعار بطلب صرف
   * @param bool $isResubmission إذا كان الطلب معاد إرساله بعد تعديل
   */
  public function sendMaterialRequestNotification(array $request, array $details, bool $isResubmission = false): bool
  {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
      error_log('[EmailService] vendor/autoload.php not found');
      return false;
    }
    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
      // ===== إعدادات SMTP =====
      $mail->isSMTP();
      $mail->Host = 'smtp.gmail.com';
      $mail->SMTPAuth = true;
      $mail->Username = $this->fromEmail;
      $mail->Password = $this->appPassword;
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = 587;
      $mail->CharSet = 'UTF-8';
      $mail->SMTPDebug = 0;

      // ===== المرسل والمستقبل =====
      $mail->setFrom($this->fromEmail, $this->fromName);
      $mail->addAddress('musta.ishag@gmail.com', 'مدير المستودع');

      // ===== محتوى البريد =====
      $mail->isHTML(true);
      $subjectPrefix = $isResubmission ? '🔄 تعديل وإعادة إرسال طلب صرف: ' : '📦 طلب صرف جديد: ';
      $mail->Subject = $subjectPrefix . ($request['request_number'] ?? 'N/A');
      $mail->Body = $this->buildEmailHtml($request, $details, $isResubmission);
      $mail->AltBody = $this->buildEmailText($request, $details, $isResubmission);

      $mail->send();
      error_log('[EmailService] تم إرسال إشعار طلب الصرف: ' . ($request['request_number'] ?? ''));
      return true;

    } catch (Exception $e) {
      error_log('[EmailService] فشل إرسال البريد: ' . $mail->ErrorInfo);
      return false;
    }
  }

  /**
   * إرسال بريد إشعار بطلب تعديل لمقدم الطلب
   */
  public function sendRevisionRequestEmail(array $request, string $requesterEmail, string $approverName, string $notes): bool
  {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload))
      return false;
    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
      $mail->isSMTP();
      $mail->Host = 'smtp.gmail.com';
      $mail->SMTPAuth = true;
      $mail->Username = $this->fromEmail;
      $mail->Password = $this->appPassword;
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = 587;
      $mail->CharSet = 'UTF-8';

      $mail->setFrom($this->fromEmail, $this->fromName);
      $mail->addAddress($requesterEmail, $request['requested_by_name'] ?? 'مقدم الطلب');

      $mail->isHTML(true);
      $mail->Subject = '🔄 مطلوب تعديل على طلب الصرف: ' . ($request['request_number'] ?? '');

      $requestNumber = htmlspecialchars($request['request_number'] ?? 'N/A');
      $requesterNameHtml = htmlspecialchars($request['requested_by_name'] ?? 'مقدم الطلب');
      $approverNameHtml = htmlspecialchars($approverName);
      $workOrderNum = htmlspecialchars($request['work_order_number'] ?? '-');
      $notesHtml = nl2br(htmlspecialchars($notes));

      $mail->Body = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:20px;background:#e9eef6;font-family:'Segoe UI',Tahoma,Arial,sans-serif;direction:rtl;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.05);">
  <tr>
    <td style="background:linear-gradient(145deg,#e67e22 0%,#f39c12 100%);padding:30px 20px;text-align:center;color:#fff;">
      <div style="font-size:40px;margin-bottom:10px;">🔄</div>
      <h1 style="margin:0;font-size:22px;">مطلوب تعديل على طلب الصرف</h1>
      <p style="margin:10px 0 0;font-size:14px;opacity:0.9;">رقم الطلب: <strong>{$requestNumber}</strong></p>
    </td>
  </tr>
  <tr>
    <td style="padding:20px;">
      <p style="margin:0 0 15px;font-size:15px;color:#333;">مرحباً <strong>{$requesterNameHtml}</strong>،</p>
      <p style="margin:0 0 20px;font-size:14px;color:#555;line-height:1.6;">
        تم طلب تعديل على طلب الصرف رقم <strong style="color:#2353a4;">{$requestNumber}</strong>
        الخاص بأمر العمل <strong style="color:#2353a4;">{$workOrderNum}</strong>.
      </p>

      <div style="background:#fff8f0;padding:15px;border-radius:8px;border:1px solid #f0c84a;border-right:4px solid #e67e22;margin-bottom:20px;">
        <h3 style="margin:0 0 10px;font-size:14px;color:#e67e22;">👤 طالب التعديل:</h3>
        <p style="margin:0 0 10px;font-size:14px;color:#333;font-weight:bold;">{$approverNameHtml}</p>
        <h3 style="margin:0 0 10px;font-size:14px;color:#e67e22;">📝 ملاحظات التعديل المطلوب:</h3>
        <p style="margin:0;font-size:14px;color:#333;line-height:1.6;">{$notesHtml}</p>
      </div>

      <div style="text-align:center;margin-bottom:20px;">
        <a href="http://localhost/etganplus/public/inventory/material-requests/index.php"
           style="display:inline-block;background:linear-gradient(135deg,#e67e22,#d35400);
                  color:#fff;text-decoration:none;padding:13px 34px;
                  border-radius:30px;font-size:14px;font-weight:700;">
          تعديل الطلب في النظام &larr;
        </a>
      </div>

      <p style="margin:0;font-size:13px;color:#888;text-align:center;">
        هذه رسالة آلية من نظام تِقان، يرجى عدم الرد عليها.
      </p>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

      $mail->send();
      return true;
    } catch (Exception $e) {
      error_log('[EmailService] Revision Email failed: ' . $mail->ErrorInfo);
      return false;
    }
  }

  /**
   * إرسال بريد إشعار باعتماد طلب الصرف لمقدم الطلب
   */
  public function sendMaterialRequestApprovalNotification(array $request, array $details, string $requesterEmail, string $level): bool
  {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload))
      return false;
    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
      $mail->isSMTP();
      $mail->Host = 'smtp.gmail.com';
      $mail->SMTPAuth = true;
      $mail->Username = $this->fromEmail;
      $mail->Password = $this->appPassword;
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = 587;
      $mail->CharSet = 'UTF-8';

      $mail->setFrom($this->fromEmail, $this->fromName);
      $mail->addAddress($requesterEmail, $request['requested_by_name'] ?? 'مقدم الطلب');

      $mail->isHTML(true);
      $mail->Subject = '✅ تم اعتماد طلب الصرف: ' . ($request['request_number'] ?? '');

      $levelNames = [
        'warehouse' => 'المستودع',
        'project' => 'المشروع',
        'branch' => 'الفرع'
      ];
      $levelName = $levelNames[$level] ?? $level;

      $mail->Body = $this->buildApprovalEmailHtml($request, $details, $levelName);

      $mail->send();
      return true;
    } catch (Exception $e) {
      error_log('[EmailService] Approval Email failed: ' . $mail->ErrorInfo);
      return false;
    }
  }

  /**
   * بناء محتوى HTML لبريد الاعتماد
   */
  private function buildApprovalEmailHtml(array $request, array $details, string $levelName): string
  {
    $requestNumber = htmlspecialchars($request['request_number'] ?? 'N/A');
    $status = $this->getStatusLabel($request['status'] ?? '');
    $requesterName = htmlspecialchars($request['requested_by_name'] ?? '-');
    $workOrderNum = htmlspecialchars($request['work_order_number'] ?? '-');

    return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
body{margin:0;padding:0;background:#e9eef6;font-family:'Segoe UI',Tahoma,Arial,sans-serif;direction:rtl;}
</style>
</head>
<body style="margin:0;padding:20px;background:#e9eef6;font-family:'Segoe UI',Tahoma,Arial,sans-serif;direction:rtl;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.05);">
  <tr>
    <td style="background:linear-gradient(145deg,#1e8449 0%,#27ae60 100%);padding:30px 20px;text-align:center;color:#fff;">
      <div style="font-size:40px;margin-bottom:10px;">✅</div>
      <h1 style="margin:0;font-size:22px;">تم اعتماد طلبك بنجاح!</h1>
      <p style="margin:10px 0 0;font-size:14px;opacity:0.9;">مرحلة الاعتماد: <strong>{$levelName}</strong></p>
    </td>
  </tr>
  <tr>
    <td style="padding:20px;">
      <p style="margin:0 0 15px;font-size:15px;color:#333;">مرحباً <strong>{$requesterName}</strong>،</p>
      <p style="margin:0 0 20px;font-size:14px;color:#555;line-height:1.6;">
        نود إعلامك بأنه قد تمت الموافقة على طلب الصرف رقم <strong style="color:#2353a4;">{$requestNumber}</strong> الخاص بأمر العمل <strong style="color:#2353a4;">{$workOrderNum}</strong>.
      </p>
      
      <div style="background:#f8fafc;padding:15px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:20px;">
        <h3 style="margin:0 0 10px;font-size:14px;color:#1a336b;">تفاصيل الطلب:</h3>
        <ul style="margin:0;padding:0;list-style:none;">
          <li style="margin-bottom:8px;font-size:13px;color:#555;"><strong>الرقم:</strong> {$requestNumber}</li>
          <li style="font-size:13px;color:#555;"><strong>الحالة الحالية:</strong> {$status}</li>
        </ul>
      </div>
      
      <p style="margin:0;font-size:13px;color:#888;text-align:center;">
        هذه رسالة آلية من نظام تِقان، يرجى عدم الرد عليها.
      </p>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
  }

  /**
   * بناء محتوى HTML للبريد - تصميم متجاوب للهاتف والكمبيوتر
   */
  private function buildEmailHtml(array $request, array $details, bool $isResubmission = false): string
  {
    $requestNumber = htmlspecialchars($request['request_number'] ?? 'N/A');
    $requestDate = isset($request['request_date']) ? date('d/m/Y', strtotime($request['request_date'])) : date('d/m/Y');
    $createdAt = isset($request['created_at']) ? date('d/m/Y H:i', strtotime($request['created_at'])) : date('d/m/Y H:i');
    $notes = htmlspecialchars($request['notes'] ?? '');
    $status = $this->getStatusLabel($request['status'] ?? 'submitted');
    $totalItems = count($details);
    $totalQty = number_format(array_sum(array_column($details, 'requested_quantity')), 2);

    // ===== بيانات أمر العمل ومقدم الطلب =====
    $workOrderNum = htmlspecialchars($request['work_order_number'] ?? '-');
    $workOrderType = htmlspecialchars($request['work_order_type_description'] ?? '');
    $requesterName = htmlspecialchars($request['requested_by_name'] ?? '-');
    $branchName = htmlspecialchars($request['branch_name'] ?? '-');
    $requiredDate = !empty($request['required_date']) ? date('d/m/Y', strtotime($request['required_date'])) : '-';

    // ===== عنوان البريد حسب نوع الإرسال =====
    $emailTitle = $isResubmission ? 'تعديل وإعادة إرسال طلب صرف' : 'طلب صرف مواد جديد';
    $emailSubtitle = $isResubmission ? 'تم تعديل طلب الصرف وإعادة إرساله للمراجعة' : 'تم استلام طلب صرف جديد ويستوجب مراجعتك';


    // ===== بطاقات المواد =====
    $materialCards = '';
    foreach ($details as $i => $d) {
      $num = $i + 1;
      $itemNum = htmlspecialchars($d['item_number'] ?? '-');
      $desc = htmlspecialchars($d['description'] ?? '-');
      $qty = number_format((float) ($d['requested_quantity'] ?? 0), 2);
      $unit = htmlspecialchars($d['unit'] ?? '-');
      $stock = number_format((float) ($d['current_stock'] ?? 0), 2);
      $purpose = htmlspecialchars($d['purpose'] ?? '');
      $isLow = (float) ($d['current_stock'] ?? 0) < (float) ($d['requested_quantity'] ?? 0);
      $stockColor = $isLow ? '#c0392b' : '#27ae60';
      $stockBg = $isLow ? '#fff0ee' : '#edfaf4';
      $stockBorder = $isLow ? '#e74c3c' : '#2ecc71';
      $stockIcon = $isLow ? '&#9888;' : '&#10003;';
      $purposeRow = (!empty(trim($purpose)))
        ? "<tr><td style='padding:5px 0;font-size:12px;color:#999;width:44%;'>الغرض</td><td style='padding:5px 0;font-size:13px;color:#555;'>{$purpose}</td></tr>"
        : '';

      $materialCards .= "
            <tr>
              <td style='padding:0 14px 12px;'>
                <table width='100%' cellpadding='0' cellspacing='0'
                       style='background:#fff;border:1px solid #dae3f3;border-radius:12px;overflow:hidden;'>
                  <!-- Card header -->
                  <tr>
                    <td style='background:linear-gradient(135deg,#2353a4,#3a7bd5);padding:10px 14px;'>
                      <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                          <td style='color:#fff;font-weight:800;font-size:13px;'>
                            <span style='background:rgba(255,255,255,0.22);border-radius:50%;display:inline-block;
                                         width:20px;height:20px;line-height:20px;text-align:center;
                                         margin-left:6px;font-size:11px;'>{$num}</span>{$itemNum}
                          </td>
                          <td align='left'>
                            <span style='background:rgba(255,255,255,0.18);color:#fff;font-size:11px;
                                         padding:2px 10px;border-radius:10px;'>{$unit}</span>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <!-- Description -->
                  <tr>
                    <td style='padding:12px 14px 8px;font-size:14px;color:#1a336b;font-weight:700;
                                line-height:1.55;border-bottom:1px solid #edf1f9;'>{$desc}</td>
                  </tr>
                  <!-- Data -->
                  <tr>
                    <td style='padding:10px 14px 12px;'>
                      <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                          <td style='padding:5px 0;font-size:12px;color:#999;width:44%;'>الكمية المطلوبة</td>
                          <td style='padding:5px 0;'>
                            <span style='font-size:20px;font-weight:900;color:#2353a4;'>{$qty}</span>
                            <span style='font-size:12px;color:#aaa;margin-right:3px;'>{$unit}</span>
                          </td>
                        </tr>
                        <tr>
                          <td style='padding:5px 0;font-size:12px;color:#999;'>المخزون الحالي</td>
                          <td style='padding:5px 0;'>
                            <span style='display:inline-block;background:{$stockBg};color:{$stockColor};
                                         font-weight:700;font-size:13px;padding:3px 10px;border-radius:6px;
                                         border:1px solid {$stockBorder};'>
                              {$stockIcon} {$stock} {$unit}
                            </span>
                          </td>
                        </tr>
                        {$purposeRow}
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>";
    }

    $notesBlock = $this->buildNotesBlock($notes);

    return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<style>
body{margin:0;padding:0;background:#e9eef6;}
table{border-collapse:collapse;}
@media only screen and (max-width:600px){
  .ew{padding:8px 0!important;}
  .hd{padding:26px 16px!important;}
  .ht{font-size:19px!important;}
  .hs{font-size:12px!important;}
  .hb{font-size:11px!important;padding:5px 14px!important;}
  .bp{padding:14px 12px!important;}
  .ic{padding:11px 8px!important;}
  .il{font-size:9px!important;}
  .iv{font-size:11px!important;}
  .sp{padding:0 12px 8px!important;}
  .sb{padding:12px 14px!important;}
  .st{font-size:13px!important;}
  .btn{padding:13px 24px!important;font-size:13px!important;}
  .fp{padding:18px 14px!important;}
  .ft{font-size:11px!important;}
}
</style>
</head>
<body style="margin:0;padding:0;background:#e9eef6;font-family:'Segoe UI',Tahoma,Arial,sans-serif;direction:rtl;">

<table width="100%" class="ew" cellpadding="0" cellspacing="0" style="background:#e9eef6;padding:20px 0;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">

  <!-- HEADER -->
  <tr>
    <td class="hd" style="background:linear-gradient(145deg,#152f5e 0%,#2353a4 55%,#3a7bd5 100%);
                           border-radius:16px 16px 0 0;padding:34px 28px;text-align:center;">
      <div style="background:rgba(255,255,255,0.14);border-radius:50%;width:64px;height:64px;
                  line-height:64px;font-size:28px;margin:0 auto 14px;text-align:center;">&#128230;</div>
      <h1 class="ht" style="margin:0;color:#fff;font-size:22px;font-weight:800;">{$emailTitle}</h1>
      <p class="hs" style="margin:8px 0 0;color:rgba(255,255,255,0.8);font-size:13px;">
        {$emailSubtitle}
      </p>
      <div class="hb" style="display:inline-block;margin-top:14px;background:rgba(255,255,255,0.17);
                              border:1px solid rgba(255,255,255,0.38);border-radius:20px;
                              padding:6px 20px;color:#fff;font-size:13px;font-weight:700;">
        &#128278; {$requestNumber}
      </div>
    </td>
  </tr>

  <!-- INFO CARDS -->
  <tr>
    <td class="bp" style="background:#fff;padding:18px 20px 14px;">

      <!-- 3 cards row -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="ic" width="31%" valign="top"
              style="background:#f0f5ff;border:1px solid #c8d9f3;border-radius:10px;
                     padding:13px 10px;text-align:center;">
            <div style="font-size:18px;margin-bottom:5px;">&#128203;</div>
            <div class="il" style="font-size:10px;color:#7a8899;margin-bottom:3px;">رقم الطلب</div>
            <div class="iv" style="font-size:12px;font-weight:800;color:#1a336b;word-break:break-all;">{$requestNumber}</div>
          </td>
          <td width="3%"></td>
          <td class="ic" width="31%" valign="top"
              style="background:#f0fff5;border:1px solid #b2d9c5;border-radius:10px;
                     padding:13px 10px;text-align:center;">
            <div style="font-size:18px;margin-bottom:5px;">&#128197;</div>
            <div class="il" style="font-size:10px;color:#7a8899;margin-bottom:3px;">تاريخ الطلب</div>
            <div class="iv" style="font-size:12px;font-weight:800;color:#145a32;">{$requestDate}</div>
          </td>
          <td width="3%"></td>
          <td class="ic" width="31%" valign="top"
              style="background:#fffbee;border:1px solid #e8cc70;border-radius:10px;
                     padding:13px 10px;text-align:center;">
            <div style="font-size:18px;margin-bottom:5px;">&#128202;</div>
            <div class="il" style="font-size:10px;color:#7a8899;margin-bottom:3px;">الحالة</div>
            <div class="iv" style="font-size:11px;font-weight:800;color:#7d5a00;">{$status}</div>
          </td>
        </tr>
      </table>

      <!-- أمر العمل ومقدم الطلب -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
        <tr>
          <td colspan="3" style="background:linear-gradient(135deg,#f0f5ff,#e8f0ff);
                                  border:1px solid #c8d9f3;border-radius:10px;padding:14px 16px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <!-- أمر العمل -->
                <td width="50%" style="vertical-align:top;padding-left:10px;border-left:1px solid #d0dff5;">
                  <div style="font-size:10px;color:#7a8899;margin-bottom:4px;">&#128196; أمر العمل</div>
                  <div style="font-size:15px;font-weight:900;color:#1a336b;">{$workOrderNum}</div>
                  <div style="font-size:11px;color:#7a8899;margin-top:2px;">{$workOrderType}</div>
                </td>
                <!-- مقدم الطلب -->
                <td width="50%" style="vertical-align:top;padding-right:10px;">
                  <div style="font-size:10px;color:#7a8899;margin-bottom:4px;">&#128100; مقدم الطلب</div>
                  <div style="font-size:14px;font-weight:800;color:#1a336b;">{$requesterName}</div>
                  <div style="font-size:11px;color:#7a8899;margin-top:2px;">&#127970; {$branchName}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- 2 info strips -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
        <tr>
          <td width="48%" style="background:#f6f8fc;border-right:4px solid #2353a4;border-radius:6px;padding:10px 12px;">
            <div style="font-size:10px;color:#9aa5b4;">وقت الإنشاء</div>
            <div style="font-size:13px;font-weight:700;color:#2d3748;margin-top:2px;">&#128336; {$createdAt}</div>
          </td>
          <td width="4%"></td>
          <td width="48%" style="background:#f6f8fc;border-right:4px solid #27ae60;border-radius:6px;padding:10px 12px;">
            <div style="font-size:10px;color:#9aa5b4;">تاريخ الحاجة</div>
            <div style="font-size:13px;font-weight:700;color:#2d3748;margin-top:2px;">&#128197; {$requiredDate}</div>
          </td>
        </tr>
      </table>

      {$notesBlock}
    </td>

  </tr>

  <!-- SECTION TITLE -->
  <tr>
    <td class="bp" style="background:#fff;padding:4px 20px 0;">
      <div style="background:linear-gradient(90deg,#152f5e,#2c5aa0);border-radius:10px;padding:12px 16px;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td class="st" style="color:#fff;font-weight:800;font-size:14px;">
              &#128203; بيان المواد المطلوبة
            </td>
            <td align="left">
              <span style="background:rgba(255,255,255,0.18);color:#fff;font-size:11px;
                           padding:3px 11px;border-radius:10px;">{$totalItems} صنف</span>
            </td>
          </tr>
        </table>
      </div>
    </td>
  </tr>

  <!-- MATERIAL CARDS -->
  <tr>
    <td style="background:#edf1f9;padding:12px 0 4px;">
      <table width="100%" cellpadding="0" cellspacing="0" class="sp" style="padding:0 14px 8px;">
        {$materialCards}
      </table>
    </td>
  </tr>

  <!-- SUMMARY BAR -->
  <tr>
    <td class="sb" style="background:#dde8fc;border-top:2px solid #2353a4;padding:13px 20px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="st" style="font-size:13px;color:#152f5e;font-weight:800;">&#129518; إجمالي الكميات المطلوبة</td>
          <td align="left">
            <span style="font-size:22px;font-weight:900;color:#2353a4;">{$totalQty}</span>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ACTION BUTTON -->
  <tr>
    <td class="bp" style="background:#fff;padding:20px;text-align:center;">
      <a href="http://localhost/etganplus/public/inventory/material-requests/index.php"
         class="btn"
         style="display:inline-block;background:linear-gradient(135deg,#2353a4,#152f5e);
                color:#fff;text-decoration:none;padding:13px 34px;
                border-radius:30px;font-size:14px;font-weight:700;">
        &#8592; عرض الطلب في النظام
      </a>
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td class="fp" style="background:linear-gradient(135deg,#152f5e,#2353a4);
                           border-radius:0 0 16px 16px;padding:20px 28px;text-align:center;">
      <p class="ft" style="margin:0;color:rgba(255,255,255,0.88);font-size:12px;">
        هذا البريد أُرسل تلقائياً من <strong>نظام تِقان لإدارة المستودع</strong>
      </p>
      <p class="ft" style="margin:7px 0 0;color:rgba(255,255,255,0.55);font-size:11px;">
        يُرجى عدم الرد على هذا البريد &#8212; للاستفسار تواصل مع مسؤول النظام
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
  }

  /**
   * كتلة الملاحظات (تُظهر فقط إذا كانت موجودة)
   */
  private function buildNotesBlock(string $notes): string
  {
    if (empty(trim($notes))) {
      return '';
    }
    return "
      <div style='margin-top:12px;background:#fffbee;border:1px solid #f0c84a;
                  border-right:4px solid #e67e22;border-radius:8px;padding:12px 14px;'>
        <div style='font-size:11px;color:#9a6f00;font-weight:700;margin-bottom:4px;'>&#128221; ملاحظات الطلب</div>
        <div style='font-size:13px;color:#333;line-height:1.65;'>{$notes}</div>
      </div>";
  }

  /**
   * بناء نص عادي للبريد (بديل النص)
   */
  private function buildEmailText(array $request, array $details, bool $isResubmission = false): string
  {
    $headerText = $isResubmission ? "تعديل وإعادة إرسال طلب صرف" : "طلب صرف مواد جديد";
    $text = "$headerText\n";
    $text .= "==================\n\n";
    $text .= "رقم الطلب: " . ($request['request_number'] ?? 'N/A') . "\n";
    $text .= "التاريخ:   " . ($request['request_date'] ?? date('Y-m-d')) . "\n";
    $text .= "الحالة:    " . $this->getStatusLabel($request['status'] ?? '') . "\n";
    $text .= "الملاحظات: " . ($request['notes'] ?? '-') . "\n\n";
    $text .= "المواد المطلوبة:\n";
    $text .= "----------------\n";
    foreach ($details as $i => $d) {
      $text .= ($i + 1) . ". [{$d['item_number']}] {$d['description']} - الكمية: {$d['requested_quantity']} {$d['unit']}\n";
    }
    return $text;
  }

  /**
   * ترجمة الحالة إلى نص عربي
   */
  private function getStatusLabel(string $status): string
  {
    $labels = [
      'draft' => 'مسودة',
      'submitted' => 'مُرسل للمراجعة',
      'warehouse_approved' => 'معتمد من المستودع',
      'approved' => 'معتمد نهائياً',
      'rejected' => 'مرفوض',
      'cancelled' => 'ملغى',
    ];
    return $labels[$status] ?? $status;
  }

  /**
   * إرسال بريد إشعار بسلفة جديدة
   */
  public function sendLoanNotification(array $loan): bool
  {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
      error_log('[EmailService] vendor/autoload.php not found');
      return false;
    }
    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
      // ===== إعدادات SMTP =====
      $mail->isSMTP();
      $mail->Host = 'smtp.gmail.com';
      $mail->SMTPAuth = true;
      $mail->Username = $this->fromEmail;
      $mail->Password = $this->appPassword;
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port = 587;
      $mail->CharSet = 'UTF-8';
      $mail->SMTPDebug = 0;

      // ===== المرسل والمستقبل =====
      $mail->setFrom($this->fromEmail, $this->fromName);
      $mail->addAddress('musta.ishag@gmail.com', 'مدير المستودع');

      // ===== محتوى البريد =====
      $mail->isHTML(true);
      $typeName = $loan['type'] === 'borrow' ? 'استلاف' : 'تسليف';
      $mail->Subject = '📦 عملية سلفة جديدة (' . $typeName . '): ' . ($loan['loan_number'] ?? 'N/A');
      $mail->Body = $this->buildLoanEmailHtml($loan);

      // Text alternative
      $mail->AltBody = "سلفة جديدة\nرقم: {$loan['loan_number']}\nالمقاول: {$loan['client_name']}";

      $mail->send();
      error_log('[EmailService] تم إرسال إشعار السلفة: ' . ($loan['loan_number'] ?? ''));
      return true;

    } catch (Exception $e) {
      error_log('[EmailService] فشل إرسال بريد السلفة: ' . $mail->ErrorInfo);
      return false;
    }
  }

  private function buildLoanEmailHtml(array $loan): string
  {
    $loanNumber = htmlspecialchars($loan['loan_number'] ?? 'N/A');
    $createdAt = isset($loan['created_at']) ? date('d/m/Y H:i', strtotime($loan['created_at'])) : date('d/m/Y H:i');
    $clientName = htmlspecialchars($loan['client_name'] ?? '-');
    $receiver = htmlspecialchars($loan['receiver_name'] ?? '-');
    $notes = htmlspecialchars($loan['notes'] ?? '');
    $status = $loan['status'] === 'active' ? 'نشطة' : 'مخالصة';
    $typeName = $loan['type'] === 'borrow' ? 'استلاف (من مقاول)' : 'تسليف (إلى مقاول)';
    $typeColor = $loan['type'] === 'borrow' ? '#17a2b8' : '#ffc107';
    $totalItems = count($loan['items'] ?? []);

    $itemsHtml = '';
    if (isset($loan['items'])) {
      foreach ($loan['items'] as $i => $item) {
        $num = $i + 1;
        $itemNum = htmlspecialchars($item['item_number']);
        $desc = htmlspecialchars($item['description']);
        $qty = number_format($item['quantity'], 2);
        $itemsHtml .= "
                <tr>
                    <td style='padding:8px; border-bottom:1px solid #eee;'>{$num}</td>
                    <td style='padding:8px; border-bottom:1px solid #eee; font-weight:bold;'>{$itemNum}</td>
                    <td style='padding:8px; border-bottom:1px solid #eee;'>{$desc}</td>
                    <td style='padding:8px; border-bottom:1px solid #eee; text-align:center;'>{$qty}</td>
                </tr>";
      }
    }

    return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
</head>
<body style="margin:0;padding:20px;background:#f8f9fa;font-family:'Segoe UI',Tahoma,Arial,sans-serif;direction:rtl;">
    <div style="max-width:600px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden; border:1px solid #e9ecef;">
        <div style="background:#2353a4; color:#fff; padding:20px; text-align:center;">
            <h2 style="margin:0;">عملية سلفة جديدة</h2>
            <div style="margin-top:10px; font-size:18px; font-weight:bold;">{$loanNumber}</div>
        </div>
        <div style="padding:20px;">
            <table width="100%" cellpadding="5">
                <tr>
                    <td width="30%" style="color:#6c757d;">نوع السلفة:</td>
                    <td style="font-weight:bold; color:{$typeColor}">{$typeName}</td>
                </tr>
                <tr>
                    <td style="color:#6c757d;">المقاول/العميل:</td>
                    <td style="font-weight:bold;">{$clientName}</td>
                </tr>
                <tr>
                    <td style="color:#6c757d;">المستلم:</td>
                    <td style="font-weight:bold;">{$receiver}</td>
                </tr>
                <tr>
                    <td style="color:#6c757d;">الحالة:</td>
                    <td><span style="background:#007bff; color:#fff; padding:3px 10px; border-radius:15px; font-size:12px;">{$status}</span></td>
                </tr>
                <tr>
                    <td style="color:#6c757d;">تاريخ الإنشاء:</td>
                    <td>{$createdAt}</td>
                </tr>
            </table>

            <h3 style="margin-top:20px; border-bottom:2px solid #2353a4; padding-bottom:5px; color:#2353a4;">بنود السلفة ({$totalItems})</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px; text-align:right;">
                <tr style="background:#f1f3f5;">
                    <th style="padding:8px;">#</th>
                    <th style="padding:8px;">رقم البند</th>
                    <th style="padding:8px;">الوصف</th>
                    <th style="padding:8px; text-align:center;">الكمية</th>
                </tr>
                {$itemsHtml}
            </table>

            <div style="margin-top:25px; text-align:center;">
                <a href="http://localhost/etganplus/public/inventory/loans/view.php?id={$loan['id']}" style="background:#2353a4; color:#fff; text-decoration:none; padding:10px 20px; border-radius:5px; font-weight:bold;">عرض السلفة في النظام</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
  }
}
