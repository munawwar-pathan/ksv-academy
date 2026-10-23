<?php
if (!isset($school_settings)) {
    $school_settings = function_exists('get_school_settings') ? get_school_settings() : [];
}
if (!isset($active_session)) {
    $active_session = function_exists('get_active_session') ? get_active_session() : null;
}
?>
<script src="<?php echo HRMS_URL; ?>/assets/js/html2canvas.min.js"></script>

<style>
/* ── Academic & Coaching Institute Fee Receipt Styles (Exact replica of admin/fees.php) ── */
.pay-receipt {
  padding: 0;
  background: #ffffff;
  color: #0f172a;
  font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
  width: 100%;
  max-width: 440px;
  margin: 0 auto;
  border-radius: 12px;
  overflow: hidden;
  border: 1.5px solid #cbd5e1;
  box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
}
.pay-receipt .ri-top-accent {
  height: 6px;
  background: linear-gradient(90deg, #0f172a 0%, #0092df 50%, #0284c7 100%);
}
.pay-receipt .receipt-body {
  padding: 14px 18px 12px;
}
.pay-receipt .ri-brand-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding-bottom: 12px;
  border-bottom: 2px solid #e2e8f0;
}
.pay-receipt .ri-logo-box {
  width: 54px;
  height: 54px;
  min-width: 54px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  padding: 3px;
  background: #ffffff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.pay-receipt .ri-logo-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 6px;
}
.pay-receipt .ri-brand-info {
  flex: 1;
}
.pay-receipt .ri-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: #0f172a;
  font-family: 'Outfit', sans-serif;
  letter-spacing: -0.01em;
  line-height: 1.2;
}
.pay-receipt .ri-sub {
  font-size: 10px;
  font-weight: 700;
  color: #0284c7;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-top: 2px;
}
.pay-receipt .ri-address {
  font-size: 9.5px;
  color: #64748b;
  margin-top: 2px;
  line-height: 1.3;
}

/* Document Title Bar */
.pay-receipt .receipt-doc-band {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 5px 10px;
  margin: 8px 0 6px;
}
.pay-receipt .receipt-doc-title {
  font-size: 11px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  font-family: 'Outfit', sans-serif;
}
.pay-receipt .receipt-doc-session {
  font-size: 10px;
  font-weight: 700;
  color: #0284c7;
  background: #e0f2fe;
  padding: 2px 7px;
  border-radius: 5px;
}

/* Meta Grid */
.pay-receipt .receipt-meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 6px 10px;
  margin-bottom: 8px;
}
.pay-receipt .rmg-cell {
  display: flex;
  flex-direction: column;
}
.pay-receipt .rmg-label {
  font-size: 9px;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 700;
  letter-spacing: 0.04em;
}
.pay-receipt .rmg-val {
  font-size: 11.5px;
  color: #0f172a;
  font-weight: 600;
  margin-top: 1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pay-receipt .rmg-val.bold {
  font-weight: 800;
  font-family: 'Outfit', sans-serif;
}
.pay-receipt .rmg-val.mono-badge {
  font-family: monospace;
  font-weight: 800;
  color: #0284c7;
}

/* Particulars Table */
.pay-receipt .receipt-table-wrapper {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 10px;
}
.pay-receipt .receipt-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 11px;
}
.pay-receipt .receipt-table th {
  background: #f1f5f9;
  color: #334155;
  font-weight: 800;
  font-size: 9.5px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 6px 10px;
  border-bottom: 1px solid #cbd5e1;
}
.pay-receipt .receipt-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.pay-receipt .receipt-table tr.total-row td {
  background: #f8fafc;
  border-top: 1.5px solid #cbd5e1;
  border-bottom: none;
  font-weight: 800;
  padding: 8px 10px;
}

/* Words Box */
.pay-receipt .receipt-words-box {
  background: #f0fdf4;
  border: 1px dashed #86efac;
  border-radius: 8px;
  padding: 7px 10px;
  margin-bottom: 12px;
  font-size: 10.5px;
}
.pay-receipt .receipt-words-box .words-label {
  font-weight: 800;
  color: #166534;
  text-transform: uppercase;
  font-size: 9px;
  letter-spacing: 0.04em;
  display: block;
  margin-bottom: 2px;
}
.pay-receipt .receipt-words-box .words-val {
  font-weight: 700;
  color: #14532d;
  font-style: italic;
}

