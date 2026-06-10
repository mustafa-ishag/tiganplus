const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const express = require('express');

// Initialize Express app
const app = express();
app.use(express.json());

const PORT = process.env.PORT || 3000;

// =============================================
// ⚙️ إعدادات النظام
// =============================================

// رابط PHP API لنظام تِقان - عدّله حسب عنوان الاستضافة
const PHP_API_URL = process.env.PHP_API_URL || 'http://YOUR_DOMAIN_OR_IP/etganplus';

// مفتاح API للتحقق - يجب أن يتطابق مع الموجود في ملف PHP
const API_KEY = process.env.API_KEY || 'tiqan_wa_bot_2026_secure_key';

// تفعيل/تعطيل ميزة الرد التلقائي
let autoReplyEnabled = true;

// =============================================
// 🤖 تهيئة عميل واتساب
// =============================================

const client = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: {
        headless: true,
        args: [
            '--no-sandbox', 
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu'
        ]
    }
});

let isClientReady = false;

// =============================================
// 📡 أحداث عميل واتساب
// =============================================

client.on('loading_screen', (percent, message) => {
    console.log(`\n⏳ جاري تحميل واتساب ويب... ${percent}%`);
    console.log(`📝 رسالة النظام: ${message}`);
});

client.on('qr', (qr) => {
    console.log('\n==================================================');
    console.log('📌 يرجى فتح الواتساب في جوالك ومسح هذا الباركود (QR Code):');
    console.log('==================================================\n');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    isClientReady = true;
    console.log('\n✅ اكتمل الربط! الواتساب جاهز الآن لاستقبال وإرسال الرسائل عبر الـ API الخاص بنا.');
    console.log(`🌐 يمكنك إرسال الطلبات إلى: http://localhost:${PORT}/send-message`);
    console.log(`🤖 الرد التلقائي: ${autoReplyEnabled ? 'مفعّل ✅' : 'معطّل ❌'}\n`);
});

client.on('authenticated', () => {
    console.log('✅ تمت المصادقة بنجاح مع سيرفرات واتساب!');
});

client.on('auth_failure', msg => {
    console.error('❌ فشل في المصادقة مع الواتساب:', msg);
});

// =============================================
// 💬 الرد التلقائي على استعلام أمر العمل
// =============================================

client.on('message', async (msg) => {
    // تجاهل إذا كانت الميزة معطلة
    if (!autoReplyEnabled) return;

    // تجاهل الرسائل من المجموعات والرسائل المرسلة من نفس الحساب
    if (msg.fromMe) return;

    // يمكن تجاهل رسائل المجموعات أو السماح بها - حالياً نتجاهلها
    const chat = await msg.getChat();
    if (chat.isGroup) return;

    const text = msg.body.trim();

    // =============================================
    // 🔍 فحص إذا كان النص رقم أمر عمل
    // =============================================
    // نمط: أرقام فقط بطول 7 إلى 12 رقم
    // يمكنك تعديل النمط حسب نظام ترقيم أوامر العمل لديكم
    const workOrderPattern = /^\d{7,12}$/;

    if (!workOrderPattern.test(text)) {
        // ليس رقم أمر عمل - تجاهل
        return;
    }

    console.log(`\n📩 استعلام وارد من ${msg.from}: أمر عمل ${text}`);

    try {
        // إرسال رسالة "جاري البحث..."
        await msg.reply('🔍 جاري البحث عن بيانات أمر العمل...');

        // استدعاء PHP API
        const apiUrl = `${PHP_API_URL}/api/whatsapp/material-analysis.php?wo=${encodeURIComponent(text)}&key=${encodeURIComponent(API_KEY)}`;
        
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
            signal: AbortSignal.timeout(15000) // مهلة 15 ثانية
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error(`❌ PHP API Error (HTTP ${response.status}):`, errorText);
            await msg.reply(`❌ حدث خطأ أثناء البحث. يرجى المحاولة لاحقاً.`);
            return;
        }

        const data = await response.json();

        if (data.success) {
            // إرسال رسالة التحليل المنسقة
            await msg.reply(data.formatted_message);
            console.log(`✅ تم إرسال تحليل المواد لأمر العمل ${text} إلى ${msg.from}`);
        } else {
            // أمر العمل غير موجود
            await msg.reply(`❌ ${data.message || 'لم يتم العثور على أمر العمل'}\n\nتأكد من صحة رقم أمر العمل وحاول مرة أخرى.`);
            console.log(`⚠️ أمر العمل ${text} غير موجود - استعلام من ${msg.from}`);
        }

    } catch (error) {
        console.error(`❌ خطأ في الرد التلقائي:`, error.message);
        
        if (error.name === 'TimeoutError' || error.name === 'AbortError') {
            await msg.reply('⏰ انتهت مهلة الاتصال بالخادم. يرجى المحاولة لاحقاً.');
        } else {
            await msg.reply('❌ حدث خطأ أثناء جلب البيانات. يرجى المحاولة لاحقاً.');
        }
    }
});

