-- Point of Sale module migration

CREATE TABLE IF NOT EXISTS pos_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_quantity INT NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pos_products_category (category),
    INDEX idx_pos_products_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(100) NOT NULL UNIQUE,
    customer_type VARCHAR(30) NOT NULL DEFAULT 'walk_in',
    customer_id INT DEFAULT NULL,
    customer_name VARCHAR(180) DEFAULT NULL,
    sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_sales_invoice (invoice_no),
    INDEX idx_pos_sales_date (sale_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pos_sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_sale_items_sale (sale_id),
    INDEX idx_pos_sale_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS product_name VARCHAR(255) DEFAULT NULL;
ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS name VARCHAR(255) DEFAULT NULL;
ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS stock_quantity INT NOT NULL DEFAULT 0;
ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS stock INT NOT NULL DEFAULT 0;
ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active';
ALTER TABLE pos_products ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS invoice_no VARCHAR(100) DEFAULT NULL;
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS customer_type VARCHAR(30) NOT NULL DEFAULT 'walk_in';
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL;
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS customer_name VARCHAR(180) DEFAULT NULL;
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS discount DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS balance DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE pos_sales ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL;

UPDATE pos_products SET product_name = name WHERE (product_name IS NULL OR product_name = '') AND name IS NOT NULL;
UPDATE pos_products SET name = product_name WHERE (name IS NULL OR name = '') AND product_name IS NOT NULL;
UPDATE pos_products SET sale_price = price WHERE sale_price = 0 AND price > 0;
UPDATE pos_products SET price = sale_price WHERE price = 0 AND sale_price > 0;
UPDATE pos_products SET stock_quantity = stock WHERE stock_quantity = 0 AND stock > 0;
UPDATE pos_products SET stock = stock_quantity WHERE stock = 0 AND stock_quantity > 0;
UPDATE pos_products SET status = CASE WHEN COALESCE(is_active, 1) = 1 THEN 'active' ELSE 'inactive' END WHERE status IS NULL OR status = '';
UPDATE pos_sales SET sale_date = created_at WHERE sale_date IS NULL AND created_at IS NOT NULL;
UPDATE pos_sales SET invoice_no = CONCAT('POS-', DATE_FORMAT(COALESCE(sale_date, created_at, NOW()), '%Y%m%d'), '-', LPAD(id, 5, '0')) WHERE invoice_no IS NULL OR invoice_no = '';