/* Signatures & Seal Section */
.pay-receipt .receipt-auth-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin: 10px 0 8px;
  padding: 0 4px;
}
.pay-receipt .auth-sign-box {
  text-align: center;
  width: 115px;
}
.pay-receipt .auth-sign-line {
  border-bottom: 1.5px dashed #94a3b8;
  height: 24px;
  margin-bottom: 4px;
  font-size: 10.5px;
  font-weight: 700;
  color: #0f172a;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.pay-receipt .auth-sign-label {
  font-size: 9px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Academy Stamp Badge */
.pay-receipt .auth-seal-badge {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  border: 2px dashed #0284c7;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 4px;
  background: rgba(2, 132, 199, 0.03);
  transform: rotate(-6deg);
  margin: 0 auto;
}
.pay-receipt .seal-text-top {
  font-size: 7px;
  font-weight: 900;
  color: #0284c7;
  letter-spacing: 0.05em;
  line-height: 1;
}
.pay-receipt .seal-text-mid {
  font-size: 7.5px;
  font-weight: 900;
  color: #059669;
  letter-spacing: 0.08em;
  margin: 2px 0;
  line-height: 1;
}
.pay-receipt .seal-text-bot {
  font-size: 6.5px;
  font-weight: 800;
  color: #0284c7;
  line-height: 1;
}

/* Bottom QR & Terms */
.pay-receipt .receipt-bottom-bar {
  display: flex;
  gap: 10px;
  align-items: center;
  border-top: 1px solid #e2e8f0;
  padding-top: 8px;
  margin-top: 8px;
}
.pay-receipt .rb-qr-wrap {
  width: 62px;
  min-width: 62px;
  height: 62px;
  padding: 3px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
}
.pay-receipt .rb-qr-wrap img {
  width: 100%;
  height: 100%;
  display: block;
}
.pay-receipt .rb-notes {
  flex: 1;
  font-size: 8.5px;
  color: #64748b;
  line-height: 1.35;
}
.pay-receipt .rb-notes strong {
  color: #334155;
}

/* ── Receipt Modal Custom Visible Scrollbar & Layout ── */
#receiptPrintModal {
  overflow-y: auto !important;
}
#receiptPrintModal .receipt-modal-card {
  max-width: 530px !important;
  width: 95% !important;
  max-height: 90vh !important;
  display: flex !important;
  flex-direction: column !important;
  border-radius: 20px !important;
  overflow: hidden !important;
  box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.4) !important;
  margin: auto !important;
}
#receiptModalBody {
  padding: 0 !important;
  background: #f8fafc !important;
  display: block !important;
  overflow-y: scroll !important;
  overflow-x: hidden !important;
  flex: 1 1 auto !important;
  min-height: 220px !important;
  max-height: calc(90vh - 135px) !important;
  scroll-behavior: smooth !important;
  -webkit-overflow-scrolling: touch !important;
  position: relative !important;
  scrollbar-width: thin !important;
  scrollbar-color: #0284c7 #e2e8f0 !important;
}
#receiptModalBody::-webkit-scrollbar {
  width: 9px !important;
  display: block !important;
}
#receiptModalBody::-webkit-scrollbar-track {
  background: #f1f5f9 !important;
  border-left: 1px solid #e2e8f0 !important;
}
#receiptModalBody::-webkit-scrollbar-thumb {
  background: #0284c7 !important;
  border-radius: 10px !important;
  border: 2px solid #f1f5f9 !important;
}
#receiptModalBody::-webkit-scrollbar-thumb:hover {
  background: #0369a1 !important;
}
</style>

<!-- ══════════════════════════════════════════════════════
     MODAL: OFFICIAL TUITION FEE RECEIPT VOUCHER (Exact replica of admin/fees.php)
     ══════════════════════════════════════════════════════ -->