// =============================================
// 🌐 نقاط الوصول API
// =============================================

// حالة الخادم
app.get('/status', (req, res) => {
    res.json({
        success: true,
        whatsapp_ready: isClientReady,
        auto_reply: autoReplyEnabled,
        uptime: process.uptime(),
        php_api_url: PHP_API_URL
    });
});

// جلب قائمة المجموعات
app.get('/groups', async (req, res) => {
    if (!isClientReady) {
        return res.status(503).json({ success: false, message: 'خدمة الواتساب غير جاهزة.' });
    }
    try {
        const chats = await client.getChats();
        const groups = chats.filter(chat => chat.isGroup).map(group => ({
            id: group.id._serialized,
            name: group.name
        }));
        res.json({ success: true, groups });
    } catch (error) {
        res.status(500).json({ success: false, message: 'حدث خطأ.', error: error.toString() });
    }
});

// إرسال رسالة
app.post('/send-message', async (req, res) => {
    if (!isClientReady) {
        return res.status(503).json({ success: false, message: 'خدمة الواتساب قيد التشغيل أو لم يتم ربط الجوال بعد. يرجى مسح الباركود أولاً.' });
    }

    const { number, message, isGroup } = req.body;

    if (!number || !message) {
        return res.status(400).json({ success: false, message: 'يرجى إرسال رقم الجوال والنص (number, message).' });
    }

    try {
        let chatId = '';

        if (isGroup || number.endsWith('@g.us')) {
            chatId = number.includes('@g.us') ? number : `${number}@g.us`;
        } else {
            let cleanNumber = number.replace(/[^0-9]/g, '');
            if (cleanNumber.startsWith('05')) {
                cleanNumber = '966' + cleanNumber.substring(1);
            }
            chatId = `${cleanNumber}@c.us`;

            const isRegistered = await client.isRegisteredUser(chatId);
            if (!isRegistered) {
                return res.status(404).json({ success: false, message: 'الرقم غير مسجل في واتساب أو صيغة الرقم غير صحيحة.' });
            }
        }

        const response = await client.sendMessage(chatId, message);
        res.json({ success: true, message: 'تم الإرسال بنجاح!', responseId: response.id.id });
    } catch (error) {
        console.error('Error sending message:', error);
        res.status(500).json({ success: false, message: 'حدث خطأ أثناء الإرسال.', error: error.toString() });
    }
});

// =============================================
// 🎛️ تحكم بالرد التلقائي
// =============================================

// تفعيل الرد التلقائي
app.post('/auto-reply/enable', (req, res) => {
    autoReplyEnabled = true;
    console.log('🤖 تم تفعيل الرد التلقائي');
    res.json({ success: true, message: 'تم تفعيل الرد التلقائي', auto_reply: true });
});

// تعطيل الرد التلقائي
app.post('/auto-reply/disable', (req, res) => {
    autoReplyEnabled = false;
    console.log('🤖 تم تعطيل الرد التلقائي');
    res.json({ success: true, message: 'تم تعطيل الرد التلقائي', auto_reply: false });
});

// حالة الرد التلقائي
app.get('/auto-reply/status', (req, res) => {
    res.json({ success: true, auto_reply: autoReplyEnabled });
});

// =============================================
// 🚀 تشغيل الخادم
// =============================================

app.listen(PORT, () => {
    console.log(`🚀 خادم الـ API يعمل على المنفذ ${PORT}`);
    console.log('⏳ جاري تهيئة متصفح الواتساب في الخلفية... الرجاء الانتظار...');
    console.log(`\n📋 الأوامر المتاحة:`);
    console.log(`   GET  /status             - حالة الخادم`);
    console.log(`   POST /send-message       - إرسال رسالة`);
    console.log(`   GET  /groups             - قائمة المجموعات`);
    console.log(`   POST /auto-reply/enable  - تفعيل الرد التلقائي`);
    console.log(`   POST /auto-reply/disable - تعطيل الرد التلقائي`);
    console.log(`   GET  /auto-reply/status  - حالة الرد التلقائي\n`);
});

// Initialize the WhatsApp Client
client.initialize();
