<?php
require_once "vendor/autoload.php";

try {
    // 首先尝试连接到MySQL服务器但不指定数据库
    $pdo = new PDO("mysql:host=127.0.0.1;charset=utf8", "root", "123456");
    
    // 尝试创建数据库（如果不存在）
    $pdo->exec("CREATE DATABASE IF NOT EXISTS schedule CHARACTER SET utf8 COLLATE utf8_general_ci");
    
    // 然后连接到具体的数据库
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=schedule;charset=utf8", "root", "123456");
    
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    if ($tables) {
        echo "Tables in database:\n";
        foreach($tables as $table) {
            echo "- " . $table . "\n";
        }
    } else {
        echo "No tables found in the database.\n";
    }
} catch(Exception $e) {
    echo 'Database connection failed: ' . $e->getMessage() . "\n";
}