<div class="hrms-modal" id="receiptPrintModal">
  <div class="hrms-modal-card receipt-modal-card">
    
    <!-- Modal Header -->
    <div style="background: #ffffff; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(0, 146, 223, 0.1); color: var(--hrms-primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
          <i class="fa-solid fa-receipt"></i>
        </div>
        <div>
          <h3 style="margin: 0; font-size: 1rem; font-weight: 800; color: var(--hrms-navy); font-family: 'Outfit', sans-serif;">
            Official Fee Payment Receipt
          </h3>
          <p id="receiptModalSub" style="margin: 2px 0 0; font-size: 0.76rem; color: #64748b;">
            KSV Academy Fee Acknowledgement Voucher
          </p>
        </div>
      </div>

      <button type="button" class="modal-close-btn" onclick="closeReceiptModal();" aria-label="Close modal" style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Modal Body (Receipt display container with visible scrollbar) -->
    <div class="hrms-modal-body" id="receiptModalBody">
      <div id="receipt_print_area" style="display: flex; justify-content: center; width: 100%; padding: 14px 10px 20px;">
        <!-- Built dynamically via buildPaymentReceipt(payment) -->
      </div>
    </div>

    <!-- Hidden container used for off-screen clean html2canvas rendering -->
    <div id="receiptRenderContainer" style="position: fixed; left: -9999px; top: 0; width: 480px;"></div>

    <!-- Modal Footer Action Buttons (Print, Download, Share) -->
    <div class="no-print" style="padding: 12px 18px; background: #ffffff; border-top: 1px solid #e2e8f0; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
      
      <!-- Print Button -->
      <button type="button" onclick="printReceipt();" class="btn-hrms btn-hrms-outline btn-hrms-sm" style="font-weight: 700; gap: 6px; justify-content: center; padding: 9px 12px; font-size: 0.82rem; border-color: #cbd5e1;" title="Print on printer or save as PDF">
        <i class="fa-solid fa-print"></i>
        <span>Print</span>
      </button>

      <!-- Download Button -->
      <button type="button" id="btn_download_receipt" onclick="downloadReceiptPNG();" class="roaster-add-btn" style="justify-content: center; padding: 9px 12px; font-size: 0.82rem; gap: 6px; box-shadow: 0 4px 12px rgba(0, 146, 223, 0.25);" title="Download as high-resolution PNG image">
        <i class="fa-solid fa-file-arrow-down"></i>
        <span>Download</span>
      </button>

      <!-- Share Button -->
      <button type="button" id="btn_share_receipt" onclick="shareReceipt();" class="btn-hrms btn-hrms-sm" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; border: none; font-weight: 700; gap: 6px; justify-content: center; padding: 9px 12px; font-size: 0.82rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);" title="Share via WhatsApp or Device Share">
        <i class="fa-solid fa-share-nodes"></i>
        <span>Share</span>
      </button>

    </div>

  </div>
</div>

<script>
let currentActivePayment = null;

// ── Number to Indian Currency Words (e.g. 10000 -> Ten Thousand Rupees Only) ──
function numberToIndianWords(num) {
  const a = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
  const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

  num = Math.floor(parseFloat(num) || 0);
  if (num === 0) return '(Zero Rupees Only)';

  function convertSection(n) {
    if (n < 20) return a[n];
    const digit = n % 10;
    return b[Math.floor(n / 10)] + (digit ? ' ' + a[digit] : '');
  }

  let words = '';
  const crore = Math.floor(num / 10000000);
  num %= 10000000;
  const lakh = Math.floor(num / 100000);
  num %= 100000;
  const thousand = Math.floor(num / 1000);
  num %= 1000;
  const hundred = Math.floor(num / 100);
  const remaining = num % 100;

  if (crore > 0) words += convertSection(crore) + ' Crore ';
  if (lakh > 0) words += convertSection(lakh) + ' Lakh ';
  if (thousand > 0) words += convertSection(thousand) + ' Thousand ';
  if (hundred > 0) words += convertSection(hundred) + ' Hundred ';
  if (remaining > 0) {
    if (words !== '') words += 'and ';
    words += convertSection(remaining) + ' ';
  }

  return '(' + words.trim() + ' Rupees Only)';
}

