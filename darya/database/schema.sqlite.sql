-- ============================================================
--  داریا (Darya) v1.0.0 — سامانه مدیریت و حسابداری
--  SQLite 3.8+ — نسخه دسکتاپ ویندوزی (وب: MySQL)
-- ============================================================

-- ---------- کاربران و نقش‌ها ----------
CREATE TABLE IF NOT EXISTS roles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  permissions TEXT NULL,
  is_system INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  full_name TEXT NOT NULL,
  email TEXT NULL,
  role_id INTEGER NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  last_login_at TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

-- ---------- مشتریان / کارمندان / مبلغان ----------
CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NULL UNIQUE,
  full_name TEXT NOT NULL,
  company TEXT NULL,
  type TEXT NOT NULL DEFAULT 'person',
  mobile TEXT NULL,
  phone TEXT NULL,
  email TEXT NULL,
  address TEXT NULL,
  city TEXT NULL,
  postal_code TEXT NULL,
  national_id TEXT NULL,
  economic_code TEXT NULL,
  credit_limit NUMERIC NOT NULL DEFAULT 0,
  balance NUMERIC NOT NULL DEFAULT 0,
  notes TEXT NULL,
  wp_user_id INTEGER NULL,
  wp_roles TEXT NULL,
  source TEXT NOT NULL DEFAULT 'manual',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NULL);

CREATE TABLE IF NOT EXISTS employees (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NULL UNIQUE,
  full_name TEXT NOT NULL,
  national_id TEXT NULL,
  mobile TEXT NULL,
  position TEXT NULL,
  department TEXT NULL,
  hire_date DATE NULL,
  base_salary NUMERIC NOT NULL DEFAULT 0,
  insurance_no TEXT NULL,
  bank_name TEXT NULL,
  bank_account TEXT NULL,
  status TEXT NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS promoters (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NULL UNIQUE,
  full_name TEXT NOT NULL,
  mobile TEXT NOT NULL,
  pin TEXT NULL,
  commission_rate NUMERIC NOT NULL DEFAULT 0,
  wp_user_id INTEGER NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

-- ---------- کالا و انبار ----------
CREATE TABLE IF NOT EXISTS product_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  parent_id INTEGER NULL
);

CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sku TEXT NULL UNIQUE,
  name TEXT NOT NULL,
  category_id INTEGER NULL,
  unit TEXT NOT NULL DEFAULT 'عدد',
  purchase_price NUMERIC NOT NULL DEFAULT 0,
  sale_price NUMERIC NOT NULL DEFAULT 0,
  min_stock NUMERIC NOT NULL DEFAULT 0,
  stock_qty NUMERIC NOT NULL DEFAULT 0,
  description TEXT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  wc_product_id INTEGER NULL,
  source TEXT NOT NULL DEFAULT 'manual',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NULL);

CREATE TABLE IF NOT EXISTS warehouses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  location TEXT NULL,
  is_default INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS stock_movements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL,
  warehouse_id INTEGER NOT NULL DEFAULT 1,
  movement_type TEXT NOT NULL,
  qty NUMERIC NOT NULL,
  unit_cost NUMERIC NOT NULL DEFAULT 0,
  ref_type TEXT NULL,
  ref_id INTEGER NULL,
  description TEXT NULL,
  user_id INTEGER NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

-- ---------- فروش و خرید ----------
CREATE TABLE IF NOT EXISTS invoices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  invoice_no TEXT NOT NULL UNIQUE,
  invoice_type TEXT NOT NULL DEFAULT 'sale',
  customer_id INTEGER NULL,
  supplier_name TEXT NULL,
  employee_id INTEGER NULL,
  promoter_id INTEGER NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NULL,
  subtotal NUMERIC NOT NULL DEFAULT 0,
  discount NUMERIC NOT NULL DEFAULT 0,
  tax_rate NUMERIC NOT NULL DEFAULT 0,
  tax_amount NUMERIC NOT NULL DEFAULT 0,
  shipping NUMERIC NOT NULL DEFAULT 0,
  total NUMERIC NOT NULL DEFAULT 0,
  paid_amount NUMERIC NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'draft',
  payment_method TEXT NULL,
  description TEXT NULL,
  source TEXT NOT NULL DEFAULT 'manual',
  wc_order_id INTEGER NULL,
  wc_order_no TEXT NULL,
  created_by INTEGER NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NULL);

CREATE TABLE IF NOT EXISTS invoice_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  invoice_id INTEGER NOT NULL,
  product_id INTEGER NULL,
  description TEXT NULL,
  qty NUMERIC NOT NULL DEFAULT 1,
  unit_price NUMERIC NOT NULL DEFAULT 0,
  discount NUMERIC NOT NULL DEFAULT 0,
  total NUMERIC NOT NULL DEFAULT 0);

