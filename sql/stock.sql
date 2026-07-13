-- 商品库存管理系统数据库初始化脚本
CREATE DATABASE IF NOT EXISTS stock_manage DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stock_manage;

-- 商品表
CREATE TABLE IF NOT EXISTS `goods` (
  id INT PRIMARY KEY AUTO_INCREMENT COMMENT '商品ID',
  name VARCHAR(100) NOT NULL COMMENT '商品名称',
  category VARCHAR(50) COMMENT '分类',
  spec VARCHAR(50) COMMENT '规格',
  buy_price DECIMAL(10,2) DEFAULT 0 COMMENT '进价',
  sell_price DECIMAL(10,2) DEFAULT 0 COMMENT '售价',
  stock_num INT DEFAULT 0 COMMENT '当前库存',
  warn_num INT DEFAULT 10 COMMENT '预警库存',
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 入库单主表
CREATE TABLE IF NOT EXISTS stock_in (
  id INT PRIMARY KEY AUTO_INCREMENT,
  in_no VARCHAR(32) UNIQUE NOT NULL COMMENT '入库单号',
  supplier VARCHAR(100) COMMENT '供应商',
  total_money DECIMAL(12,2) DEFAULT 0 COMMENT '入库总金额',
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 入库明细
CREATE TABLE IF NOT EXISTS stock_in_item (
  id INT PRIMARY KEY AUTO_INCREMENT,
  in_id INT NOT NULL,
  goods_id INT NOT NULL,
  num INT NOT NULL COMMENT '入库数量',
  price DECIMAL(10,2) NOT NULL COMMENT '单价',
  FOREIGN KEY (in_id) REFERENCES stock_in(id),
  FOREIGN KEY (goods_id) REFERENCES goods(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 出库单主表
CREATE TABLE IF NOT EXISTS stock_out (
  id INT PRIMARY KEY AUTO_INCREMENT,
  out_no VARCHAR(32) UNIQUE NOT NULL COMMENT '出库单号',
  customer VARCHAR(100) COMMENT '客户/领用部门',
  reason VARCHAR(200) COMMENT '出库原因',
  total_money DECIMAL(12,2) DEFAULT 0,
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 出库明细
CREATE TABLE IF NOT EXISTS stock_out_item (
  id INT PRIMARY KEY AUTO_INCREMENT,
  out_id INT NOT NULL,
  goods_id INT NOT NULL,
  num INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (out_id) REFERENCES stock_out(id),
  FOREIGN KEY (goods_id) REFERENCES goods(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 库存盘点表
CREATE TABLE IF NOT EXISTS stock_check (
  id INT PRIMARY KEY AUTO_INCREMENT,
  check_no VARCHAR(32) NOT NULL COMMENT '盘点单号（一单多行，行内非唯一）',
  goods_id INT NOT NULL,
  book_num INT NOT NULL COMMENT '账面库存',
  real_num INT NOT NULL COMMENT '实际库存',
  diff_num INT NOT NULL COMMENT '盘盈盘亏',
  remark VARCHAR(255) DEFAULT '' COMMENT '盈亏原因备注',
  check_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_check_no(check_no),
  FOREIGN KEY (goods_id) REFERENCES goods(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户表：登录鉴权（password_hash 存 bcrypt 哈希，不存明文）
-- permissions: 逗号分隔的权限码，可选值：goods, stock_in, stock_out, check
--   admin 角色天然全权限，permissions 字段可空；非 admin 用户按此列表放行
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL UNIQUE COMMENT '登录账号',
  password_hash VARCHAR(255) NOT NULL COMMENT 'bcrypt/argon2 哈希，非明文',
  role VARCHAR(32) NOT NULL DEFAULT 'admin' COMMENT '角色：admin/keeper',
  permissions VARCHAR(255) NOT NULL DEFAULT '' COMMENT '权限码：goods,stock_in,stock_out,check',
  is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  last_login_at DATETIME NULL COMMENT '最近登录时间',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始管理员：admin / admin123（首次登录后请自行修改密码）
-- 下面这一行 hash 是 password_hash('admin123', PASSWORD_DEFAULT) 的结果
INSERT IGNORE INTO users(username, password_hash, role)
VALUES ('admin', '$2y$12$KexUwwlZP3GdUtwUgxOeE.rr.upbL4k.w2OKl5hitPg4hFPN/j11a', 'admin');

-- 登录失败限流：以 (ip, username) 为键计数，达到阈值临时锁定
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ip VARCHAR(64) NOT NULL,
  username VARCHAR(64) NOT NULL,
  fail_count INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ip_user (ip, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
