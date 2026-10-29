-- ============================================================
--  داریا (Darya) v1.0.0 — سامانه مدیریت و حسابداری
--  MySQL 5.7+ / MariaDB 10.3+  |  utf8mb4
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- کاربران و نقش‌ها ----------
CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  permissions TEXT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  role_id INT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- مشتریان / کارمندان / مبلغان ----------
CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  company VARCHAR(150) NULL,
  type ENUM('person','company') NOT NULL DEFAULT 'person',
  mobile VARCHAR(20) NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  address TEXT NULL,
  city VARCHAR(80) NULL,
  postal_code VARCHAR(20) NULL,
  national_id VARCHAR(20) NULL,
  economic_code VARCHAR(20) NULL,
  credit_limit DECIMAL(18,2) NOT NULL DEFAULT 0,
  balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  wp_user_id BIGINT UNSIGNED NULL,
  wp_roles VARCHAR(190) NULL,
  source VARCHAR(30) NOT NULL DEFAULT 'manual',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (wp_user_id), INDEX (mobile), INDEX (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  national_id VARCHAR(20) NULL,
  mobile VARCHAR(20) NULL,
  position VARCHAR(80) NULL,
  department VARCHAR(80) NULL,
  hire_date DATE NULL,
  base_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
  insurance_no VARCHAR(50) NULL,
  bank_name VARCHAR(80) NULL,
  bank_account VARCHAR(50) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promoters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  pin VARCHAR(10) NULL,
  commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  wp_user_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- کالا و انبار ----------
CREATE TABLE IF NOT EXISTS product_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  parent_id INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(64) NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  category_id INT UNSIGNED NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'عدد',
  purchase_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  min_stock DECIMAL(18,3) NOT NULL DEFAULT 0,
  stock_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  wc_product_id BIGINT UNSIGNED NULL,
  source VARCHAR(30) NOT NULL DEFAULT 'manual',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (category_id), INDEX (wc_product_id), INDEX (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  location VARCHAR(190) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL DEFAULT 1,
  movement_type ENUM('in','out','transfer_in','transfer_out','adjustment') NOT NULL,
  qty DECIMAL(18,3) NOT NULL,
  unit_cost DECIMAL(18,2) NOT NULL DEFAULT 0,
  ref_type VARCHAR(30) NULL,
  ref_id BIGINT UNSIGNED NULL,
  description VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id), INDEX (warehouse_id), INDEX (ref_type, ref_id), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- فروش و خرید ----------
CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(32) NOT NULL UNIQUE,
  invoice_type ENUM('sale','purchase','return_sale','return_purchase') NOT NULL DEFAULT 'sale',
  customer_id BIGINT UNSIGNED NULL,
  supplier_name VARCHAR(150) NULL,
  employee_id INT UNSIGNED NULL,
  promoter_id INT UNSIGNED NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NULL,
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  shipping DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  status ENUM('draft','pending','unpaid','partial','paid','canceled') NOT NULL DEFAULT 'draft',
  payment_method VARCHAR(30) NULL,
  description TEXT NULL,
  source VARCHAR(30) NOT NULL DEFAULT 'manual',
  wc_order_id BIGINT UNSIGNED NULL,
  wc_order_no VARCHAR(50) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (customer_id), INDEX (invoice_type), INDEX (status),
  INDEX (invoice_date), INDEX (wc_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  description VARCHAR(190) NULL,
  qty DECIMAL(18,3) NOT NULL DEFAULT 1,
  unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  INDEX (invoice_id), INDEX (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- خزانه: صندوق، بانک، تراکنش، چک ----------
CREATE TABLE IF NOT EXISTS cashboxes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('cash','bank') NOT NULL DEFAULT 'cash',
  bank_name VARCHAR(80) NULL,
  account_number VARCHAR(50) NULL,
  iban VARCHAR(30) NULL,
  card_number VARCHAR(25) NULL,
  balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trx_no VARCHAR(32) NULL,
  type ENUM('receipt','payment') NOT NULL,
  party_type ENUM('customer','employee','promoter','other') NOT NULL DEFAULT 'customer',
  party_id BIGINT UNSIGNED NULL,
  cashbox_id INT UNSIGNED NOT NULL,
  method ENUM('cash','transfer','cheque','card','online') NOT NULL DEFAULT 'cash',
  amount DECIMAL(18,2) NOT NULL,
  trx_date DATE NOT NULL,
  invoice_id BIGINT UNSIGNED NULL,
  description VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (cashbox_id), INDEX (trx_date), INDEX (invoice_id),
  INDEX (party_type, party_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cheques (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  direction ENUM('received','issued') NOT NULL,
  cheque_no VARCHAR(50) NOT NULL,
  bank_name VARCHAR(80) NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  party_type VARCHAR(20) NULL,
  party_id BIGINT UNSIGNED NULL,
  cashbox_id INT UNSIGNED NULL,
  invoice_id BIGINT UNSIGNED NULL,
  status ENUM('in_hand','deposited','cleared','bounced','canceled') NOT NULL DEFAULT 'in_hand',
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status), INDEX (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- حسابداری ----------
CREATE TABLE IF NOT EXISTS accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  type ENUM('asset','liability','equity','income','expense') NOT NULL,
  parent_id INT UNSIGNED NULL,
  is_group TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (parent_id), INDEX (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vouchers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_no VARCHAR(32) NOT NULL UNIQUE,
  voucher_date DATE NOT NULL,
  description VARCHAR(255) NULL,
  status ENUM('draft','posted') NOT NULL DEFAULT 'posted',
  source VARCHAR(40) NOT NULL DEFAULT 'manual',
  ref_type VARCHAR(30) NULL,
  ref_id BIGINT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (voucher_date), INDEX (source, ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voucher_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voucher_id BIGINT UNSIGNED NOT NULL,
  account_id INT UNSIGNED NOT NULL,
  debit DECIMAL(18,2) NOT NULL DEFAULT 0,
  credit DECIMAL(18,2) NOT NULL DEFAULT 0,
  description VARCHAR(190) NULL,
  INDEX (voucher_id), INDEX (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- حقوق و دستمزد ----------
CREATE TABLE IF NOT EXISTS payrolls (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  period_year SMALLINT NOT NULL,
  period_month TINYINT NOT NULL,
  base_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
  overtime DECIMAL(18,2) NOT NULL DEFAULT 0,
  bonus DECIMAL(18,2) NOT NULL DEFAULT 0,
  insurance_deduction DECIMAL(18,2) NOT NULL DEFAULT 0,
  tax_deduction DECIMAL(18,2) NOT NULL DEFAULT 0,
  other_deduction DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_pay DECIMAL(18,2) NOT NULL DEFAULT 0,
  status ENUM('draft','paid') NOT NULL DEFAULT 'draft',
  paid_at DATETIME NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll (employee_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- تنظیمات / امنیت API ----------
CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) NOT NULL PRIMARY KEY,
  `value` LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_keys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  key_prefix VARCHAR(16) NOT NULL,
  key_hash CHAR(64) NOT NULL UNIQUE,
  scopes VARCHAR(255) NOT NULL DEFAULT '*',
  last_used_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- همگام‌سازی (افزونه + نرم‌افزار ویندوزی) ----------
CREATE TABLE IF NOT EXISTS sync_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  direction VARCHAR(10) NOT NULL DEFAULT 'in',
  entity_type VARCHAR(40) NULL,
  entity_ref VARCHAR(80) NULL,
  action VARCHAR(30) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ok',
  message TEXT NULL,
  payload LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (created_at), INDEX (entity_type), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(40) NOT NULL,
  payload LONGTEXT NULL,
  status ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status), INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abandoned_carts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id VARCHAR(80) NOT NULL UNIQUE,
  customer_name VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  wp_user_id BIGINT UNSIGNED NULL,
  wp_user_login VARCHAR(60) NULL,
  wp_roles VARCHAR(190) NULL,
  items LONGTEXT NULL,
  items_count INT UNSIGNED NOT NULL DEFAULT 0,
  cart_total DECIMAL(18,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'IRT',
  status ENUM('open','abandoned','converted','recovered','canceled') NOT NULL DEFAULT 'open',
  abandoned_at DATETIME NULL,
  converted_wc_order_id BIGINT UNSIGNED NULL,
  converted_at DATETIME NULL,
  last_activity DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX (status), INDEX (abandoned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  type VARCHAR(30) NOT NULL DEFAULT 'info',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  داده‌های اولیه
-- ============================================================
INSERT INTO roles (name, slug, permissions, is_system) VALUES
('مدیر کل', 'admin', '*', 1),
('حسابدار', 'accountant', '["dashboard.view","accounting.view","accounting.edit","accounting.manage","treasury.view","treasury.edit","reports.view","customers.view","customers.edit","invoices.view","invoices.edit","payroll.view","payroll.edit"]', 1),
('فروش', 'sales', '["dashboard.view","customers.view","customers.edit","invoices.view","invoices.edit","products.view","reports.view"]', 1),
('انباردار', 'warehouse', '["dashboard.view","products.view","products.edit","stock.view","stock.edit","reports.view"]', 1),
('مشاهده‌گر', 'viewer', '["dashboard.view","reports.view","customers.view","invoices.view","products.view","stock.view","accounting.view","treasury.view"]', 1);

INSERT INTO warehouses (name, location, is_default) VALUES
('انبار مرکزی', NULL, 1);

INSERT INTO cashboxes (name, type, balance, is_active) VALUES
('صندوق اصلی', 'cash', 0, 1);

INSERT INTO product_categories (name) VALUES ('عمومی');

-- درخت حساب‌ها (شناسه‌ها ثابت و شناخته‌شده در تنظیمات)
INSERT INTO accounts (id, code, name, type, parent_id, is_group) VALUES
(1,  '1000', 'دارایی‌ها', 'asset', NULL, 1),
(2,  '1100', 'دارایی‌های جاری', 'asset', 1, 1),
(3,  '1101', 'صندوق', 'asset', 2, 0),
(4,  '1102', 'بانک', 'asset', 2, 0),
(5,  '1103', 'حساب‌های دریافتنی', 'asset', 2, 0),
(6,  '1104', 'موجودی کالا', 'asset', 2, 0),
(7,  '1105', 'اسناد دریافتنی (چک)', 'asset', 2, 0),
(8,  '2000', 'بدهی‌ها', 'liability', NULL, 1),
(9,  '2100', 'بدهی‌های جاری', 'liability', 8, 1),
(10, '2101', 'حساب‌های پرداختنی', 'liability', 9, 0),
(11, '2102', 'مالیات بر ارزش افزوده', 'liability', 9, 0),
(12, '2103', 'حقوق پرداختنی', 'liability', 9, 0),
(13, '2104', 'اسناد پرداختنی (چک)', 'liability', 9, 0),
(14, '3000', 'سرمایه', 'equity', NULL, 1),
(15, '3101', 'سرمایه اولیه', 'equity', 14, 0),
(16, '4000', 'درآمدها', 'income', NULL, 1),
(17, '4101', 'فروش کالا', 'income', 16, 0),
(18, '4102', 'درآمد خدمات', 'income', 16, 0),
(19, '5000', 'هزینه‌ها', 'expense', NULL, 1),
(20, '5101', 'بهای تمام‌شده کالای فروش‌رفته', 'expense', 19, 0),
(21, '5102', 'هزینه حمل و نقل', 'expense', 19, 0),
(22, '5103', 'هزینه‌های عمومی و اداری', 'expense', 19, 0),
(23, '5104', 'حقوق و دستمزد', 'expense', 19, 0),
(24, '5105', 'تخفیفات فروش', 'expense', 19, 0);

INSERT INTO settings (`key`, `value`) VALUES
('company_name', 'شرکت من'),
('company_phone', ''),
('company_address', ''),
('company_economic_code', ''),
('currency', 'تومان'),
('tax_rate', '9'),
('invoice_prefix', 'S'),
('purchase_prefix', 'P'),
('invoice_counter', '0'),
('trx_prefix', 'T'),
('trx_counter', '0'),
('voucher_prefix', 'V'),
('voucher_counter', '0'),
('account_ar', '5'),
('account_ap', '10'),
('account_sales', '17'),
('account_inventory', '6'),
('account_cash', '3'),
('account_bank', '4'),
('account_vat', '11'),
('account_cogs', '20'),
('account_discount', '24'),
('role_map', '{"administrator":"admin","shop_manager":"sales","editor":"viewer","author":"viewer","contributor":"viewer","subscriber":null,"customer":null}'),
('db_version', '1.0.0'),
('sync_enabled', '1');

SET FOREIGN_KEY_CHECKS = 1;
