# KM Portal - Knowledge Management System

ระบบจัดการความรู้ (Knowledge Management) สำหรับมหาวิทยาลัยราชภัฏอุดรธานี (UDRU) ที่เน้นความสวยงาม ทันสมัย และการใช้งานที่ลื่นไหล

## คุณสมบัติเด่น (Key Features)

- **Interactive Sidebar**: ระบบเมนูที่ยุบ/ขยายได้ พร้อมจดจำสถานะการใช้งาน
- **Advanced Analytics**: ระบบวิเคราะห์ข้อมูลด้วย Chart.js (Area Chart & Donut Chart)
- **Content Creation Hub**: ศูนย์กลางการสร้างเนื้อหา ทั้ง เอกสาร (Upload), Wiki, Q&A และ CoP
- **Community of Practice (CoP)**: พื้นที่แลกเปลี่ยนเรียนรู้สำหรับกลุ่มผู้เชี่ยวชาญ
- **Premium UI/UX**: ดีไซน์ระดับ High-Fidelity พร้อมระบบ Glassmorphism และ Micro-animations

## เทคโนโลยีที่ใช้ (Tech Stack)

- **Frontend**: HTML5, Vanilla CSS (Custom HSL System), Javascript
- **Backend**: PHP 8.x
- **Database**: MySQL / MariaDB
- **Icons**: Lucide Icons
- **Charts**: Chart.js

## การติดตั้ง (Installation)

1.  **Clone Project** หรือดาวน์โหลดไฟล์ไปไว้ที่โฟลเดอร์ `htdocs` ของ XAMPP
2.  **จัดการฐานข้อมูล**:
    - สร้างฐานข้อมูลชื่อ `kmud_db`
    - Import ไฟล์ `setup.sql` เข้าไปยังฐานข้อมูล
3.  **ตั้งค่าการเชื่อมต่อ**:
    - ไปที่โฟลเดอร์ `includes/`
    - คัดลอกไฟล์ `db_sample.php` และเปลี่ยนชื่อเป็น `db.php`
    - แก้ไขข้อมูลการเชื่อมต่อ (Host, Username, Password) ให้ตรงกับเครื่องของคุณ
4.  **เริ่มใช้งาน**: เข้าไปที่ `http://localhost/kmudru` (หรือชื่อโฟลเดอร์ที่คุณตั้งไว้)

## ผู้พัฒนา (Development)

โปรเจกต์นี้ได้รับการพัฒนาโดยเน้นมาตรฐาน Pixel-Perfect ตามต้นแบบจาก https://kmud.lovable.app/ โดยมีการเสริมฟีเจอร์ด้าน Analytics และ Profile ให้สมบูรณ์ยิ่งขึ้น
