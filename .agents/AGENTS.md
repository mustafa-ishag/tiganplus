# Project Rules for Etgan Plus

## Arabic RTL Text Direction Rule

**CRITICAL**: This project is primarily in Arabic. When creating or editing ANY artifact (`.md` files in the brain directory), you MUST:

1. **Wrap ALL Arabic content** in a `<div dir="rtl">` tag at the very beginning of the artifact file.
2. **Use `dir="rtl"` on the root element** of every artifact markdown file that contains Arabic text.
3. **Format**: Start every Arabic artifact with:
   ```
   <div dir="rtl" style="text-align: right; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
   
   # عنوان الملف
   
   ... المحتوى ...
   
   </div>
   ```
4. **Tables**: Add `dir="rtl"` to table containers when the table contains Arabic text.
5. **Code blocks**: Code blocks should remain LTR (`dir="ltr"`) even inside RTL containers.
6. **Mixed content**: When mixing Arabic and English, the outer container should be RTL.

## Language Preference

- All user-facing text, comments, and documentation should be in **Arabic**.
- Code comments can be in Arabic.
- Variable names and code should remain in English.
