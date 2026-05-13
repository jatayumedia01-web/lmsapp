<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tax Invoice <?= htmlspecialchars((string)($inv['number'] ?? '')) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#1a1a1a;background:#fff;padding:0}
.page{max-width:800px;margin:0 auto;padding:32px;border:1px solid #e2e8f0}
@media print{
  .no-print{display:none!important}
  body{padding:0}
  .page{max-width:100%;border:none;padding:20px}
  @page{margin:1cm;size:A4}
}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;padding-bottom:20px;border-bottom:2px solid #7c3aed}
.company-left{flex:1}
.company-logo{height:48px;margin-bottom:8px;object-fit:contain}
.company-name{font-size:20px;font-weight:700;color:#1a1a1a}
.company-meta{color:#6b7280;font-size:11px;margin-top:4px;line-height:1.6}
.invoice-right{text-align:right}
.invoice-badge{background:#7c3aed;color:#fff;font-size:9px;font-weight:700;letter-spacing:.1em;padding:4px 10px;border-radius:4px;display:inline-block;margin-bottom:8px}
.invoice-number{font-size:18px;font-weight:700;color:#1a1a1a}
.invoice-date{color:#6b7280;font-size:11px;margin-top:4px}
.invoice-status{display:inline-block;margin-top:6px;padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700}
.status-paid{background:#dcfce7;color:#166534}
.status-pending{background:#fef9c3;color:#854d0e}
.status-failed{background:#fee2e2;color:#991b1b}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
.party-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px}
.party-label{font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7c3aed;margin-bottom:6px}
.party-name{font-weight:700;font-size:13px;margin-bottom:4px}
.party-meta{color:#6b7280;font-size:11px;line-height:1.6}
.gstin-badge{background:#ede9fe;color:#6d28d9;font-size:10px;font-weight:600;padding:2px 8px;border-radius:4px;display:inline-block;margin-top:4px}
table{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:11.5px}
thead tr{background:#7c3aed;color:#fff}
thead th{padding:9px 12px;text-align:left;font-weight:600;white-space:nowrap}
thead th:not(:first-child){text-align:right}
tbody tr:nth-child(even){background:#f8fafc}
tbody td{padding:10px 12px;border-bottom:1px solid #e2e8f0}
tbody td:not(:first-child){text-align:right}
.totals{display:flex;justify-content:flex-end;margin-bottom:20px}
.totals-box{width:320px}
.totals-row{display:flex;justify-content:space-between;padding:6px 0;font-size:12px;border-bottom:1px solid #f1f5f9}
.totals-row:last-child{border-bottom:none}
.totals-label{color:#6b7280}
.totals-value{font-weight:500}
.totals-grand{background:#7c3aed;color:#fff;border-radius:6px;padding:10px 12px;margin-top:8px;display:flex;justify-content:space-between;font-weight:700;font-size:14px}
.words-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 14px;margin-bottom:20px;font-size:11px;color:#374151}
.words-label{font-weight:700;color:#6b7280;text-transform:uppercase;font-size:9px;letter-spacing:.08em;margin-bottom:2px}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.info-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px 14px}
.info-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#7c3aed;margin-bottom:8px}
.info-row{display:flex;justify-content:space-between;font-size:11px;padding:3px 0;border-bottom:1px solid #f1f5f9}
.info-row:last-child{border-bottom:none}
.info-key{color:#6b7280}
.info-val{font-weight:500}
.terms{background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px 14px;margin-bottom:20px;font-size:10px;color:#6b7280;line-height:1.6}
.terms strong{display:block;color:#374151;margin-bottom:4px;font-size:10px;text-transform:uppercase;letter-spacing:.05em}
.footer{text-align:center;font-size:10px;color:#9ca3af;border-top:1px solid #e2e8f0;padding-top:16px}
.print-btn{position:fixed;top:16px;right:16px;background:#7c3aed;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;box-shadow:0 2px 8px rgba(124,58,237,.4)}
.gst-tag{font-size:9px;color:#9ca3af;display:block;margin-top:2px}
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()">⬇ Download / Print</button>
<div class="page">

<?php
$co   = $company ?? [];
$fy   = $fiscalYear ?? date('Y') . '-' . (date('Y') + 1 - 2000);
$amt  = (int) ($inv['amount_cents'] ?? 0);
$sub  = (int) ($inv['subtotal_cents'] ?? 0);
$gstP = (float) ($inv['gst_percent'] ?? 0);
$cgst = (int) ($inv['cgst_cents'] ?? 0);
$sgst = (int) ($inv['sgst_cents'] ?? 0);
$igst = (int) ($inv['igst_cents'] ?? 0);
$gstType = (string) ($inv['gst_type'] ?? 'CGST_SGST');
$currency = strtoupper((string) ($inv['currency'] ?? 'INR'));
$symbol   = $currency === 'INR' ? '₹' : '$';
$fmt = fn(int $cents) => $symbol . number_format($cents / 100, 2);

$statusClass = match(strtoupper((string)($inv['status'] ?? ''))) {
    'PAID'     => 'status-paid',
    'PENDING'  => 'status-pending',
    'FAILED','REFUNDED' => 'status-failed',
    default    => 'status-pending',
};

// Amount in words
function numberToWords(int $n): string {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
             'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
             'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    if ($n === 0) return 'Zero';
    $rupees  = intdiv($n, 100);
    $paise   = $n % 100;
    $convert = function(int $n) use ($ones, $tens, &$convert): string {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[intdiv($n,10)] . ($n%10 ? ' '.$ones[$n%10] : '');
        if ($n < 1000) return $ones[intdiv($n,100)].' Hundred'.($n%100?' '.$convert($n%100):'');
        if ($n < 100000) return $convert(intdiv($n,1000)).' Thousand'.($n%1000?' '.$convert($n%1000):'');
        if ($n < 10000000) return $convert(intdiv($n,100000)).' Lakh'.($n%100000?' '.$convert($n%100000):'');
        return $convert(intdiv($n,10000000)).' Crore'.($n%10000000?' '.$convert($n%10000000):'');
    };
    $words = $convert($rupees) . ' Rupees';
    if ($paise > 0) $words .= ' and ' . $convert($paise) . ' Paise';
    return $words . ' Only';
}
$amountInWords = numberToWords($amt);
?>

<!-- Header -->
<div class="header">
  <div class="company-left">
    <?php if (!empty($co['company_logo_url'])): ?>
    <img src="<?= htmlspecialchars((string)$co['company_logo_url']) ?>" class="company-logo" alt="logo">
    <?php endif; ?>
    <div class="company-name"><?= htmlspecialchars((string)($co['company_name'] ?? 'Devithor LMS')) ?></div>
    <div class="company-meta">
      <?php if (!empty($co['company_address_line1'])): ?>
      <?= htmlspecialchars((string)$co['company_address_line1']) ?><?= !empty($co['company_address_line2']) ? ', '.htmlspecialchars((string)$co['company_address_line2']) : '' ?><br>
      <?php endif; ?>
      <?php if (!empty($co['company_city'])): ?>
      <?= htmlspecialchars((string)$co['company_city']) ?><?= !empty($co['company_state']) ? ', '.htmlspecialchars((string)$co['company_state']) : '' ?><?= !empty($co['company_pincode']) ? ' - '.htmlspecialchars((string)$co['company_pincode']) : '' ?><br>
      <?php endif; ?>
      <?php if (!empty($co['company_email'])): ?>Email: <?= htmlspecialchars((string)$co['company_email']) ?><?php endif; ?>
      <?php if (!empty($co['company_phone'])): ?> | Ph: <?= htmlspecialchars((string)$co['company_phone']) ?><?php endif; ?><br>
      <?php if (!empty($co['company_website'])): ?><?= htmlspecialchars((string)$co['company_website']) ?><?php endif; ?>
    </div>
    <?php if (!empty($co['company_gstin'])): ?>
    <div class="gstin-badge">GSTIN: <?= htmlspecialchars((string)$co['company_gstin']) ?></div>
    <?php endif; ?>
    <?php if (!empty($co['company_pan'])): ?>
    <div style="font-size:10px;color:#6b7280;margin-top:3px">PAN: <?= htmlspecialchars((string)$co['company_pan']) ?><?php if(!empty($co['company_cin'])): ?> &nbsp;|&nbsp; CIN: <?= htmlspecialchars((string)$co['company_cin']) ?><?php endif; ?></div>
    <?php endif; ?>
  </div>
  <div class="invoice-right">
    <div class="invoice-badge">TAX INVOICE</div>
    <div class="invoice-number"><?= htmlspecialchars((string)($inv['number'] ?? '-')) ?></div>
    <div class="invoice-date">
      Date: <?= date('d M Y', intdiv((int)($inv['date_millis'] ?? time()*1000), 1000)) ?><br>
      Period: <?= !empty($inv['period_start_millis']) ? date('d M Y', intdiv((int)$inv['period_start_millis'],1000)) : '-' ?>
      – <?= !empty($inv['period_end_millis']) ? date('d M Y', intdiv((int)$inv['period_end_millis'],1000)) : '-' ?>
    </div>
    <span class="invoice-status <?= $statusClass ?>"><?= strtoupper((string)($inv['status'] ?? 'PAID')) ?></span>
  </div>
</div>

<!-- Bill To / Bill From -->
<div class="parties">
  <div class="party-box">
    <div class="party-label">Bill From</div>
    <div class="party-name"><?= htmlspecialchars((string)($co['company_name'] ?? 'Devithor LMS')) ?></div>
    <div class="party-meta">
      <?php if (!empty($co['company_address_line1'])): ?>
      <?= htmlspecialchars((string)$co['company_address_line1']) ?><?= !empty($co['company_address_line2']) ? ', '.htmlspecialchars((string)$co['company_address_line2']) : '' ?><br>
      <?= htmlspecialchars((string)($co['company_city'] ?? '')) ?><?= !empty($co['company_state']) ? ', '.htmlspecialchars((string)$co['company_state']) : '' ?><?php endif; ?><br>
      <?php if (!empty($co['company_state_code'])): ?>Place of Supply: <?= htmlspecialchars((string)$co['company_state']) ?> (<?= htmlspecialchars((string)$co['company_state_code']) ?>)<?php endif; ?>
    </div>
    <?php if (!empty($co['company_gstin'])): ?>
    <div class="gstin-badge">GSTIN: <?= htmlspecialchars((string)$co['company_gstin']) ?></div>
    <?php endif; ?>
  </div>
  <div class="party-box">
    <div class="party-label">Bill To</div>
    <div class="party-name"><?= htmlspecialchars((string)($inv['customer_name'] ?? $inv['user_name'] ?? 'Customer')) ?></div>
    <div class="party-meta">
      <?php if (!empty($inv['customer_email'])): ?><?= htmlspecialchars((string)$inv['customer_email']) ?><br><?php endif; ?>
      <?php if (!empty($inv['customer_address'])): ?><?= htmlspecialchars((string)$inv['customer_address']) ?><br><?php endif; ?>
      <?php if (!empty($inv['place_of_supply'])): ?>Place of Supply: <?= htmlspecialchars((string)$inv['place_of_supply']) ?><?php endif; ?>
    </div>
    <?php if (!empty($inv['customer_gstin'])): ?>
    <div class="gstin-badge">GSTIN: <?= htmlspecialchars((string)$inv['customer_gstin']) ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- Line Items Table -->
<table>
  <thead>
    <tr>
      <th style="width:36px">#</th>
      <th>Description</th>
      <th>SAC Code</th>
      <th>Period</th>
      <th>Qty</th>
      <th>Rate</th>
      <?php if ($gstType === 'CGST_SGST'): ?>
      <th>CGST (<?= $gstP/2 ?>%)</th>
      <th>SGST (<?= $gstP/2 ?>%)</th>
      <?php elseif ($gstType === 'IGST'): ?>
      <th>IGST (<?= $gstP ?>%)</th>
      <?php endif; ?>
      <th>Amount</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>1</td>
      <td>
        <strong><?= htmlspecialchars((string)($inv['plan_name'] ?? 'Subscription Plan')) ?></strong>
        <span class="gst-tag"><?= htmlspecialchars((string)($inv['billing_cycle_label'] ?? 'Monthly')) ?> Subscription – Digital Service</span>
      </td>
      <td><?= htmlspecialchars((string)($inv['sac_code'] ?? '998314')) ?></td>
      <td style="white-space:nowrap">
        <?= !empty($inv['period_start_millis']) ? date('d M Y', intdiv((int)$inv['period_start_millis'],1000)) : '-' ?><br>
        <?= !empty($inv['period_end_millis']) ? date('d M Y', intdiv((int)$inv['period_end_millis'],1000)) : '-' ?>
      </td>
      <td>1</td>
      <td><?= $fmt($sub ?: $amt) ?></td>
      <?php if ($gstType === 'CGST_SGST'): ?>
      <td><?= $fmt($cgst) ?></td>
      <td><?= $fmt($sgst) ?></td>
      <?php elseif ($gstType === 'IGST'): ?>
      <td><?= $fmt($igst) ?></td>
      <?php endif; ?>
      <td><strong><?= $fmt($amt) ?></strong></td>
    </tr>
  </tbody>
</table>

<!-- Amount in words -->
<div class="words-box">
  <div class="words-label">Amount in Words</div>
  <?= htmlspecialchars($amountInWords) ?>
</div>

<!-- Totals -->
<div class="totals">
  <div class="totals-box">
    <?php if ($sub > 0 && $sub !== $amt): ?>
    <div class="totals-row">
      <span class="totals-label">Taxable Amount</span>
      <span class="totals-value"><?= $fmt($sub) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($gstType === 'CGST_SGST' && $cgst > 0): ?>
    <div class="totals-row">
      <span class="totals-label">CGST @ <?= $gstP/2 ?>%</span>
      <span class="totals-value"><?= $fmt($cgst) ?></span>
    </div>
    <div class="totals-row">
      <span class="totals-label">SGST @ <?= $gstP/2 ?>%</span>
      <span class="totals-value"><?= $fmt($sgst) ?></span>
    </div>
    <?php elseif ($gstType === 'IGST' && $igst > 0): ?>
    <div class="totals-row">
      <span class="totals-label">IGST @ <?= $gstP ?>%</span>
      <span class="totals-value"><?= $fmt($igst) ?></span>
    </div>
    <?php elseif ($gstType === 'EXEMPT'): ?>
    <div class="totals-row">
      <span class="totals-label">GST</span>
      <span class="totals-value">Exempt</span>
    </div>
    <?php endif; ?>
    <div class="totals-grand">
      <span>Total (<?= $currency ?>)</span>
      <span><?= $fmt($amt) ?></span>
    </div>
  </div>
</div>

<!-- Bank + GST summary -->
<div class="two-col">
  <?php if (!empty($co['bank_name']) || !empty($co['bank_account_number'])): ?>
  <div class="info-box">
    <div class="info-label">Bank Details</div>
    <?php if (!empty($co['bank_name'])): ?>
    <div class="info-row"><span class="info-key">Bank</span><span class="info-val"><?= htmlspecialchars((string)$co['bank_name']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($co['bank_account_name'])): ?>
    <div class="info-row"><span class="info-key">Account Name</span><span class="info-val"><?= htmlspecialchars((string)$co['bank_account_name']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($co['bank_account_number'])): ?>
    <div class="info-row"><span class="info-key">Account No.</span><span class="info-val"><?= htmlspecialchars((string)$co['bank_account_number']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($co['bank_ifsc'])): ?>
    <div class="info-row"><span class="info-key">IFSC</span><span class="info-val"><?= htmlspecialchars((string)$co['bank_ifsc']) ?></span></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="info-box">
    <div class="info-label">Tax Summary</div>
    <div class="info-row"><span class="info-key">Taxable Value</span><span class="info-val"><?= $fmt($sub ?: $amt) ?></span></div>
    <?php if ($gstType === 'CGST_SGST'): ?>
    <div class="info-row"><span class="info-key">CGST</span><span class="info-val"><?= $fmt($cgst) ?></span></div>
    <div class="info-row"><span class="info-key">SGST</span><span class="info-val"><?= $fmt($sgst) ?></span></div>
    <?php elseif ($gstType === 'IGST'): ?>
    <div class="info-row"><span class="info-key">IGST</span><span class="info-val"><?= $fmt($igst) ?></span></div>
    <?php endif; ?>
    <div class="info-row"><span class="info-key">Total Tax</span><span class="info-val"><?= $fmt($cgst + $sgst + $igst) ?></span></div>
    <div class="info-row"><span class="info-key">Grand Total</span><span class="info-val"><strong><?= $fmt($amt) ?></strong></span></div>
  </div>
</div>

<!-- Terms -->
<div class="terms">
  <strong>Terms & Conditions</strong>
  <?= nl2br(htmlspecialchars((string)($co['invoice_terms'] ?? 'Payment is due upon receipt. This is a computer-generated invoice.'))) ?>
</div>

<!-- Footer -->
<div class="footer">
  This is a computer-generated Tax Invoice and does not require a physical signature.
  Generated by <?= htmlspecialchars((string)($co['company_name'] ?? 'Devithor LMS')) ?> on <?= date('d M Y, h:i A') ?>.
</div>

</div><!-- .page -->
</body>
</html>
