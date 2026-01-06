-- 数据库表结构定义

-- 护士表
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '姓名',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态：1-在职，0-离职',
  `enable_night` tinyint(1) DEFAULT 1 COMMENT '是否可上夜班：1-可以，0-不可以',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='护士表';

-- 排班表
CREATE TABLE IF NOT EXISTS `schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL COMMENT '护士ID',
  `work_date` date NOT NULL COMMENT '工作日期',
  `shift_code` varchar(20) NOT NULL COMMENT '班次代码：P,N,助夜,医嘱,服药,医+服,正1,正2,正(中),A1,A2,休,休(prn)',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_work_date` (`work_date`),
  KEY `idx_staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='排班表';

-- 人员申请表
CREATE TABLE IF NOT EXISTS `staff_request` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL COMMENT '护士ID',
  `date` date NOT NULL COMMENT '申请日期',
  `request_type` varchar(20) NOT NULL COMMENT '申请类型：休息,指定班次',
  `shift_code` varchar(20) DEFAULT NULL COMMENT '指定班次代码',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `idx_staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人员申请表';

-- 示例数据
INSERT INTO `staff` (`name`, `status`, `enable_night`) VALUES
('张护士', 1, 1),
('李护士', 1, 1),
('王护士', 1, 1),
('赵护士', 1, 1),
('陈护士', 1, 1),
('刘护士', 1, 1),
('杨护士', 1, 1),
('周护士', 1, 1);