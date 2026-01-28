# 📖 คู่มือการตั้งค่าระบบ Login ด้วย Google (OAuth 2.0)
## สำหรับระบบ UDRU Wisdom - Knowledge Center

คู่มือนี้สรุปขั้นตอนการตั้งค่า Google OAuth 2.0 เพื่อใช้งานร่วมกับระบบ UDRU Wisdom สำหรับนักพัฒนาและผู้ดูแลระบบ

---

### 🛠️ สิ่งที่ต้องเตรียม
1. บัญชี Google (แนะนำให้ใช้ @udru.ac.th เพื่อความเป็นทางการ)
2. เข้าใช้งาน [Google Cloud Console](https://console.cloud.google.com/)

---

### 1️⃣ ขั้นตอนการตั้งค่าใน Google Cloud Console

#### 1.1 สร้างโปรเจกต์ใหม่
- คลิกที่รายการโปรเจกต์ (บนซ้าย) > **New Project**
- ตั้งชื่อโปรเจกต์ เช่น `UDRU-Wisdom-Portal` > **Create**

#### 1.2 ตั้งค่า OAuth Consent Screen (หน้าจอขอความยินยอม)
ขั้นตอนนี้เป็นการบอก Google ว่าแอปพลิเคชันของเราคือใคร
- ไปที่เมนู **APIs & Services > OAuth consent screen**
- เลือก **User Type**:
    - **Internal**: อนุญาตเฉพาะบุคลากรที่มีอีเมล `@udru.ac.th` (แนะนำสำหรับการใช้งานภายใน)
    - **External**: อนุญาตให้ทุกคนที่มี Gmail เข้าถึงได้
- กรอกข้อมูลพื้นฐาน:
    - **App name**: `UDRU Wisdom`
    - **User support email**: เลือกเมลของคุณเอง
    - **Developer contact info**: กรอกเมลของคุณเอง
- กด **Save and Continue** จนจบกระบวนการ

#### 1.3 สร้าง Credentials (กุญแจเชื่อมต่อ)
- ไปที่เมนู **APIs & Services > Credentials**
- คลิก **+ CREATE CREDENTIALS** > **OAuth client ID**
- **Application type**: Web application
- **Name**: `UDRU Wisdom Web Client`
- **Authorized redirect URIs** (สำคัญมาก): 
    - กด **+ ADD URI**
    - สำหรับเครื่องตัวเอง (Local): `http://localhost:8080/kmudru/auth_google.php`
    - สำหรับเซิร์ฟเวอร์จริง: `https://your-domain.com/auth_google.php`
- กด **CREATE** และคัดลอกค่า **Client ID** และ **Client Secret** ไว้

---

### 2️⃣ ขั้นตอนการตั้งค่าในระบบ (Source Code)

เปิดไฟล์ `includes/google_config.php` ในโปรเจกต์ของคุณ และแทนที่ค่าที่ได้รับมา:

```php
<?php
// Google API Configuration
define('GOOGLE_CLIENT_ID', 'ใส่_Client_ID_ที่ได้จาก_Google_ตรงนี้');
define('GOOGLE_CLIENT_SECRET', 'ใส่_Client_Secret_ที่ได้จาก_Google_ตรงนี้');

// เมื่อขึ้น Server จริง ต้องเปลี่ยน URL นี้ให้ตรงกับความเป็นจริง
define('GOOGLE_REDIRECT_URL', 'http://localhost:8080/kmudru/auth_google.php');
?>
```

---

### 3️⃣ สรุปการทำงานของระบบ (Workflow)
1. **ผู้ใช้คลิกปุ่ม**: ระบบจะส่งผู้ใช้ไปยังหน้า Login ของ Google
2. **ผู้ใช้ยินยอม**: Google ตรวจสอบตัวตน และส่งรหัส (Auth Code) กลับมายัง `auth_google.php`
3. **ระบบตรวจสอบรหัส**: เซิร์ฟเวอร์ของเราจะนำรหัสไปแลกข้อมูลชื่อและอีเมลจาก Google
4. **ลงทะเบียนอัตโนมัติ**: 
    - หากอีเมลนี้ยังไม่มีในระบบ -> ระบบจะสร้างบัญชีสมาชิกใหม่ให้ทันที
    - หากมีอีเมลในระบบอยู่แล้ว -> ระบบจะทำ Login ให้ทันทีเข้าสู่หน้าหลัก

---

### ⚠️ ข้อควรระวังและการแก้ไขปัญหา
- **Error 401 (invalid_client)**: ตรวจสอบว่าคัดลอก Client ID มาครบถ้วนหรือไม่
- **Error 400 (redirect_uri_mismatch)**: ตรวจสอบว่า URL ในไฟล์ส่งค่าไป ตรงกับที่ตั้งไว้ใน Google Cloud Console หรือไม่ (ต้องเหมือนกันทุกตัวอักษร)
- **สถานะการเข้าถึง**: ตัวเครื่อง Localhost มักจะขึ้นคำเตือนว่าเป็นแอปที่ยังไม่ผ่านการตรวจสอบ (Unverified App) สามารถกด **Advanced > Go to your-site (unsafe)** เพื่อทดสอบได้ตามปกติครับ

---
*จัดทำโดย: AI Assistant สำหรับ UDRU Wisdom Project*
