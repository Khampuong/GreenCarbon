<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล (ค่าเริ่มต้นตรงกับ XAMPP: root / ไม่มีรหัสผ่าน)
// สามารถกำหนดผ่าน environment variable GC_DB_* แทนการแก้ไฟล์นี้ได้
function gc_env($name, $default)
{
    $v = getenv($name);
    return $v === false ? $default : $v;
}

return [
    'db_host' => gc_env('GC_DB_HOST', '127.0.0.1'),
    'db_port' => gc_env('GC_DB_PORT', '3306'),
    'db_name' => gc_env('GC_DB_NAME', 'greencarbon'),
    'db_user' => gc_env('GC_DB_USER', 'root'),
    'db_pass' => gc_env('GC_DB_PASS', ''),
];
