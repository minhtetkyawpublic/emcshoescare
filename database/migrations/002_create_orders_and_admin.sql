USE emc_shoes_care;

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admins_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL,
  token_hash BINARY(32) NOT NULL,
  csrf_token_hash BINARY(32) NOT NULL,
  user_agent_hash BINARY(32) NULL,
  ip_address VARBINARY(16) NULL,
  expires_at DATETIME NOT NULL,
  last_rotated_at DATETIME NOT NULL,
  last_used_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admin_sessions_token_unique (token_hash),
  KEY admin_sessions_admin_index (admin_id),
  KEY admin_sessions_expiry_index (expires_at),
  CONSTRAINT admin_sessions_admin_fk
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  name_en VARCHAR(120) NOT NULL,
  name_mm VARCHAR(180) NOT NULL,
  description_en VARCHAR(500) NOT NULL DEFAULT '',
  description_mm VARCHAR(800) NOT NULL DEFAULT '',
  price_ks BIGINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY packages_slug_unique (slug),
  KEY packages_public_index (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_settings (
  setting_key VARCHAR(80) NOT NULL,
  setting_value VARCHAR(500) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(24) NOT NULL,
  storage_key CHAR(32) NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,
  package_id BIGINT UNSIGNED NOT NULL,
  package_name_en VARCHAR(120) NOT NULL,
  package_name_mm VARCHAR(180) NOT NULL,
  package_price_ks BIGINT UNSIGNED NOT NULL,
  pickup_fee_ks BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_price_ks BIGINT UNSIGNED NOT NULL,
  fulfillment_method VARCHAR(20) NOT NULL,
  customer_name VARCHAR(120) NOT NULL,
  customer_phone VARCHAR(20) NOT NULL,
  customer_address VARCHAR(500) NOT NULL,
  customer_notes TEXT NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'submitted',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY orders_number_unique (order_number),
  UNIQUE KEY orders_storage_unique (storage_key),
  KEY orders_customer_index (customer_id, created_at),
  KEY orders_status_index (status, created_at),
  CONSTRAINT orders_customer_fk
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT orders_package_fk
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_photos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  storage_name VARCHAR(100) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(40) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  width_px INT UNSIGNED NOT NULL,
  height_px INT UNSIGNED NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY order_photos_storage_unique (order_id, storage_name),
  KEY order_photos_order_index (order_id, sort_order, id),
  CONSTRAINT order_photos_order_fk
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO packages
  (slug, name_en, name_mm, description_en, description_mm, price_ks, is_active, sort_order)
VALUES
  ('essential-clean', 'Essential Clean', 'အခြေခံသန့်ရှင်းရေး', 'A careful refresh for everyday shoes.', 'နေ့စဉ်စီးဖိနပ်များအတွက် ဂရုတစိုက် သန့်ရှင်းပေးခြင်း။', 15000, 1, 10),
  ('premium-care', 'Premium Care', 'အထူးဂရုစိုက်မှု', 'Deeper care for pairs that need extra attention.', 'ပိုမိုဂရုစိုက်ရန်လိုသော ဖိနပ်များအတွက် အထူးဝန်ဆောင်မှု။', 25000, 1, 20),
  ('full-restore', 'Full Restore', 'အပြည့်အစုံ ပြန်လည်ပြုပြင်ခြင်း', 'Complete care for worn and well-loved shoes.', 'စီးထားပြီး ပျက်စီးနေသောဖိနပ်များအတွက် အပြည့်အစုံဂရုစိုက်မှု။', 45000, 1, 30);

INSERT IGNORE INTO shop_settings (setting_key, setting_value) VALUES ('pickup_fee_ks', '0');
INSERT IGNORE INTO schema_migrations (version) VALUES ('002_create_orders_and_admin');