// ── Build Official Receipt Card ──
function buildPaymentReceipt(record) {
  const paymentsList = (record.all_payments && Array.isArray(record.all_payments) && record.all_payments.length > 0)
    ? record.all_payments
    : [record];

  let totalReceived = 0;
  paymentsList.forEach(p => {
    totalReceived += parseFloat(p.amount) || 0;
  });

  const totalCourseFee = parseFloat(record.total_fees || 0);
  const remainingDue = Math.max(0, totalCourseFee - totalReceived);
  const receiptNo = record.receipt_no || ('RCPT-' + String(record.id).padStart(5, '0'));

  let payDateFmt = '--';
  if (record.payment_date) {
    const d = new Date(record.payment_date + 'T12:00:00');
    payDateFmt = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
  }

  const totalReceivedFmt = '₹' + totalReceived.toLocaleString('en-IN', { minimumFractionDigits: 2 }) + '/-';
  const amountWords = numberToIndianWords(totalReceived);

  const esc = (str) => {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  const qrData = "VERIFIED-INSTITUTE-FEE-RECEIPT:" + receiptNo + "|STUDENT:" + (record.student_name || '') + "|TOTAL_AMT:" + totalReceived + "|PAYMENTS:" + paymentsList.length;

  const schoolName = <?php echo json_encode($school_settings['school_name'] ?? 'KSV Academy'); ?>;
  const schoolAddress = <?php echo json_encode(($school_settings['address'] ?? 'KSV Campus, Near City Center') . ' • Contact: ' . ($school_settings['phone'] ?? '+91 98765 43210')); ?>;
  const sessionName = <?php echo json_encode($active_session['session_name'] ?? date('Y')); ?>;
  const schoolLogo = <?php echo json_encode($school_settings['logo_url'] ?? '/assets/imgs/ksv-logo.jpg'); ?>;

  return `
    <div class="pay-receipt" id="renderedReceiptCard">
      <div class="ri-top-accent"></div>
      <div class="receipt-body">
        
        <!-- Academy Institutional Header -->
        <div class="ri-brand-row">
          <div class="ri-logo-box">
            <img src="${schoolLogo}" alt="Logo" onerror="this.src='/assets/imgs/ksv-logo.jpg'">
          </div>
          <div class="ri-brand-info">
            <h2 class="ri-title">${esc(schoolName)}</h2>
            <div class="ri-sub">Coaching Institute &amp; Academic Center</div>
            <div class="ri-address">${esc(schoolAddress)}</div>
          </div>
        </div>

        <!-- Document Type & Session Banner -->
        <div class="receipt-doc-band">
          <span class="receipt-doc-title"><i class="fa-solid fa-receipt" style="color: #0284c7; margin-right: 5px;"></i> FEE PAYMENT RECEIPT</span>
          <span class="receipt-doc-session">Session: ${esc(sessionName)}</span>
        </div>

        <!-- Student & Voucher Meta Grid -->
        <div class="receipt-meta-grid">
          <div class="rmg-cell">
            <span class="rmg-label">Receipt No</span>
            <span class="rmg-val mono-badge">${esc(receiptNo)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Payment Date</span>
            <span class="rmg-val">${esc(payDateFmt)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Student Name</span>
            <span class="rmg-val bold">${esc(record.student_name)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Roll No / ID</span>
            <span class="rmg-val mono-badge" style="color: #0f172a;">${esc(record.roll_no)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">Class / Course</span>
            <span class="rmg-val">${esc(record.standard)}</span>
          </div>
          <div class="rmg-cell">
            <span class="rmg-label">${paymentsList.length > 1 ? 'Installments' : 'Payment Mode'}</span>
            <span class="rmg-val" style="color: #0284c7; font-weight: 700;">
              ${paymentsList.length > 1 ? `${paymentsList.length} Payments Recorded` : esc(record.payment_method || 'Cash')}
            </span>
          </div>
        </div>

        <!-- Particulars / Breakdown Table -->
        <div class="receipt-table-wrapper">
          <table class="receipt-table">
            <thead>
              <tr>
                <th style="width: 24px; text-align: center;">#</th>
                <th>Fee Head / Particulars</th>
                <th style="text-align: right; width: 105px;">Amount (₹)</th>
              </tr>
            </thead>
            <tbody>
              ${paymentsList.map((item, idx) => {
                const itemAmt = parseFloat(item.amount) || 0;
                let itemDate = '--';
                if (item.payment_date) {
                  const d = new Date(item.payment_date + 'T12:00:00');
                  itemDate = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
                }
                const isSelected = (item.id === record.id || item.receipt_no === record.receipt_no);
                const itemRemarks = item.remarks ? item.remarks.trim() : '';

                return `
                  <tr style="${isSelected && paymentsList.length > 1 ? 'background: #f0f9ff;' : ''}">
                    <td style="text-align: center; color: #64748b; font-weight: 700; font-size: 10px; vertical-align: top; padding-top: 7px;">
                      ${idx + 1}
                    </td>
                    <td style="padding: 6px 8px;">
                      <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        <span style="font-weight: 800; color: #0f172a; font-size: 10.5px;">
                          ${esc(item.fee_type || 'Tuition Fee')}
                          ${paymentsList.length > 1 ? `<span style="font-size: 8.5px; color: #0284c7; font-weight: 700; margin-left: 3px;">(Installment ${idx + 1})</span>` : ''}
                        </span>
                        <span style="font-size: 8.5px; background: #e0f2fe; color: #0369a1; padding: 1px 5px; border-radius: 4px; font-weight: 700; font-family: monospace;">
                          ${esc(item.receipt_no)}
                        </span>
                      </div>
                      <div style="font-size: 9px; color: #64748b; margin-top: 2px;">
                        <span><i class="fa-regular fa-calendar" style="font-size: 8px;"></i> ${esc(itemDate)}</span>
                        &bull; <span><i class="fa-solid fa-wallet" style="font-size: 8px;"></i> ${esc(item.payment_method || 'Cash')}</span>
                        ${isSelected && paymentsList.length > 1 ? '<span style="color: #0284c7; font-weight: 800; margin-left: 3px;">(Selected)</span>' : ''}
                      </div>
                      ${itemRemarks ? `<div style="font-size: 8.5px; color: #0284c7; margin-top: 2px; font-style: italic;">Note: ${esc(itemRemarks)}</div>` : ''}
                    </td>
                    <td style="text-align: right; font-weight: 800; color: #0f172a; font-family: 'Outfit', sans-serif; font-size: 11px; vertical-align: top; padding-top: 7px; white-space: nowrap;">
                      ₹${itemAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 })}/-
                    </td>
                  </tr>
                `;
              }).join('')}

              <tr class="total-row">
                <td colspan="2" style="text-align: right; font-weight: 800; font-size: 10.5px; color: #0f172a; text-transform: uppercase;">
                  Total Amount Received
                </td>
                <td style="text-align: right; font-size: 13px; font-weight: 900; color: #059669; font-family: 'Outfit', sans-serif;">
                  ${totalReceivedFmt}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Course Fee & Due Balance Strip -->
        ${totalCourseFee > 0 ? `
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 10px; font-size: 9.5px;">
            <div>
              <span style="color: #64748b; display: block; font-size: 8.5px; text-transform: uppercase; font-weight: 700;">Total Course Fee</span>
              <strong style="color: #0f172a; font-family: 'Outfit', sans-serif; font-size: 11px;">₹${totalCourseFee.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</strong>
            </div>
            <div style="text-align: right;">
              <span style="color: #64748b; display: block; font-size: 8.5px; text-transform: uppercase; font-weight: 700;">Outstanding Balance Due</span>
              <strong style="color: ${remainingDue > 0 ? '#dc2626' : '#059669'}; font-family: 'Outfit', sans-serif; font-size: 11px;">
                ${remainingDue > 0 ? `₹${remainingDue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}` : 'Fully Paid ✓'}
              </strong>
            </div>
          </div>
        ` : ''}

        <!-- Amount In Words Box -->
        <div class="receipt-words-box">
          <span class="words-label">Total Amount in Words:</span>
          <span class="words-val">${amountWords}</span>
        </div>

        <!-- Signatures & Official Institute Stamp -->
        <div class="receipt-auth-row">
          <div class="auth-sign-box">
            <div class="auth-sign-line">${esc(record.student_name)}</div>
            <div class="auth-sign-label">Student / Depositor</div>
          </div>

          <div class="auth-seal-badge">
            <div class="seal-text-top">KSV ACADEMY</div>
            <div class="seal-text-mid">★ VERIFIED ★</div>
            <div class="seal-text-bot">ACCOUNTS DEPT</div>
          </div>

          <div class="auth-sign-box">
            <div class="auth-sign-line" style="color: #0284c7;">Authorized Signatory</div>
            <div class="auth-sign-label">Accounts Officer</div>
          </div>
        </div>

        <!-- Security QR & Institutional Terms -->
        <div class="receipt-bottom-bar">
          <div class="rb-qr-wrap">
            <img id="dl_qr_image" src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=0&data=${encodeURIComponent(qrData)}" alt="QR Code">
          </div>
          <div class="rb-notes">
            <div><strong>Terms &amp; Instructions:</strong></div>
            <div>• Computer-generated fee acknowledgement voucher issued by KSV Academy.</div>
            <div>• Fees deposited are non-refundable and non-transferable under any condition.</div>
            <div>• Please preserve this receipt for academic clearance and hall ticket.</div>
          </div>
        </div>

      </div>
    </div>`;
}

