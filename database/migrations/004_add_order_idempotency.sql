USE emc_shoes_care;

SET @has_client_request_id = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'client_request_id'
);
SET @add_client_request_id = IF(
  @has_client_request_id = 0,
  'ALTER TABLE orders ADD COLUMN client_request_id CHAR(36) NULL AFTER order_number',
  'SELECT 1'
);
PREPARE add_client_request_id_statement FROM @add_client_request_id;
EXECUTE add_client_request_id_statement;
DEALLOCATE PREPARE add_client_request_id_statement;

SET @has_request_index = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'orders_customer_request_unique'
);
SET @add_request_index = IF(
  @has_request_index = 0,
  'ALTER TABLE orders ADD UNIQUE INDEX orders_customer_request_unique (customer_id, client_request_id)',
  'SELECT 1'
);
PREPARE add_request_index_statement FROM @add_request_index;
EXECUTE add_request_index_statement;
DEALLOCATE PREPARE add_request_index_statement;

INSERT IGNORE INTO schema_migrations (version) VALUES ('004_add_order_idempotency');
