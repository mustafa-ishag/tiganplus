const FATRAPdfGenerator = {
  async generateAndDownload(items, woNumber, woLocation) {
    if (typeof PizZip === 'undefined' || typeof window.docxtemplater === 'undefined') {
      alert("مكتبات PizZip أو docxtemplater غير متوفرة في الصفحة.");
      return;
    }

    try {
      // 1. جلب قالب الوورد
      const response = await fetch('./FATRA.DOCX');
      if (!response.ok) {
        throw new Error("لم يتم العثور على ملف FATRA.DOCX في المجلد الرئيسي. يرجى التأكد من وجوده.");
      }
      const arrayBuffer = await response.arrayBuffer();

      // 2. تحميل الملف في PizZip
      const zip = new PizZip(arrayBuffer);

      // 3. تهيئة docxtemplater
      const doc = new window.docxtemplater(zip, {
        paragraphLoop: true,
        linebreaks: true,
        nullGetter: function(part) {
          if (!part.module) return "";
          if (part.module === "rawxml") return "";
          return "";
        }
      });

      // 4. تجهيز البيانات للزرع داخل ملف الوورد
      const data = {
        woNumber: woNumber || '',
        location: woLocation || '',
        date: new Date().toLocaleDateString('en-GB'),
        // مصفوفة المواد لزرعها في الجدول (يجب استخدام {#items} في الوورد)
        items: items.map((item, index) => ({
          index: index + 1,
          item_number: item.item_number || '',
          assembly: item.assembly || '',
          description: item.equipment || item.description || '',
          unit: item.unit || 'EA',
          functional_location: item.functional_location || '',
          equipment: item.equipment || '',
          capacity_kva: item.capacity_kva || '',
          manufacturer: item.manufacturer || '',
          prim_sec_volt: item.prim_sec_volt || '',
          year: item.year || '',
          serial_number: item.serial_number || '',
          quantity: item.quantity || 1,
          status: item.status || '',
          item_type: item.item_type || ''
        }))
      };

      // 5. دمج البيانات مع القالب
      doc.render(data);

      // 6. استخراج الملف المعبأ كـ Blob
      const out = doc.getZip().generate({
        type: "blob",
        mimeType: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      });

      // 7. إرسال الملف المعبأ إلى PDF-SERVER لتحويله
      const formData = new FormData();
      formData.append('file', out, 'FATRA_filled.docx');

      const serverResponse = await fetch('https://pdf-converter-69cg.onrender.com/convert', {
        method: 'POST',
        body: formData
      });

      if (!serverResponse.ok) {
        const errText = await serverResponse.text();
        throw new Error(`خطأ من خادم التحويل: ${errText}`);
      }

      // 8. تنزيل ملف الـ PDF النهائي
      const pdfBlob = await serverResponse.blob();
      const url = window.URL.createObjectURL(pdfBlob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `FATRA_${woNumber || 'export'}_${new Date().toISOString().slice(0, 10)}.pdf`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);

    } catch (error) {
      console.error('FATRA Docx Export Error:', error);
      alert('حدث خطأ أثناء التصدير:\n' + error.message);
    }
  }
};

if (typeof window !== 'undefined') window.FATRAPdfGenerator = FATRAPdfGenerator;