-- ---------- خزانه: صندوق، بانک، تراکنش، چک ----------
CREATE TABLE IF NOT EXISTS cashboxes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  type TEXT NOT NULL DEFAULT 'cash',
  bank_name TEXT NULL,
  account_number TEXT NULL,
  iban TEXT NULL,
  card_number TEXT NULL,
  balance NUMERIC NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  trx_no TEXT NULL,
  type TEXT NOT NULL,
  party_type TEXT NOT NULL DEFAULT 'customer',
  party_id INTEGER NULL,
  cashbox_id INTEGER NOT NULL,
  method TEXT NOT NULL DEFAULT 'cash',
  amount NUMERIC NOT NULL,
  trx_date DATE NOT NULL,
  invoice_id INTEGER NULL,
  description TEXT NULL,
  created_by INTEGER NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS cheques (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  direction TEXT NOT NULL,
  cheque_no TEXT NOT NULL,
  bank_name TEXT NULL,
  due_date DATE NOT NULL,
  amount NUMERIC NOT NULL,
  party_type TEXT NULL,
  party_id INTEGER NULL,
  cashbox_id INTEGER NULL,
  invoice_id INTEGER NULL,
  status TEXT NOT NULL DEFAULT 'in_hand',
  description TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

-- ---------- حسابداری ----------
CREATE TABLE IF NOT EXISTS accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  type TEXT NOT NULL,
  parent_id INTEGER NULL,
  is_group INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS vouchers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  voucher_no TEXT NOT NULL UNIQUE,
  voucher_date DATE NOT NULL,
  description TEXT NULL,
  status TEXT NOT NULL DEFAULT 'posted',
  source TEXT NOT NULL DEFAULT 'manual',
  ref_type TEXT NULL,
  ref_id INTEGER NULL,
  created_by INTEGER NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS voucher_lines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  voucher_id INTEGER NOT NULL,
  account_id INTEGER NOT NULL,
  debit NUMERIC NOT NULL DEFAULT 0,
  credit NUMERIC NOT NULL DEFAULT 0,
  description TEXT NULL);

-- ---------- حقوق و دستمزد ----------
CREATE TABLE IF NOT EXISTS payrolls (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  employee_id INTEGER NOT NULL,
  period_year INTEGER NOT NULL,
  period_month INTEGER NOT NULL,
  base_salary NUMERIC NOT NULL DEFAULT 0,
  overtime NUMERIC NOT NULL DEFAULT 0,
  bonus NUMERIC NOT NULL DEFAULT 0,
  insurance_deduction NUMERIC NOT NULL DEFAULT 0,
  tax_deduction NUMERIC NOT NULL DEFAULT 0,
  other_deduction NUMERIC NOT NULL DEFAULT 0,
  net_pay NUMERIC NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'draft',
  paid_at TEXT NULL,
  description TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (employee_id, period_year, period_month)
);

-- ---------- تنظیمات / امنیت API ----------
CREATE TABLE IF NOT EXISTS settings (
  `key` TEXT NOT NULL PRIMARY KEY,
  `value` TEXT NULL
);

CREATE TABLE IF NOT EXISTS api_keys (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  key_prefix TEXT NOT NULL,
  key_hash CHAR(64) NOT NULL UNIQUE,
  scopes TEXT NOT NULL DEFAULT '*',
  last_used_at TEXT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------- همگام‌سازی (افزونه + نرم‌افزار ویندوزی) ----------
CREATE TABLE IF NOT EXISTS sync_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  direction TEXT NOT NULL DEFAULT 'in',
  entity_type TEXT NULL,
  entity_ref TEXT NULL,
  action TEXT NULL,
  status TEXT NOT NULL DEFAULT 'ok',
  message TEXT NULL,
  payload TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS sync_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event_type TEXT NOT NULL,
  payload TEXT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  processed_at TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS abandoned_carts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  cart_id TEXT NOT NULL UNIQUE,
  customer_name TEXT NULL,
  phone TEXT NULL,
  email TEXT NULL,
  wp_user_id INTEGER NULL,
  wp_user_login TEXT NULL,
  wp_roles TEXT NULL,
  items TEXT NULL,
  items_count INTEGER NOT NULL DEFAULT 0,
  cart_total NUMERIC NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'IRT',
  status TEXT NOT NULL DEFAULT 'open',
  abandoned_at TEXT NULL,
  converted_wc_order_id INTEGER NULL,
  converted_at TEXT NULL,
  last_activity TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NULL);

CREATE TABLE IF NOT EXISTS notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  body TEXT NULL,
  type TEXT NOT NULL DEFAULT 'info',
  is_read INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

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

