<?php
require __DIR__ . '/includes/init.php';

if (is_post()) {
    session_unset();
    session_regenerate_id(true);
    flash('ออกจากระบบแล้ว');
}
redirect('index.php');
