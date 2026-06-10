// c:\Users\musta\scrap\utils\MRRPdfGenerator.js
window.MRRPdfGenerator = {
  _fontLoaded: false,
  _calibriLoaded: false,
  _fontData: null,

  _cols: {},
  _rowsY: {},
  _initCols() {
    this._cols = {};
    const width = 281;
    const rightMargin = 289; // 297 - 8
    const baseW = width / 16;

    // إمكانية التحكم بأوزان وعرض الأعمدة بشكل منفصل
    // تقليص E و F بمقدار 4 ملم، وزيادة G بمقدار 8 ملم لتعويض الفرق
    const customWidths = {
      'E': baseW - 4,
      'F': baseW - 4,
      'G': baseW + 8
    };

    // A to P from right to left
    const letters = 'ABCDEFGHIJKLMNOP'.split('');
    let currentX = rightMargin;

    letters.forEach((char) => {
      const colW = customWidths[char] || baseW;
      currentX -= colW;
      this._cols[char] = {
        x: currentX,
        w: colW
      };
    });
  },

  _rh(r) {
    if (r === 1 || r === 2) return 8; // زيادة ارتفاع الصف 1 و 2
    if (r === 32 || r === 33) return 3; // تقليل الارتفاع
    return 5.428; // الارتفاع الافتراضي لباقي الصفوف
  },
  _ry(r) {
    let y = 10;
    for (let i = 1; i < r; i++) y += this._rh(i);
    return y;
  },
  _sh(rf, rt) {
    let h = 0;
    for (let i = rf; i <= rt; i++) h += this._rh(i);
    return h;
  },

  _r(colsStr) {
    const arr = colsStr.split('');
    let xMin = 9999, xMax = -9999;
    arr.forEach(c => {
      if (!this._cols[c]) return;
      const x1 = this._cols[c].x;
      const x2 = x1 + this._cols[c].w;
      if (x1 < xMin) xMin = x1;
      if (x2 > xMax) xMax = x2;
    });
    return { x: xMin, w: xMax - xMin };
  },

  _box(d, x, y, w, h, bg = null) {
    if (bg) { d.setFillColor(bg[0], bg[1], bg[2]); d.rect(x, y, w, h, 'FD'); }
    else { d.rect(x, y, w, h, 'S'); }
  },

  _boxAt(d, colsStr, rFrom, rTo, bg = null) {
    const rc = this._r(colsStr);
    const y = this._ry(rFrom);
    const h = this._sh(rFrom, rTo);
    this._box(d, rc.x, y, rc.w, h, bg);
  },

  _t(d, colsStr, rFrom, txt, opts = {}) {
    const rc = this._r(colsStr);
    const yFrom = this._ry(rFrom);
    const sz = opts.sz || 9.5; // تكبير الخط الأساسي للعناوين
    const al = opts.al || 'center';
    const isAr = opts.ar || false;
    const weight = opts.weight || 'bold';

    // استخدام خط بولد للعناوين كما طلب المستخدم
    if (isAr) {
      d.setFont('Amiri', weight);
      d.setFontSize(sz);
    } else {
      d.setFont('helvetica', weight);
      d.setFontSize(sz);
    }

    let tx = rc.x + rc.w / 2;
    if (al === 'right') tx = rc.x + rc.w - 1;
    if (al === 'left') tx = rc.x + 1;

    let ty = yFrom + (this._rh(rFrom) / 2) + (sz * 0.3527 / 2) - 0.5;
    if (opts.yOffset) ty += opts.yOffset;

    d.setTextColor(0, 0, 0);
    if (opts.maxWidth) {
      d.text(txt, tx, ty, { align: al, maxWidth: opts.maxWidth });
    } else {
      d.text(txt, tx, ty, { align: al });
    }
  },

  async _loadFont(d) {
    try {
      if (!this._fontData) {
        this._fontData = [];
        const fontsToLoad = [
          ['https://cdn.jsdelivr.net/gh/google/fonts@main/ofl/amiri/Amiri-Regular.ttf', 'Amiri-Regular.ttf', 'Amiri', 'normal'],
          ['https://cdn.jsdelivr.net/gh/google/fonts@main/ofl/amiri/Amiri-Bold.ttf', 'Amiri-Bold.ttf', 'Amiri', 'bold'],
          ['https://cdn.jsdelivr.net/gh/googlefonts/carlito@main/fonts/ttf/Carlito-Regular.ttf', 'Carlito-Regular.ttf', 'Carlito', 'normal'],
          ['https://cdn.jsdelivr.net/gh/googlefonts/carlito@main/fonts/ttf/Carlito-Bold.ttf', 'Carlito-Bold.ttf', 'Carlito', 'bold'],
          ['https://cdn.jsdelivr.net/gh/googlefonts/carlito@main/fonts/ttf/Carlito-BoldItalic.ttf', 'Carlito-BoldItalic.ttf', 'Carlito', 'bolditalic']
        ];

        for (const [url, nm, fn, st] of fontsToLoad) {
          const buf = await (await fetch(url)).arrayBuffer();
          let b = ''; const u = new Uint8Array(buf);
          for (let i = 0; i < u.length; i++) b += String.fromCharCode(u[i]);
          this._fontData.push({ nm, b64: btoa(b), fn, st });
        }
      }

      for (const f of this._fontData) {
        d.addFileToVFS(f.nm, f.b64);
        d.addFont(f.nm, f.fn, f.st);
      }
      this._fontLoaded = true;
      this._calibriLoaded = true;
    } catch (e) {
      console.warn('Font loading error:', e);
    }
  },

  async generate(items, woNumber = '') {
    this._initCols();
    const JC = (window.jspdf || window.jsPDF).jsPDF || (window.jspdf || window.jsPDF);
    const d = new JC({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    await this._loadFont(d);

    // ============ Header ============
    try {
      const logoUrl = '/etganplus/public/assets/images/SEC.png';
      const logoImg = await new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
          const canvas = document.createElement('canvas');
          canvas.width = img.width; canvas.height = img.height;
          canvas.getContext('2d').drawImage(img, 0, 0);
          resolve(canvas.toDataURL('image/png'));
        };
        img.onerror = reject;
        img.src = logoUrl;
      });
      // جعل الشعار يسار النموذج بين الصفين 1 و 2
      const leftCol = this._r('P');
      const logoH = 12; // الارتفاع التقريبي
      const logoW = 35; // العرض التقريبي
      const headerSt = this._ry(1);
      const headerH = this._sh(1, 2);
      const logoY = headerSt + (headerH - logoH) / 2;

      d.addImage(logoImg, 'PNG', leftCol.x, logoY, logoW, logoH);
    } catch (e) {
      // Ignored
    }

    // إزالة البوردر (Box) عن الصفين 1 و 2
    this._t(d, 'ABCDEFGHIJKLMNOP', 1, "طلب ارجاع المواد", { ar: true, sz: 14, al: 'center' });
    this._t(d, 'ABCDEFGHIJKLMNOP', 2, "MATREIAL RETURN REQUEST", { sz: 12, al: 'center' });

    // ============ Info Section Rows 3-9 ============

    // Row 3-4 User Organization = m to p 3
    this._boxAt(d, 'MNOP', 3, 4);
    this._t(d, 'P', 3, "User Organiztion", { al: 'left', yOffset: -1 });
    this._t(d, 'M', 3, "الادارة المستفيدة", { ar: true, al: 'right', yOffset: -1 });

    // Row 3-4 User Control No = h to l 3
    this._boxAt(d, 'HIJKL', 3, 4);
    this._t(d, 'L', 3, "User Control No", { al: 'left', yOffset: -1 });
    this._t(d, 'H', 3, "رقم ضبط المستخدم", { ar: true, al: 'right', yOffset: -1 });

    // Row 3-4 Mailing Addres = e to g 3
    this._boxAt(d, 'EFG', 3, 4);
    this._t(d, 'G', 3, "Mailing Addres", { al: 'left', yOffset: -1 });
    this._t(d, 'E', 3, "عنوان المراسلة", { ar: true, al: 'right', yOffset: -1 });

    // Row 3-4 MMD USE ONLY = a to d 3
    this._boxAt(d, 'ABCD', 3, 4);
    this._t(d, 'D', 3, "MMD USE ONLY", { al: 'left', yOffset: -1 });
    this._t(d, 'A', 3, "لاستعمال ادارة المواد", { ar: true, al: 'right', yOffset: -1 });

    // Row 5-6 Charge account = m to p 5
    this._boxAt(d, 'MNOP', 5, 6);
    this._t(d, 'P', 5, "Charge account", { al: 'left' });
    this._t(d, 'M', 5, "رقم الحساب", { ar: true, al: 'right' });

    // Row 7-9 Prepared & Tel
    this._boxAt(d, 'OP', 7, 9); // Prepared = op7
    this._t(d, 'P', 7, "Prepared", { al: 'left' });
    this._t(d, 'O', 7, "أعدها", { ar: true, al: 'right' });
    this._boxAt(d, 'MN', 7, 9); // Tel = mn7
    this._t(d, 'N', 7, "Tel", { al: 'left' });
    this._t(d, 'M', 7, "رقم الهاتف", { ar: true, al: 'right' });

    // Rows 5-9: Materials at = kl5
    this._boxAt(d, 'KL', 5, 9);
    this._t(d, 'L', 5, "Materials at", { al: 'left' });
    this._t(d, 'K', 5, "المواد في", { ar: true, al: 'right' });

    // Rows 5-9 Checkboxes Block (Split vertically with two boxes)
    this._boxAt(d, 'HIJ', 5, 9);
    this._boxAt(d, 'EFG', 5, 9);
    const cbSz = 6.5;
    const drawCbCstm = (colText, colCb, r, txtEn) => {
      this._t(d, colText, r, txtEn, { al: 'left', sz: cbSz, weight: 'normal' });
      const rectR = this._r(colCb);
      d.rect(rectR.x + rectR.w - 4.5, this._ry(r) + 1, 3.5, 3.5);
    };
    drawCbCstm('IJ', 'H', 5, "Dirct charge Matrials");
    drawCbCstm('IJ', 'H', 6, "Used materials");
    drawCbCstm('IJ', 'H', 7, "Project Listed");
    drawCbCstm('IJ', 'H', 8, "Materials");

    drawCbCstm('FG', 'E', 5, "Removed Esential Material");
    drawCbCstm('FG', 'E', 6, "Excess project");
    drawCbCstm('FG', 'E', 7, "inventory materials");
    drawCbCstm('FG', 'E', 8, "Issued from inventory");
    drawCbCstm('FG', 'E', 9, "more than 60 days");

    // Row 10: Insured
    this._boxAt(d, 'EFGHIJ', 10, 10);
    this._t(d, 'IJ', 10, "Materials Insured / Paid through Project", { al: 'left', sz: 6.5, weight: 'normal' });
    d.rect(this._r('E').x + this._r('E').w - 4.5, this._ry(10) + 1, 3.5, 3.5); // Add checkbox block arbitrarily right

    // Rows 5-9 MMD USE ONLY right block
    this._boxAt(d, 'ABCD', 5, 5); // a to d 5
    this._t(d, 'D', 5, "CONTROL NO", { al: 'left' });
    this._t(d, 'A', 5, "رقم الضبط", { ar: true, al: 'right' });

    this._boxAt(d, 'ABCD', 6, 6); // a to d 6
    this._t(d, 'D', 6, "Returne to Inventory", { al: 'left' });
    this._t(d, 'A', 6, "تعاد الى المخزون", { ar: true, al: 'right' });

    this._boxAt(d, 'CD', 7, 10);
    this._t(d, 'CD', 8, "01", { sz: 12, al: 'center' });

    this._boxAt(d, 'AB', 7, 10);
    this._t(d, 'AB', 8, "02", { sz: 12, al: 'center' });

    // ============ Table Headers Rows 11-13 ============
    const drawTh3 = (col, ar11, mid12, en13) => {
      this._boxAt(d, col, 11, 13); // دمج الخلية كصندوق واحد عمودياً
      if (mid12) {
        const isAr = /[\u0600-\u06FF]/.test(mid12);
        this._t(d, col, 11, ar11, { ar: true, sz: 7.5, al: 'center', yOffset: 0.5 });
        this._t(d, col, 12, mid12, { ar: isAr, sz: 6.5, al: 'center', yOffset: 0 });
        this._t(d, col, 13, en13, { sz: 6.5, al: 'center', yOffset: -0.5 });
      } else {
        if (ar11) this._t(d, col, 11, ar11, { ar: true, sz: 8.5, al: 'center', yOffset: 2 });
        if (en13) this._t(d, col, 13, en13, { sz: 8, al: 'center', yOffset: -2 });
      }
    };

    drawTh3('P', "رقم البند", null, "Line No");
    drawTh3('LMNO', "الوصف ( أذكر رقم مواصفات المواد ورقم التصنيع)", null, "Desription (GIVE MFR.NO");
    drawTh3('K', "رقم التخزين", null, "SEC NO");
    drawTh3('J', "الوحدة", null, "unit");
    drawTh3('I', "الكمية", null, "Quantity");
    drawTh3('H', "الحالة", null, "Condition");

    // Future Usage
    drawTh3('G', "الاستخدام المستقبلي", "(نعم /لا)", "Future Usage (y/no)");

    // Materials Type (Merged E & F)
    drawTh3('EF', "نوع المادة", "Materials Type", "ZSTK/ZCAP");

    drawTh3('CD', "سعر الوحدة بالريال", null, "Unit Price(SR)");
    drawTh3('B', "وضع المادة", "Order", "Status");
    drawTh3('A', "ملاحظات", null, "Remarks");

    // ============ Table Data Rows 14-21 ============
    const startRow = 14;
    const maxRows = 8; // 14 to 21
    const hdrs = [
      { c: 'P' }, { c: 'LMNO' }, { c: 'K' }, { c: 'J' }, { c: 'I' },
      { c: 'H' }, { c: 'G' }, { c: 'EF' }, { c: 'CD' }, { c: 'B' }, { c: 'A' }
    ];

    for (let i = 0; i < maxRows; i++) {
      const rNum = startRow + i;
      hdrs.forEach(h => this._boxAt(d, h.c, rNum, rNum));

      const item = (items && items[i]) ? items[i] : null;
      if (item) {
        // Line No
        this._t(d, 'P', rNum, (i + 1).toString(), { al: 'center', weight: 'normal', sz: 8.5 });
        // Description (cut to prevent overflowing to SEC NO)
        const desc = String(item.description || '').substring(0, 60);
        if (item.description) this._t(d, 'LMNO', rNum, desc, { al: 'center', weight: 'normal', sz: 8.5 });
        // SEC NO
        if (item.item_number) this._t(d, 'K', rNum, String(item.item_number), { al: 'center', weight: 'normal', sz: 8.5 });
        // Unit
        if (item.unit) this._t(d, 'J', rNum, String(item.unit), { al: 'center', weight: 'normal', sz: 8.5 });
        // Quantity
        if (item.quantity) this._t(d, 'I', rNum, String(item.quantity), { al: 'center', weight: 'normal', sz: 8.5 });

        // Defaults for MRR
        this._t(d, 'H', rNum, "جيدة", { ar: true, al: 'center', weight: 'normal', sz: 8.5 });
        this._t(d, 'G', rNum, "نعم", { ar: true, al: 'center', weight: 'normal', sz: 8.5 });
        this._t(d, 'EF', rNum, "ZSTK", { al: 'center', weight: 'normal', sz: 8.5 });
      }
    }

    // ============ Signatures & Footers Rows 22-35 ============
    // Reason for request Row 22
    this._boxAt(d, 'GP', 22, 22); // g to p 22
    this._t(d, 'P', 22, "Reason for request", { al: 'left' });
    this._t(d, 'G', 22, "الغرض من الطلب", { ar: true, al: 'right' });

    // أمر العمل
    this._boxAt(d, 'GP', 23, 23);
    const woFullText = woNumber ? `امر العمل رقم - ${woNumber}` : "امر العمل رقم - ";
    this._t(d, 'GP', 23, woFullText, { ar: true, al: 'center', sz: 8.5 });

    // Row 24 Contractor
    this._boxAt(d, 'GP', 24, 24); // g to p 24
    this._t(d, 'GP', 24, "إسم المقاول :  شركة توت المصيف للمقاولات", { ar: true, al: 'center' });

    // Row 25-27 Titles & Values (Merged vertically without horizontal dividers)
    this._boxAt(d, 'MP', 25, 27);
    this._t(d, 'P', 25, "Approved by", { al: 'left', yOffset: -1 });
    this._t(d, 'M', 25, "وافق عليها", { ar: true, al: 'right', yOffset: -1 });
    this._t(d, 'MP', 26, "م / إبراهيم الأسمري", { ar: true, al: 'center', yOffset: 2.7 });

    this._boxAt(d, 'JL', 25, 27);
    this._t(d, 'L', 25, "Jop Title", { al: 'left', yOffset: -1 });
    this._t(d, 'J', 25, "الوظيفة", { ar: true, al: 'right', yOffset: -1 });
    this._t(d, 'JL', 26, "مدير دائرة الإنشاءات", { ar: true, al: 'center', sz: 7, yOffset: 2.7 });

    this._boxAt(d, 'GI', 25, 27);
    this._t(d, 'I', 25, "DATE", { al: 'left', yOffset: -1 });
    this._t(d, 'G', 25, "التاريخ", { ar: true, al: 'right', yOffset: -1 });

    // Right Side Signatures Blocks (A to F)
    // Row 22-23 headers
    this._boxAt(d, 'A', 22, 23);
    this._t(d, 'A', 22, "وافق عليها", { ar: true, al: 'center', sz: 7.5 });
    this._t(d, 'A', 23, "Approved by", { al: 'center', sz: 6.5 });

    this._boxAt(d, 'B', 22, 23);
    this._t(d, 'B', 22, "فحصها", { ar: true, al: 'center', sz: 7.5 });
    this._t(d, 'B', 23, "Inspected by", { al: 'center', sz: 6.5 });

    this._boxAt(d, 'CD', 22, 23);
    this._t(d, 'CD', 22, "تسلمها", { ar: true, al: 'center', sz: 7.5 });
    this._t(d, 'CD', 23, "Received by", { al: 'center', sz: 6.5 });

    // Left Side Name/ID/Sign/Date (E & F spanning rows 24-33 approx)
    this._boxAt(d, 'EF', 24, 25);
    this._boxAt(d, 'A', 24, 25); this._boxAt(d, 'B', 24, 25); this._boxAt(d, 'CD', 24, 25);
    this._t(d, 'F', 24, "Name", { al: 'left', sz: 8.5 });
    this._t(d, 'E', 24, "الاسم", { ar: true, al: 'right', sz: 8.5 });

    this._boxAt(d, 'EF', 26, 27);
    this._boxAt(d, 'A', 26, 27); this._boxAt(d, 'B', 26, 27); this._boxAt(d, 'CD', 26, 27);
    this._t(d, 'F', 26, "ID No", { al: 'left', sz: 8.5 });
    this._t(d, 'E', 26, "رقم الموظف", { ar: true, al: 'right', sz: 8.5 });

    this._boxAt(d, 'EF', 28, 29);
    this._boxAt(d, 'A', 28, 29); this._boxAt(d, 'B', 28, 29); this._boxAt(d, 'CD', 28, 29);
    this._t(d, 'F', 28, "SIGN", { al: 'left', sz: 8.5 });
    this._t(d, 'E', 28, "التوقيع", { ar: true, al: 'right', sz: 8.5 });

    this._boxAt(d, 'EF', 30, 35); // user said 30 to 33, let's span to end
    this._boxAt(d, 'A', 30, 35); this._boxAt(d, 'B', 30, 35); this._boxAt(d, 'CD', 30, 35);
    this._t(d, 'F', 30, "Date", { al: 'left', sz: 8.5 });
    this._t(d, 'E', 30, "التاريخ", { ar: true, al: 'right', sz: 8.5 });

    // Planning Div Use Only Rows 28
    this._boxAt(d, 'GP', 28, 28); // g to p 28
    this._t(d, 'P', 28, "Planing Div Use Only", { al: 'left' });
    this._t(d, 'G', 28, "لاستخدام دائرة تخطيط المخزون فقط", { ar: true, al: 'right' });

    // Table header for planning Row 29
    // DIV = o to p 29 (extrapolated from "o to p 2" typo)
    this._boxAt(d, 'OP', 29, 29);
    this._t(d, 'OP', 29, "DIV                     القسم", { ar: true, al: 'center', sz: 7 });

    // APPROVED BY = l to n 29
    this._boxAt(d, 'LN', 29, 29);
    this._t(d, 'LN', 29, "وافق عليها APPROVED BY", { ar: true, al: 'center', sz: 7 });

    // JOB TITLE = i to k 29
    this._boxAt(d, 'IK', 29, 29);
    this._t(d, 'IK', 29, "JOB TITLE الوظيفة", { ar: true, al: 'center', sz: 7 });

    // DATE = g to h 29
    this._boxAt(d, 'GH', 29, 29);
    this._t(d, 'GH', 29, "DATE التاريخ", { ar: true, al: 'center', sz: 7 });

    // Planning rows 30-35
    const drawPlanRow = (r1, r2, enArText, isGreen = false) => {
      const bg = isGreen ? [220, 240, 220] : null;
      this._boxAt(d, 'OP', r1, r2, bg);
      this._t(d, 'OP', Math.floor((r1 + r2) / 2), enArText, { ar: true, al: 'center', sz: 8 });
      this._boxAt(d, 'LN', r1, r2, bg);
      this._boxAt(d, 'IK', r1, r2, bg);
      this._boxAt(d, 'GH', r1, r2, bg);
    };

    // الفهرسة Catalogin == o to p 30 (spanning 30-31 usually)
    drawPlanRow(30, 31, "Catalogin       الفهرسة", true);
    // Planing التخطيط o to p 32 (spanning 32-35 usually)
    drawPlanRow(32, 35, "Planing            التخطيط", false);

    // ============ Save PDF ============
    d.save("MRR_Report.pdf");
  },

  async generateAndDownload(items, woNumber = '') {
    return this.generate(items, woNumber);
  }
};
