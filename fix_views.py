#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
إصلاح الـ VIEWs في ملف SQL
Fix VIEWs in SQL file
"""

import re

input_file = r'C:\Users\musta\Downloads\etgan_erp (33)_cleaned_fixed.sql'
output_file = r'C:\Users\musta\Downloads\etgan_erp (33)_final_fixed.sql'

print('📊 معلومات الملف الأصلي:')
print(f'   - الملف: {input_file}')
print()

# قراءة الملف بترميز UTF-8
with open(input_file, 'r', encoding='utf-8') as f:
    content = f.read()

print('🔍 البحث عن الـ VIEWs المعطلة...')
print()

# البحث عن جميع الـ VIEWs
view_pattern = r'CREATE\s+(?:ALGORITHM=\w+\s+)?DEFINER=``\s+SQL\s+SECURITY\s+\w+\s+VIEW'
views_found = re.findall(view_pattern, content, re.IGNORECASE)

print(f'✅ تم العثور على {len(views_found)} VIEW(s) مع DEFINER=``')
print()

# إصلاح الـ VIEWs بحذف DEFINER=`` و ALGORITHM و SQL SECURITY
# الطريقة: استبدال CREATE ALGORITHM=... DEFINER=`` SQL SECURITY ... VIEW
# بـ: CREATE VIEW

# النمط الكامل للـ VIEW
full_view_pattern = r'CREATE\s+ALGORITHM=\w+\s+DEFINER=``\s+SQL\s+SECURITY\s+\w+\s+VIEW'

# الاستبدال
fixed_content = re.sub(
    full_view_pattern,
    'CREATE VIEW',
    content,
    flags=re.IGNORECASE
)

# التحقق من التغييرات
if fixed_content != content:
    print('✅ تم إصلاح الـ VIEWs:')
    print('   - تم حذف: ALGORITHM=...')
    print('   - تم حذف: DEFINER=``')
    print('   - تم حذف: SQL SECURITY ...')
    print()
else:
    print('⚠️  لم يتم العثور على VIEWs تحتاج إلى إصلاح')
    print()

# حفظ الملف المُصلح
with open(output_file, 'w', encoding='utf-8') as f:
    f.write(fixed_content)

print('✅ تم حفظ الملف المُصلح في:')
print(f'   {output_file}')
print()

# التحقق من الملف الجديد
print('📋 معاينة من الملف الجديد:')
with open(output_file, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# البحث عن أول VIEW في الملف الجديد
for i, line in enumerate(lines):
    if 'CREATE VIEW' in line and 'work_order_extract_status' in lines[i:min(i+5, len(lines))]:
        print()
        print('السطر الذي يحتوي على CREATE VIEW:')
        for j in range(max(0, i-1), min(i+3, len(lines))):
            print(f'   {lines[j].rstrip()}')
        break

print()
print('✅ تم الانتهاء من إصلاح الـ VIEWs')
print()

# إحصائيات
print('📊 الإحصائيات:')
print(f'   - عدد الأسطر: {len(lines)}')
print(f'   - حجم الملف: {len(fixed_content)} حرف')

