#!/bin/bash
# Ubuntu 24.04 LTS 一键部署脚本（PHP 8.4 + MySQL 8.4 + Nginx）
# 用法：cd Inventory_Management/deploy && sudo bash install.sh
set -e

echo "==> 更新系统"
apt update && apt upgrade -y

echo "==> 安装 Nginx"
apt install -y nginx
systemctl enable --now nginx

echo "==> 添加 PHP 8.4 源并安装"
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
# 注意：php8.4-json 不需要安装，JSON 扩展自 PHP 8.0 起已内置编译
apt install -y php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-cli
systemctl enable --now php8.4-fpm

echo "==> 安装 MySQL 8.4（通过 MySQL 官方 APT 源）"
apt install -y wget lsb-release gnupg
# 注意：必须使用 RPM-GPG-KEY-mysql-2025（有效期至 2027-10-23）。
# 旧的 RPM-GPG-KEY-mysql-2023 密钥已于 2025-10-22 过期，会导致 apt 报 EXPKEYSIG B7B3B788A8D3785C。
wget -qO- https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | gpg --batch --yes --dearmor -o /usr/share/keyrings/mysql.gpg
echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-8.4-lts" > /etc/apt/sources.list.d/mysql-8.4.list
echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-tools" >> /etc/apt/sources.list.d/mysql-8.4.list
apt update
# 若 3306 已被占用（如 WSL2 镜像网络下 Windows 侧已有 MySQL），改用 3307 避免冲突。
# 应用通过 Unix socket 连接，端口仅供调试；3306 空闲时仍用默认端口。
if ss -tlnH 2>/dev/null | awk '{print $4}' | grep -qE ':3306$'; then
  echo "    检测到 3306 已被占用，本机 MySQL 改监听 3307"
  mkdir -p /etc/mysql/mysql.conf.d
  printf '[mysqld]\nport=3307\nmysqlx_port=33070\n' > /etc/mysql/mysql.conf.d/zz-port.cnf
fi
DEBIAN_FRONTEND=noninteractive apt install -y mysql-server
systemctl enable --now mysql

echo "==> 初始化数据库"
mysql -e "CREATE DATABASE IF NOT EXISTS stock_manage DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'stock_user'@'localhost' IDENTIFIED BY 'Stock@123456';"
mysql -e "GRANT ALL PRIVILEGES ON stock_manage.* TO 'stock_user'@'localhost'; FLUSH PRIVILEGES;"
mysql stock_manage < ../sql/stock.sql

echo "==> 安装 Node.js 20 LTS（前端构建用）"
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

echo "==> 部署项目文件"
mkdir -p /var/www/stock/dist /var/www/stock/api
cp -r ../api/* /var/www/stock/api/
chown -R www-data:www-data /var/www/stock

echo "==> 配置 Nginx"
cp stock.conf /etc/nginx/sites-available/stock.conf
ln -sf /etc/nginx/sites-available/stock.conf /etc/nginx/sites-enabled/stock.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo "==> 构建前端"
cd ../frontend
# 清理可能残留的旧 node_modules：若从 Windows 复制而来，符号链接会退化为 0 字节文件
# 且无写权限，导致 vite 无法执行（sh: vite: Permission denied）。清理后由 npm 重建。
chmod -R u+w node_modules 2>/dev/null || true
rm -rf node_modules 2>/dev/null || true
npm install
npm run build
cp -r dist/* /var/www/stock/dist/
chown -R www-data:www-data /var/www/stock/dist

echo "部署完成！浏览器访问 http://<服务器IP>/"
