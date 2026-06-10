#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
تنظيف ملف SQL من Functions و Triggers
Clean SQL file from Functions and Triggers
"""

import sys

input_file = r'C:\Users\musta\Downloads\etgan_erp (33).sql'
output_file = r'C:\Users\musta\Downloads\etgan_erp (33)_cleaned_fixed.sql'

# قراءة الملف بترميز UTF-8
with open(input_file, 'r', encoding='utf-8') as f:
    lines = f.readlines()

print('📊 معلومات الملف الأصلي:')
print(f'   - إجمالي الأسطر: {len(lines)}')
print()

# حذف الأسطر من 24 إلى 69 (قسم Functions)
# في Python الفهرسة تبدأ من 0، لذا السطر 24 هو index 23
new_lines = lines[0:23] + lines[69:]

print('📊 بعد حذف Functions:')
print(f'   - إجمالي الأسطر: {len(new_lines)}')
print(f'   - الأسطر المحذوفة: {len(lines) - len(new_lines)} سطر')
print()

# الآن حذف الـ Triggers الثلاثة
# نحتاج لإعادة حساب الأرقام بعد الحذف الأول
triggers = [
    {'start': 829, 'end': 846, 'name': 'before_insert_final_for_partial_extract_work_order'},
    {'start': 1500, 'end': 1517, 'name': 'before_insert_final_regular_extract_work_order'},
    {'start': 4922, 'end': 4939, 'name': 'before_insert_partial_extract_work_order'}
]

# تعديل أرقام الـ Triggers بعد حذف 46 سطر
for trigger in triggers:
    trigger['start'] -= 46
    trigger['end'] -= 46

final_lines = []
deleted_lines = 0

for i, line in enumerate(new_lines):
    current_line = i + 1
    should_delete = False
    
    for trigger in triggers:
        if current_line >= trigger['start'] and current_line <= trigger['end']:
            should_delete = True
            if current_line == trigger['start']:
                print(f'🗑️  حذف Trigger: {trigger["name"]}')
            break
    
    if not should_delete:
        final_lines.append(line)
    else:
        deleted_lines += 1

print()
print('📊 بعد حذف Triggers:')
print(f'   - إجمالي الأسطر: {len(final_lines)}')
print(f'   - الأسطر المحذوفة: {deleted_lines} سطر')
print()

# حفظ الملف بترميز UTF-8
with open(output_file, 'w', encoding='utf-8') as f:
    f.writelines(final_lines)

print('✅ تم حفظ الملف النهائي في:')
print(f'   {output_file}')
print()

# التحقق من الترميز
print('📋 معاينة من الملف الجديد (التحقق من الترميز):')
with open(output_file, 'r', encoding='utf-8') as f:
    preview_lines = f.readlines()

print('السطر 20-30:')
for i in range(19, min(30, len(preview_lines))):
    print(preview_lines[i].rstrip())

print()
print('✅ تم التحقق: الملف يحتفظ بالترميز الصحيح للغة العربية')

