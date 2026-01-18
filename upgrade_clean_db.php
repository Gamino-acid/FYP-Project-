<?php
// upgrade_clean_db.php - 删除 ID 0 并完美升级结构
include("connect.php");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    echo "<h3>正在执行数据库清理与升级...</h3>";
    
    // 1. 关闭外键检查 (允许删除和修改结构)
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // 2. 【关键】删除 ID 为 0 的数据 (防止 Bug)
    // 先删 Quota 里的 (因为 Quota 引用了 Supervisor)
    $conn->query("DELETE FROM `quota` WHERE `fyp_supervisorid` = '0' OR `fyp_supervisorid` = 0");
    // 再删 Supervisor 里的
    $conn->query("DELETE FROM `supervisor` WHERE `fyp_supervisorid` = 0");
    echo "✅ 已删除 ID 为 0 的 Supervisor 及相关记录。<br>";

    // 3. 修复 Quota 表结构 (从 VARCHAR 改为 INT)
    $conn->query("ALTER TABLE `quota` MODIFY `fyp_supervisorid` int(11) DEFAULT NULL");
    echo "✅ Quota 表结构已修正 (VARCHAR -> INT)。<br>";

    // 4. 给 Supervisor 表添加工号列 (fyp_staffid)
    $check = $conn->query("SHOW COLUMNS FROM `supervisor` LIKE 'fyp_staffid'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE `supervisor` ADD `fyp_staffid` VARCHAR(50) DEFAULT NULL AFTER `fyp_name`");
        echo "✅ 添加了 fyp_staffid 列 (用于存 SF001)。<br>";
    }

    // 5. 将 Supervisor 主键改为 Auto Increment
    $conn->query("ALTER TABLE `supervisor` MODIFY `fyp_supervisorid` int(11) NOT NULL AUTO_INCREMENT");
    echo "✅ Supervisor 表已成功开启 Auto Increment (自动递增)。<br>";

    // 6. 重置计数器 (确保下一个 ID 接着目前最大的 ID 后面)
    $res = $conn->query("SELECT MAX(fyp_supervisorid) as maxid FROM `supervisor`");
    $row = $res->fetch_assoc();
    $next_id = ($row['maxid'] ?? 0) + 1;
    $conn->query("ALTER TABLE `supervisor` AUTO_INCREMENT = $next_id");
    
    // 7. 恢复外键检查
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    echo "<hr><h1 style='color:green'>🎉 完美修复成功！</h1>";
    echo "<p>1. ID 为 0 的“幽灵数据”已被清除。<br>";
    echo "2. 你的数据库现在是标准的自动递增模式。<br>";
    echo "3. 请删除此文件，然后去管理页面试试 CSV 导入吧！</p>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ 出错了: " . $e->getMessage() . "</h2>";
}
?>