// ── Open Official Receipt Modal ──
function openFeeReceiptModal(record) {
  if (!record) return;
  currentActivePayment = record;

  const printArea = document.getElementById('receipt_print_area');
  if (printArea) {
    printArea.innerHTML = buildPaymentReceipt(record);
  }

  const sub = document.getElementById('receiptModalSub');
  if (sub) {
    sub.innerText = (record.student_name || 'Student') + ' • ' + (record.receipt_no || 'Receipt');
  }

  const modal = document.getElementById('receiptPrintModal');
  if (modal) modal.classList.add('show');

  const body = document.getElementById('receiptModalBody');
  if (body) body.scrollTop = 0;
}

// ── Close Receipt Modal ──
function closeReceiptModal() {
  const modal = document.getElementById('receiptPrintModal');
  if (modal) modal.classList.remove('show');
}

// ── Print Action (Isolated Clean Print) ──
function printReceipt() {
  if (!currentActivePayment) return;
  const p = currentActivePayment;
  const printArea = document.getElementById('receipt_print_area');
  if (!printArea) return;

  const receiptContent = printArea.innerHTML;

  let printIframe = document.getElementById('ksvReceiptPrintFrame');
  if (!printIframe) {
    printIframe = document.createElement('iframe');
    printIframe.id = 'ksvReceiptPrintFrame';
    printIframe.style.position = 'fixed';
    printIframe.style.right = '0';
    printIframe.style.bottom = '0';
    printIframe.style.width = '0';
    printIframe.style.height = '0';
    printIframe.style.border = '0';
    document.body.appendChild(printIframe);
  }

  const externalLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
    .map(el => el.outerHTML)
    .join('\n');

  const payReceiptStyles = `
    * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    html, body {
      margin: 0;
      padding: 0;
      background: #ffffff !important;
      color: #0f172a;
      font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
    }
    body {
      display: flex;
      justify-content: center;
      padding: 8mm 0;
    }
    .pay-receipt {
      padding: 0;
      background: #ffffff !important;
      color: #0f172a;
      width: 100%;
      max-width: 480px;
      margin: 0 auto !important;
      border-radius: 12px;
      overflow: hidden;
      border: 1.5px solid #cbd5e1 !important;
      box-shadow: none !important;
      page-break-inside: avoid !important;
      break-inside: avoid !important;
    }
    .pay-receipt .ri-top-accent {
      height: 6px;
      background: linear-gradient(90deg, #0f172a 0%, #0092df 50%, #0284c7 100%);
    }
    .pay-receipt .receipt-body {
      padding: 16px 20px 14px;
    }
    .pay-receipt .ri-brand-row {
      display: flex;
      align-items: center;
      gap: 14px;
      padding-bottom: 12px;
      border-bottom: 2px solid #e2e8f0;
    }
    .pay-receipt .ri-logo-box {
      width: 54px;
      height: 54px;
      min-width: 54px;
      border-radius: 10px;
      border: 1.5px solid #e2e8f0;
      padding: 3px;
      background: #ffffff;
    }
    .pay-receipt .ri-logo-box img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      border-radius: 6px;
    }
    .pay-receipt .ri-brand-info { flex: 1; }
    .pay-receipt .ri-title {
      margin: 0;
      font-size: 16px;
      font-weight: 800;
      color: #0f172a;
      font-family: 'Outfit', sans-serif;
      line-height: 1.2;
    }
    .pay-receipt .ri-sub {
      font-size: 10px;
      font-weight: 700;
      color: #0284c7;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-top: 2px;
    }
    .pay-receipt .ri-address {
      font-size: 9.5px;
      color: #64748b;
      margin-top: 2px;
      line-height: 1.3;
    }
    .pay-receipt .receipt-doc-band {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 5px 10px;
      margin: 8px 0 6px;
    }
    .pay-receipt .receipt-doc-title {
      font-size: 11px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      font-family: 'Outfit', sans-serif;
    }
    .pay-receipt .receipt-doc-session {
      font-size: 10px;
      font-weight: 700;
      color: #0284c7;
      background: #e0f2fe;
      padding: 2px 7px;
      border-radius: 5px;
    }
    .pay-receipt .receipt-meta-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 5px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 6px 10px;
      margin-bottom: 8px;
    }
    .pay-receipt .rmg-cell { display: flex; flex-direction: column; }
    .pay-receipt .rmg-label {
      font-size: 8.5px;
      color: #64748b;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.03em;
    }
    .pay-receipt .rmg-val {
      font-size: 10.5px;
      color: #0f172a;
      font-weight: 600;
      margin-top: 1px;
    }
    .pay-receipt .rmg-val.bold {
      font-weight: 800;
      font-family: 'Outfit', sans-serif;
      font-size: 11.5px;
    }
    .pay-receipt .rmg-val.mono-badge {
      font-family: monospace;
      color: #0284c7;
      font-weight: 800;
      letter-spacing: 0.02em;
    }
    .pay-receipt .receipt-table-wrapper {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 8px;
    }
    .pay-receipt .receipt-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10.5px;
    }
    .pay-receipt .receipt-table th {
      background: #f1f5f9;
      color: #334155;
      font-weight: 800;
      text-transform: uppercase;
      font-size: 8.5px;
      padding: 6px 10px;
      letter-spacing: 0.04em;
    }
    .pay-receipt .receipt-table td {
      padding: 7px 10px;
      border-bottom: 1px solid #f1f5f9;
      color: #334155;
    }
    .pay-receipt .receipt-table tr.total-row td {
      background: #f8fafc;
      border-top: 1.5px solid #cbd5e1;
      border-bottom: none;
      padding: 8px 10px;
    }
    .pay-receipt .receipt-words-box {
      background: #f0fdf4;
      border: 1px dashed #86efac;
      border-radius: 6px;
      padding: 6px 10px;
      margin-bottom: 10px;
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .pay-receipt .receipt-words-box .words-label {
      font-size: 8px;
      font-weight: 800;
      color: #166534;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .pay-receipt .receipt-words-box .words-val {
      font-size: 9.5px;
      font-weight: 700;
      color: #15803d;
      font-style: italic;
    }
    .pay-receipt .receipt-auth-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin: 10px 0 8px;
      padding: 0 4px;
    }
    .pay-receipt .auth-sign-box { text-align: center; width: 115px; }
    .pay-receipt .auth-sign-line {
      border-bottom: 1.5px dashed #94a3b8;
      height: 24px;
      margin-bottom: 4px;
      font-size: 10.5px;
      font-weight: 700;
      color: #0f172a;
      display: flex;
      align-items: flex-end;
      justify-content: center;
    }
    .pay-receipt .auth-sign-label {
      font-size: 9px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .pay-receipt .auth-seal-badge {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      border: 2px dashed #0284c7;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 4px;
      background: rgba(2, 132, 199, 0.03);
      transform: rotate(-6deg);
      margin: 0 auto;
    }
    .pay-receipt .seal-text-top { font-size: 7px; font-weight: 900; color: #0284c7; letter-spacing: 0.05em; line-height: 1; }
    .pay-receipt .seal-text-mid { font-size: 7.5px; font-weight: 900; color: #059669; letter-spacing: 0.08em; margin: 2px 0; line-height: 1; }
    .pay-receipt .seal-text-bot { font-size: 6.5px; font-weight: 800; color: #0284c7; line-height: 1; }
    .pay-receipt .receipt-bottom-bar {
      display: flex;
      gap: 10px;
      align-items: center;
      border-top: 1px solid #e2e8f0;
      padding-top: 8px;
      margin-top: 8px;
    }
    .pay-receipt .rb-qr-wrap {
      width: 62px;
      min-width: 62px;
      height: 62px;
      padding: 3px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
    }
    .pay-receipt .rb-qr-wrap img { width: 100%; height: 100%; display: block; }
    .pay-receipt .rb-notes { flex: 1; font-size: 8.5px; color: #64748b; line-height: 1.35; }
    .pay-receipt .rb-notes strong { color: #334155; }
    .no-print { display: none !important; }
    @page { size: auto; margin: 8mm; }
  `;

  const doc = printIframe.contentWindow.document;
  doc.open();
  doc.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <title>Fee Receipt - ${p.student_name || 'KSV Academy'} - ${p.receipt_no || ''}</title>
      ${externalLinks}
      <style>
        ${payReceiptStyles}
      </style>
    </head>
    <body>
      ${receiptContent}
    </body>
    </html>
  `);
  doc.close();

  setTimeout(() => {
    try {
      printIframe.contentWindow.focus();
      printIframe.contentWindow.print();
    } catch (e) {
      console.warn('Iframe print fallback to window.print():', e);
      window.print();
    }
  }, 400);
}

// ── Download PNG Action (html2canvas) ──
function downloadReceiptPNG() {
  if (!currentActivePayment) return;
  const p = currentActivePayment;
  const btn = document.getElementById('btn_download_receipt');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Rendering...';

  const renderContainer = document.getElementById('receiptRenderContainer');
  renderContainer.innerHTML = buildPaymentReceipt(p);
  const cardEl = renderContainer.firstElementChild;
  const filename = `Fee_Receipt-${(p.student_name || 'Student').replace(/\s+/g, '_')}-${p.receipt_no}.png`;

  const imgEl = renderContainer.querySelector('#dl_qr_image');
  const runExport = () => {
    html2canvas(cardEl, {
      scale: 2,
      useCORS: true,
      backgroundColor: '#ffffff'
    }).then(canvas => {
      const link = document.createElement('a');
      link.download = filename;
      link.href = canvas.toDataURL('image/png');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      btn.disabled = false;
      btn.innerHTML = origHtml;
      renderContainer.innerHTML = '';
    }).catch(err => {
      console.error('html2canvas error:', err);
      alert('Unable to generate PNG receipt. You can use the Print button to save as PDF.');
      btn.disabled = false;
      btn.innerHTML = origHtml;
      renderContainer.innerHTML = '';
    });
  };

  if (imgEl && imgEl.src.startsWith('http')) {
    fetch(imgEl.src)
      .then(res => res.blob())
      .then(blob => {
        const reader = new FileReader();
        reader.onloadend = () => {
          imgEl.src = reader.result;
          runExport();
        };
        reader.readAsDataURL(blob);
      })
      .catch(() => runExport());
  } else {
    runExport();
  }
}

// ── Share Action (WhatsApp & Web Share) ──
function shareReceipt() {
  if (!currentActivePayment) return;
  const p = currentActivePayment;
  const btn = document.getElementById('btn_share_receipt');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sharing...';

  const paymentsList = (p.all_payments && p.all_payments.length > 0) ? p.all_payments : [p];
  const totalPaidSum = paymentsList.reduce((acc, curr) => acc + (parseFloat(curr.amount) || 0), 0);

  let shareText = `*KSV ACADEMY - OFFICIAL FEE PAYMENT RECEIPT*\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `• Student: ${p.student_name} (${p.roll_no || '-'})\n` +
    `• Class: ${p.standard || '-'}\n` +
    `• Payments Recorded: ${paymentsList.length}\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n`;

  paymentsList.forEach((pmt, idx) => {
    const pDate = pmt.payment_date ? new Date(pmt.payment_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
    shareText += `[${idx + 1}] ${pmt.fee_type || 'Tuition Fee'} (${pmt.receipt_no}): ₹${parseFloat(pmt.amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })} on ${pDate} [${pmt.payment_method}]\n`;
  });

  shareText += `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `• Total Amount Received: ₹${totalPaidSum.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n`;

  if (p.total_fees !== undefined && p.total_fees !== null && parseFloat(p.total_fees) > 0) {
    const balDue = Math.max(0, parseFloat(p.total_fees) - totalPaidSum);
    shareText += `• Total Course Fee: ₹${parseFloat(p.total_fees).toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n` +
      `• Outstanding Due: ${balDue <= 0 ? 'Fully Paid (Nil)' : '₹' + balDue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}\n`;
  }

  shareText += `• Status: Paid & Cleared\n` +
    `━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
    `Verified by KSV Academy Accounts Portal`;

  const renderContainer = document.getElementById('receiptRenderContainer');
  renderContainer.innerHTML = buildPaymentReceipt(p);
  const cardEl = renderContainer.firstElementChild;
  const filename = `Fee_Receipt-${(p.student_name || 'Student').replace(/\s+/g, '_')}-${p.receipt_no}.png`;

  html2canvas(cardEl, { scale: 2, useCORS: true, backgroundColor: '#ffffff' })
    .then(canvas => {
      canvas.toBlob(blob => {
        const file = new File([blob], filename, { type: 'image/png' });

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          navigator.share({
            title: `Fee Receipt - ${p.student_name}`,
            text: shareText,
            files: [file]
          }).then(() => {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            renderContainer.innerHTML = '';
          }).catch(err => {
            console.log('Share dismissed:', err);
            btn.disabled = false;
            btn.innerHTML = origHtml;
            renderContainer.innerHTML = '';
          });
        } else {
          try {
            const item = new ClipboardItem({ 'image/png': blob });
            navigator.clipboard.write([item]).then(() => {
              btn.disabled = false;
              btn.innerHTML = origHtml;
              renderContainer.innerHTML = '';
              if (confirm('Receipt image copied to clipboard!\n\nClick OK to open WhatsApp and paste/send.')) {
                window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
              }
            }).catch(() => {
              const link = document.createElement('a');
              link.download = filename;
              link.href = canvas.toDataURL('image/png');
              link.click();
              btn.disabled = false;
              btn.innerHTML = origHtml;
              renderContainer.innerHTML = '';
              window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
            });
          } catch (e) {
            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            link.click();
            btn.disabled = false;
            btn.innerHTML = origHtml;
            renderContainer.innerHTML = '';
            window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
          }
        }
      }, 'image/png');
    })
    .catch(err => {
      console.error(err);
      btn.disabled = false;
      btn.innerHTML = origHtml;
      renderContainer.innerHTML = '';
      window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
    });
}

// ── Close on Escape or Backdrop Click ──
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeReceiptModal();
  }
});

document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('receiptPrintModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeReceiptModal();
      }
    });
  }
});
</script>
