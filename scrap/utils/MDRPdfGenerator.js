/**
 * MDR PDF Generator - مطابق لخلايا Excel
 * العمود H مخفي، الأعمدة A-T (RTL: A يمين, T يسار)
 */
const MDRPdfGenerator = {
  _fontLoaded: false,
  _colPos: null,

  // تهيئة مواقع الأعمدة (H مخفي)
  _initCols() {
    if (this._colPos) return;
    const cw = 287 / 19;
    this._cw = cw;
    this._colPos = {};
    let x = 292;
    for (const c of 'ABCDEFGHIJKLMNOPQRST') {
      const w = c === 'H' ? 0 : cw;
      x -= w;
      this._colPos[c] = { x, w };
    }
  },

  // نطاق أعمدة: 'A', 'AB', 'EFG', 'PT' إلخ
  _r(spec) {
    this._initCols();
    const s = spec[0], e = spec.length > 1 ? spec[spec.length - 1] : s;
    const sp = this._colPos[s], ep = this._colPos[e];
    return { x: ep.x, w: (sp.x + sp.w) - ep.x };
  },

  // موقع y للصف
  _ry(row) {
    if (row <= 17) return 18 + (row - 6) * 5;
    if (row <= 30) return 78 + (row - 18) * 4.5;
    if (row <= 38) return 137.5 + (row - 31) * 4;
    if (row <= 42) return 169.5 + (row - 39) * 3.5;
    return 183 + (row - 43) * 3.5;
  },

  // ارتفاع الصف
  _rh(row) {
    if (row <= 17) return 5;
    if (row <= 30) return 4.5;
    if (row <= 38) return 4;
    return 3.5;
  },

  // ارتفاع من صف لصف
  _sh(from, to) {
    return this._ry(to) + this._rh(to) - this._ry(from);
  },

  // رسم مستطيل
  _box(d, x, y, w, h, fill) {
    if (fill) { d.setFillColor(...fill); d.rect(x, y, w, h, 'F'); }
    d.setDrawColor(0); d.setLineWidth(0.3); d.rect(x, y, w, h, 'S');
  },

  // رسم مستطيل بالأعمدة والصفوف
  _boxAt(d, colSpec, fromRow, toRow, fill) {
    const r = this._r(colSpec);
    this._box(d, r.x, this._ry(fromRow), r.w, this._sh(fromRow, toRow), fill);
  },

  // كتابة نص في خلية
  _t(d, col, row, txt, o = {}) {
    const { ar = false, sz = 6, st = 'bold', al = 'center', rows = 1, vt = false } = o;
    const r = this._r(col);
    const y = this._ry(row);
    const h = rows > 1 ? this._sh(row, row + rows - 1) : this._rh(row);
    d.setFontSize(sz); d.setTextColor(0, 0, 0);
    d.setFont(ar && this._fontLoaded ? 'Amiri' : (this._calibriLoaded ? 'Carlito' : 'helvetica'), st);
    const tx = al === 'right' ? r.x + r.w - 1.5 : al === 'left' ? r.x + 1.5 : r.x + r.w / 2;
    const ty = vt ? y + sz * 0.4 + 1 : y + h / 2 + sz * 0.12;
    d.text(txt, tx, ty, { align: al });
  },

  // تخزين الخطوط في الذاكرة لتجنب إعادة التحميل من الإنترنت
  _fontData: null,
  async _loadFont(d) {
    try {
      // 1. جلب البيانات من الإنترنت مرة واحدة فقط
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
      
      // 2. تسجيل الخطوط في كل ملف PDF جديد (مهم جداً لحل مشكلة الترميز عند التصدير المتكرر)
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

  // =============================================
  async generate(items, woNumber = '') {
    this._initCols();
    const JC = (window.jspdf || window.jsPDF).jsPDF || (window.jspdf || window.jsPDF);
    const d = new JC({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    await this._loadFont(d);

    // ============ العنوان ============
    // شعار SEC في يسار النموذج
    try {
      const logoUrl = (window.location.pathname.includes('/') ? '' : '') + 'SEC.png';
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
      d.addImage(logoImg, 'PNG', 5, 2, 30, 15);
    } catch (e) {
      // fallback: نص SE في حالة عدم تحميل الصورة
      d.setTextColor(0, 100, 180); d.setFontSize(22); d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'bolditalic');
      d.text("SE", 20, 9, { align: 'center' });
      if (this._fontLoaded) {
        d.setFont('Amiri', 'bold'); d.setFontSize(7);
        d.text("السعودية للطاقة", 20, 13, { align: 'center' });
      }
    }
    d.setTextColor(0, 0, 0);
    if (this._fontLoaded) {
      d.setFont('Amiri', 'bold'); d.setFontSize(13);
      d.text("طلب التخلص من المواد", 148.5, 9, { align: 'center' });
    }
    d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'bold'); d.setFontSize(10);
    d.text("Material Disposal Request", 148.5, 14.5, { align: 'center' });

    // ============ الصفوف 6-11: المعلومات ============
    // --- الإطارات ---
    this._boxAt(d, 'AD', 6, 8);
    this._boxAt(d, 'EG', 6, 8);
    this._boxAt(d, 'IJ', 6, 8);
    this._boxAt(d, 'KM', 6, 7);
    this._boxAt(d, 'KM', 8, 9);
    this._boxAt(d, 'KM', 10, 11);
    this._boxAt(d, 'AD', 9, 11);
    this._boxAt(d, 'EG', 9, 11);
    this._boxAt(d, 'IJ', 9, 11);
    this._boxAt(d, 'NT', 6, 11); // بدون خط فاصل

    // --- النصوص ---
    this._t(d, 'A', 6, "النشاط", { ar: true, sz: 8, al: 'right' });
    this._t(d, 'CD', 8, "Activity", { sz: 7.5, al: 'left' });
    this._t(d, 'E', 6, "القطاع", { ar: true, sz: 8, al: 'right' });
    this._t(d, 'G', 8, "Sector", { sz: 7.5, al: 'left' });
    this._t(d, 'I', 6, "رقم ضبط المستفيد", { ar: true, sz: 6.5, al: 'right' });
    this._t(d, 'J', 8, "User Control No.", { sz: 6.5, al: 'left' });
    this._t(d, 'KL', 6, "التاريخ", { ar: true, sz: 8, al: 'right' });
    this._t(d, 'M', 7, "Date", { sz: 7.5, al: 'left' });
    this._t(d, 'K', 8, "الهاتف", { ar: true, sz: 8, al: 'right' });
    this._t(d, 'M', 9, "Tel", { sz: 7.5, al: 'left' });
    this._t(d, 'K', 10, "الوظيفة", { ar: true, sz: 8, al: 'right' });
    this._t(d, 'M', 11, "Job Title", { sz: 7.5, al: 'left' });
    this._t(d, 'AB', 9, "الادارة المستفيدة", { ar: true, sz: 7.5, al: 'right' });
    this._t(d, 'D', 11, "User Department", { sz: 7.5, al: 'left' });
    this._t(d, 'EF', 9, "منطقة الأعمال", { ar: true, sz: 7.5, al: 'right' });
    this._t(d, 'EFG', 11, "Operation Area", { sz: 7.5, al: 'left' });
    this._t(d, 'IJ', 9, "رقم مركز المسئولية", { ar: true, sz: 6.5, al: 'right' });
    this._t(d, 'IJ', 11, "Charge Account No.", { sz: 6.5, al: 'left' });

    // N-T: إدارة المساندة (بدون فاصل)
    const nt = this._r('NT');
    if (this._fontLoaded) {
      d.setFont('Amiri', 'bold'); d.setFontSize(12); d.setTextColor(0, 0, 0);
      d.text("إستعمال إدارة مساندة المواد فقط", nt.x + nt.w / 2, this._ry(6) + 6, { align: 'center' });
    }
    d.setFont('helvetica', 'bold'); d.setFontSize(11);
    d.text("Use by Materials Support Management only", nt.x + nt.w / 2, this._ry(9) + 4, { align: 'center' });

    // ============ الصفوف 12-14: نوع الحساب + التسليم ============
    // A-J صف 12: نوع الحساب
    this._boxAt(d, 'AJ', 12, 12);
    this._t(d, 'AB', 12, "نوع الحساب", { ar: true, sz: 7.5, al: 'right' });
    this._t(d, 'IJ', 12, "Type of Account", { sz: 7, al: 'left' });

    // A-G صفوف 13-14: اصول + رقم استبعاد الأصول (خلية واحدة بدون فاصل)
    this._boxAt(d, 'AG', 13, 14);
    this._t(d, 'A', 13, "اصول", { ar: true, sz: 7.5, al: 'right' });
    this._t(d, 'FG', 14, "Assets", { sz: 7, al: 'left' });
    // Checkbox اصول
    const rCbAsset = this._r('CD');
    const cbY13 = this._ry(13) + (this._rh(13) - 3.5) / 2;
    d.setDrawColor(0); d.setLineWidth(0.3);
    d.rect(rCbAsset.x + rCbAsset.w / 2 - 1.75, cbY13, 3.5, 3.5, 'S');
    // رقم استبعاد الأصول
    this._t(d, 'A', 14, "رقم استبعاد الأصول", { ar: true, sz: 6.5, al: 'right' });
    this._t(d, 'FG', 13, "Fix Assets No", { sz: 7, al: 'left' });

    // I-J صفوف 13-14: مصاريف
    this._boxAt(d, 'IJ', 13, 14);
    this._t(d, 'I', 13, "مصاريف", { ar: true, sz: 7.5, al: 'right' });
    this._t(d, 'J', 14, "Expenses", { sz: 7, al: 'left' });
    // Checkbox مصاريف
    const rCbExp = this._r('IJ');
    d.rect(rCbExp.x + rCbExp.w / 2 - 1.75, cbY13, 3.5, 3.5, 'S');

    // K-M صفوف 12-14: سلمها للمستودع
    this._boxAt(d, 'KM', 12, 14);
    this._t(d, 'K', 12, "سلمها للمستودع المعني", { ar: true, sz: 6, al: 'right' });
    this._t(d, 'LM', 14, "Delivered to Warehouse", { sz: 6.5, al: 'left' });

    // N-O صفوف 12-14: التاريخ
    this._boxAt(d, 'NO', 12, 14);
    this._t(d, 'NO', 12, "التاريخ", { ar: true, sz: 7.5, al: 'right' });
    this._t(d, 'O', 14, "Date", { sz: 7, al: 'left' });

    // P-T صفوف 12-14: رقم ضبط / Control No
    this._boxAt(d, 'PT', 12, 14);
    const pt = this._r('PT');
    if (this._fontLoaded) {
      d.setFont('Amiri', 'bold'); d.setFontSize(6); d.setTextColor(0, 0, 0);
      d.text("رقم ضبط قسم/وحدة التخلص من المواد", pt.x + pt.w - 1.5, this._ry(12) + 3, { align: 'right' });
    }
    d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'bold'); d.setFontSize(7);
    d.text("Control No", pt.x + 1.5, this._ry(14) + 3, { align: 'left' });

    // ============ الصفوف 15-17: رؤوس الجدول ============
    const thBg = [220, 230, 241];
    const hdrs = [
      { c: 'A', ar: "تسلسل", en: "No" },
      { c: 'BG', ar: "وصــــف المادة", en: "Description of material" },
      { c: 'I', ar: "الوحدة", en: "UNIT" },
      { c: 'J', ar: "الكمية", en: "Quantity" },
      { c: 'KL', ar: "سبب التخلص", en: "Reason for disposal" },
      { c: 'M', ar: "رقم التخزين", en: "Sec No" },
      { c: 'NO', ar: "حالة المادة", en: "Condition" },
      { c: 'PT', ar: "ملاحظات", en: "Remarks" },
    ];
    hdrs.forEach(h => {
      this._boxAt(d, h.c, 15, 17, thBg);
      if (this._fontLoaded) {
        d.setFont('Amiri', 'bold'); d.setFontSize(7.5); d.setTextColor(0, 0, 0);
        const r = this._r(h.c);
        d.text(h.ar, r.x + r.w / 2, this._ry(15) + this._sh(15, 16) / 2 + 1, { align: 'center' });
      }
      d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'bold'); d.setFontSize(7);
      const r2 = this._r(h.c);
      d.text(h.en, r2.x + r2.w / 2, this._ry(17) + 3, { align: 'center' });
    });

    // ============ الصفوف 18-30: البيانات (13 صف) ============
    for (let i = 0; i < 13; i++) {
      const row = 18 + i;
      const item = items && items[i] ? items[i] : {};
      const has = !!item.description;
      const y = this._ry(row);
      const h = this._rh(row);

      hdrs.forEach((hdr, ci) => {
        const r = this._r(hdr.c);
        this._box(d, r.x, y, r.w, h);
      });

      // No
      d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'bold'); d.setFontSize(8);
      const rNo = this._r('A');
      d.text((i + 1).toString(), rNo.x + rNo.w / 2, y + h / 2 + 1, { align: 'center' });

      // Description
      if (item.description) {
        d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'normal'); d.setFontSize(7.5);
        const rD = this._r('BG');
        d.text(item.description, rD.x + 1.5, y + h / 2 + 1, { align: 'left' });
      }
      // Unit
      if (item.unit) {
        d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'normal'); d.setFontSize(8);
        const rU = this._r('I');
        d.text(item.unit, rU.x + rU.w / 2, y + h / 2 + 1, { align: 'center' });
      }
      // Qty
      if (item.quantity) {
        d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'normal'); d.setFontSize(8);
        const rQ = this._r('J');
        d.text(String(item.quantity), rQ.x + rQ.w / 2, y + h / 2 + 1, { align: 'center' });
      }
      // Reason
      if (has && this._fontLoaded) {
        d.setFont('Amiri', 'bold'); d.setFontSize(7);
        const rR = this._r('KL');
        const reason = item.disposal_reason || 'معطوب ومستهلك';
        d.text(reason, rR.x + rR.w / 2, y + h / 2 + 1, { align: 'center' });
      }
      // Sec No
      if (item.item_number) {
        d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'normal'); d.setFontSize(7.5);
        const rS = this._r('M');
        d.text(String(item.item_number), rS.x + rS.w / 2, y + h / 2 + 1, { align: 'center' });
      }
      // Condition
      if (has && item.material_condition) {
        const isArCond = /[\u0600-\u06FF]/.test(item.material_condition);
        if (isArCond && this._fontLoaded) {
          d.setFont('Amiri', 'bold'); d.setFontSize(7);
        } else {
          d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'normal'); d.setFontSize(7);
        }
        const rC = this._r('NO');
        d.text(item.material_condition, rC.x + rC.w / 2, y + h / 2 + 1, { align: 'center' });
      }
      // Remarks
      if (item.remarks) {
        const isArabic = /[\u0600-\u06FF]/.test(item.remarks);
        if (isArabic && this._fontLoaded) {
          d.setFont('Amiri', 'bold'); d.setFontSize(6);
        } else {
          d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'normal'); d.setFontSize(6.5);
        }
        const rRm = this._r('PT');
        d.text(item.remarks, rRm.x + rRm.w / 2, y + h / 2 + 1, { align: 'center', maxWidth: rRm.w - 2 });
      }
    }

    // ============ الصفوف 31+: التوقيعات ============
    // صف 31: العناوين
    this._boxAt(d, 'AF', 31, 31);
    this._t(d, 'A', 31, "اقرار", { ar: true, sz: 7, al: 'right' });
    this._t(d, 'F', 31, "Acknowledgment", { sz: 6.5, al: 'left' });

    this._boxAt(d, 'GM', 31, 31);
    this._t(d, 'GI', 31, "ملاحظات", { ar: true, sz: 7, al: 'right' });
    this._t(d, 'M', 31, "Remarks", { sz: 6.5, al: 'left' });

    this._boxAt(d, 'PQ', 31, 32);
    this._t(d, 'PQ', 31, "تسلمها", { ar: true, sz: 7, al: 'center' });
    this._t(d, 'PQ', 32, "Received by", { sz: 6.5, al: 'center' });

    this._boxAt(d, 'RS', 31, 32);
    this._t(d, 'RS', 31, "اعتمدها", { ar: true, sz: 7, al: 'center' });
    this._t(d, 'RS', 32, "Approved", { sz: 6.5, al: 'center' });

    this._boxAt(d, 'T', 31, 32);
    this._t(d, 'T', 31, "التاريخ", { ar: true, sz: 7, al: 'center' });
    this._t(d, 'T', 32, "Date", { sz: 6.5, al: 'center' });

    // N-O صفوف 33-38: الاسم/رقم الموظف/التوقيع
    this._boxAt(d, 'NO', 33, 34);
    this._t(d, 'NO', 33, "الاسم", { ar: true, sz: 7, al: 'right' });
    this._t(d, 'O', 34, "Name", { sz: 6.5, al: 'left' });

    this._boxAt(d, 'NO', 35, 36);
    this._t(d, 'NO', 35, "رقم الموظف", { ar: true, sz: 6.5, al: 'right' });
    this._t(d, 'O', 36, "ID No.", { sz: 6.5, al: 'left' });

    this._boxAt(d, 'NO', 37, 38);
    this._t(d, 'NO', 37, "التوقيع", { ar: true, sz: 7, al: 'right' });
    this._t(d, 'O', 38, "Sign.", { sz: 6.5, al: 'left' });

    // P-Q, R-S, T: خلايا توقيع فارغة
    for (let j = 0; j < 3; j++) {
      const fr = 33 + j * 2, tr = 34 + j * 2;
      this._boxAt(d, 'PQ', fr, tr);
      this._boxAt(d, 'RS', fr, tr);
      this._boxAt(d, 'T', fr, tr);
    }

    // A-F صفوف 32-36: نص الإقرار (خلية واحدة بخلفية صفراء بدون فاصل)
    const yellowBg = [255, 255, 0];
    this._boxAt(d, 'AF', 32, 36, yellowBg);
    if (this._fontLoaded) {
      d.setFont('Amiri', 'bold'); d.setFontSize(7); d.setTextColor(0, 0, 0);
      const rAF = this._r('AF');
      const lineH = 4;
      const startY = this._ry(32) + 4;
      d.text("جميع المواد اعلاه تمت معاينتها والموافقة على", rAF.x + rAF.w - 1.5, startY, { align: 'right' });
      d.text("التخلص منها بالبيع .", rAF.x + rAF.w - 1.5, startY + lineH, { align: 'right' });
    }
    d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'bold'); d.setFontSize(6.5);
    const rAF2 = this._r('AF');
    const lineH2 = 4;
    const startY2 = this._ry(32) + 4;
    d.text("All the materials described", rAF2.x + 1.5, startY2, { align: 'left' });
    d.text("above checked and approved", rAF2.x + 1.5, startY2 + lineH2, { align: 'left' });
    d.text("to be disposed.", rAF2.x + 1.5, startY2 + lineH2 * 2, { align: 'left' });

    // G-M صفوف 32-35: شركة توت المصيف
    this._boxAt(d, 'GM', 32, 35);
    if (this._fontLoaded) {
      d.setFont('Amiri', 'bold'); d.setFontSize(10); d.setTextColor(0, 0, 0);
      const rGM = this._r('GM');
      d.text("شركة توت المصيف للمقاولات", rGM.x + rGM.w / 2, this._ry(32) + this._sh(32, 35) / 2 + 1, { align: 'center' });
    }

    // G-M صفوف 36-38: WO Number
    this._boxAt(d, 'GM', 36, 38);
    if (woNumber) {
      d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'bold'); d.setFontSize(10);
      const rGM2 = this._r('GM');
      d.text(woNumber, rGM2.x + rGM2.w / 2, this._ry(36) + this._sh(36, 38) / 2 + 1, { align: 'center' });
    }

    // ============ الصفوف 39-41: مدير القسم ============
    if (this._fontLoaded) {
      d.setFont('Amiri', 'bold'); d.setFontSize(7.5); d.setTextColor(0, 0, 0);
      d.text("مدير إدارة كهرباء الطائف", this._r('A').x + this._r('A').w - 1, this._ry(39) + 2.5, { align: 'right' });
      d.text("م / محمد بن سعد الشلوي", this._r('C').x + this._r('C').w - 1, this._ry(40) + 2.5, { align: 'right' });
    }
    d.setFont(this._calibriLoaded ? 'Carlito' : 'helvetica', 'bold'); d.setFontSize(7);
    d.text("Department Manager Singiture", this._r('F').x + 1, this._ry(41) + 2.5, { align: 'left' });

    // ============ الصفوف 43-45: ملاحظات ============
    if (this._fontLoaded) {
      const notesX = this._r('B').x + this._r('B').w - 1;
      d.setFont('Amiri', 'bold'); d.setFontSize(5.5); d.setTextColor(0, 0, 0);
      d.text("للتخلص من وحدات التوليد يجب مراجعة مخزون قطع الغيار التابعة لهذه الواحدات وايضاح الحاجة للتخلص من قطع غيارها في حالة الاحتفاظ بها",
        notesX, this._ry(43) + 2, { align: 'right', maxWidth: 260 });
      d.text("تعبئة النموذج بشكل واضح وكتابة الوصف كامل للمواد المسلمة للمستودع",
        notesX, this._ry(44) + 2, { align: 'right', maxWidth: 260 });
      d.text("الوحدات المستخدمة:", notesX, this._ry(45) + 2, { align: 'right' });
    }

    // ============ الصفوف 46-49: جدول الوحدات ============
    const unitTypes = [
      { c: 'B', ar: "نوع المادة", unit: "الوحدة" },
      { c: 'C', ar: "كيبل", unit: "متر" },
      { c: 'D', ar: "محول", unit: "عدد" },
      { c: 'E', ar: "محطة", unit: "عدد" },
      { c: 'F', ar: "عدادات", unit: "عدد" },
      { c: 'G', ar: "قواطع", unit: "عدد" },
      { c: 'I', ar: "اعمدة", unit: "عدد" },
      { c: 'J', ar: "حديد خردة", unit: "طن" },
      { c: 'KL', ar: "الاجزاء السخنة", unit: "طقم" },
    ];

    unitTypes.forEach(ut => {
      const r = this._r(ut.c);
      // صفوف 46-47: اسم المادة
      this._box(d, r.x, this._ry(46), r.w, this._sh(46, 47));
      if (this._fontLoaded) {
        d.setFont('Amiri', 'bold'); d.setFontSize(6); d.setTextColor(0, 0, 0);
        d.text(ut.ar, r.x + r.w / 2, this._ry(46) + this._sh(46, 47) / 2 + 0.5, { align: 'center' });
      }
      // صفوف 48-49: الوحدة
      this._box(d, r.x, this._ry(48), r.w, this._sh(48, 49));
      if (this._fontLoaded) {
        d.setFont('Amiri', 'bold'); d.setFontSize(6); d.setTextColor(0, 0, 0);
        d.text(ut.unit, r.x + r.w / 2, this._ry(48) + this._sh(48, 49) / 2 + 0.5, { align: 'center' });
      }
    });

    return d;
  },

  async generateAndDownload(items, woNumber = '') {
    const d = await this.generate(items, woNumber);
    d.save(`MDR_${woNumber || 'export'}_${new Date().toISOString().slice(0, 10)}.pdf`);
  }
};
if (typeof window !== 'undefined') window.MDRPdfGenerator = MDRPdfGenerator;
