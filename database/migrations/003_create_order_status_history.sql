USE emc_shoes_care;

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS customer_seen_at DATETIME(6) NULL AFTER status;

CREATE TABLE IF NOT EXISTS order_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NOT NULL,
  note_en VARCHAR(1000) NOT NULL DEFAULT '',
  note_mm VARCHAR(1500) NOT NULL DEFAULT '',
  changed_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY order_status_history_order_index (order_id, created_at, id),
  KEY order_status_history_admin_index (changed_by_admin_id),
  CONSTRAINT order_status_history_order_fk
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT order_status_history_admin_fk
    FOREIGN KEY (changed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO order_status_history
  (order_id, from_status, to_status, note_en, note_mm, changed_by_admin_id, created_at)
SELECT
  o.id,
  NULL,
  o.status,
  'Order submitted to EMC.',
  'အော်ဒါကို EMC သို့ တင်ပြီးပါပြီ။',
  NULL,
  o.created_at
FROM orders o
WHERE NOT EXISTS (
  SELECT 1 FROM order_status_history h WHERE h.order_id = o.id
);

UPDATE orders
SET customer_seen_at = COALESCE(customer_seen_at, created_at)
WHERE customer_seen_at IS NULL;

INSERT IGNORE INTO schema_migrations (version) VALUES ('003_create_order_status_history');
