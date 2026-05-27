<?php
// File: modules/pos/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}
requireRole(['admin', 'owner']);

$db = (new Database())->getConnection();
$userId = getUserId();
$categories = ['Cafeteria', 'Uniform', 'Stationery', 'Books', 'Forms', 'Other'];

function pos_h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function pos_money($amount): string {
    return 'PKR ' . number_format((float)$amount, 2);
}

function pos_positive_number($value): float {
    return is_numeric($value) ? max(0, (float)$value) : 0.0;
}

function pos_ensure_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS pos_products (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS pos_sales (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS pos_sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pos_sale_items_sale (sale_id),
        INDEX idx_pos_sale_items_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $productColumns = [
        'product_name' => "ALTER TABLE pos_products ADD COLUMN product_name VARCHAR(255) DEFAULT NULL",
        'name' => "ALTER TABLE pos_products ADD COLUMN name VARCHAR(255) DEFAULT NULL",
        'purchase_price' => "ALTER TABLE pos_products ADD COLUMN purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'sale_price' => "ALTER TABLE pos_products ADD COLUMN sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'price' => "ALTER TABLE pos_products ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'stock_quantity' => "ALTER TABLE pos_products ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0",
        'stock' => "ALTER TABLE pos_products ADD COLUMN stock INT NOT NULL DEFAULT 0",
        'status' => "ALTER TABLE pos_products ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'",
        'is_active' => "ALTER TABLE pos_products ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        'updated_at' => "ALTER TABLE pos_products ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
    ];
    foreach ($productColumns as $column => $sql) {
        if (!columnExists($db, 'pos_products', $column)) {
            $db->exec($sql);
        }
    }
    $db->exec("UPDATE pos_products SET product_name = name WHERE (product_name IS NULL OR product_name = '') AND name IS NOT NULL");
    $db->exec("UPDATE pos_products SET name = product_name WHERE (name IS NULL OR name = '') AND product_name IS NOT NULL");
    $db->exec("UPDATE pos_products SET sale_price = price WHERE sale_price = 0 AND price > 0");
    $db->exec("UPDATE pos_products SET price = sale_price WHERE price = 0 AND sale_price > 0");
    $db->exec("UPDATE pos_products SET stock_quantity = stock WHERE stock_quantity = 0 AND stock > 0");
    $db->exec("UPDATE pos_products SET stock = stock_quantity WHERE stock = 0 AND stock_quantity > 0");
    $db->exec("UPDATE pos_products SET status = CASE WHEN COALESCE(is_active, 1) = 1 THEN 'active' ELSE 'inactive' END WHERE status IS NULL OR status = ''");

    $saleColumns = [
        'invoice_no' => "ALTER TABLE pos_sales ADD COLUMN invoice_no VARCHAR(100) DEFAULT NULL",
        'customer_type' => "ALTER TABLE pos_sales ADD COLUMN customer_type VARCHAR(30) NOT NULL DEFAULT 'walk_in'",
        'customer_id' => "ALTER TABLE pos_sales ADD COLUMN customer_id INT DEFAULT NULL",
        'customer_name' => "ALTER TABLE pos_sales ADD COLUMN customer_name VARCHAR(180) DEFAULT NULL",
        'sale_date' => "ALTER TABLE pos_sales ADD COLUMN sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'total_amount' => "ALTER TABLE pos_sales ADD COLUMN total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'discount' => "ALTER TABLE pos_sales ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'paid_amount' => "ALTER TABLE pos_sales ADD COLUMN paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'balance' => "ALTER TABLE pos_sales ADD COLUMN balance DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'created_by' => "ALTER TABLE pos_sales ADD COLUMN created_by INT DEFAULT NULL",
        'created_at' => "ALTER TABLE pos_sales ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];
    foreach ($saleColumns as $column => $sql) {
        if (!columnExists($db, 'pos_sales', $column)) {
            $db->exec($sql);
        }
    }
    $db->exec("UPDATE pos_sales SET sale_date = created_at WHERE sale_date IS NULL AND created_at IS NOT NULL");
    $db->exec("UPDATE pos_sales SET customer_name = student_name WHERE (customer_name IS NULL OR customer_name = '') AND " . (columnExists($db, 'pos_sales', 'student_name') ? 'student_name IS NOT NULL' : '1=0'));
    $db->exec("UPDATE pos_sales SET invoice_no = CONCAT('POS-', DATE_FORMAT(COALESCE(sale_date, created_at, NOW()), '%Y%m%d'), '-', LPAD(id, 5, '0')) WHERE invoice_no IS NULL OR invoice_no = ''");
}

function pos_invoice_no(PDO $db): string {
    do {
        $invoice = 'POS-' . date('Ymd') . '-' . random_int(10000, 99999);
        $stmt = $db->prepare('SELECT COUNT(*) FROM pos_sales WHERE invoice_no = ?');
        $stmt->execute([$invoice]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $invoice;
}

pos_ensure_schema($db);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        requireCsrfToken();
        $action = $_POST['action'] ?? '';

        if (in_array($action, ['add_product', 'edit_product'], true)) {
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitizeInput($_POST['product_name'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $purchase = pos_positive_number($_POST['purchase_price'] ?? 0);
            $sale = pos_positive_number($_POST['sale_price'] ?? 0);
            $stock = (int)($_POST['stock_quantity'] ?? 0);
            $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

            if ($name === '' || $category === '' || $sale <= 0 || $stock < 0) {
                throw new Exception('Please enter valid product name, category, sale price, and stock.');
            }

            if ($action === 'add_product') {
                $stmt = $db->prepare("INSERT INTO pos_products (product_name, name, category, purchase_price, sale_price, price, stock_quantity, stock, status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $name, $category, $purchase, $sale, $sale, $stock, $stock, $status, $status === 'active' ? 1 : 0]);
                setFlashMessage('success', 'Product added successfully.');
            } else {
                if ($id <= 0) {
                    throw new Exception('Invalid product selected.');
                }
                $stmt = $db->prepare("UPDATE pos_products SET product_name = ?, name = ?, category = ?, purchase_price = ?, sale_price = ?, price = ?, stock_quantity = ?, stock = ?, status = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $name, $category, $purchase, $sale, $sale, $stock, $stock, $status, $status === 'active' ? 1 : 0, $id]);
                setFlashMessage('success', 'Product updated successfully.');
            }
            redirect('index.php');
        }

        if ($action === 'delete_product') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE pos_products SET status = 'inactive', is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            setFlashMessage('success', 'Product marked inactive.');
            redirect('index.php');
        }

        if ($action === 'process_sale') {
            $customerType = in_array($_POST['customer_type'] ?? 'walk_in', ['student', 'staff', 'walk_in'], true) ? $_POST['customer_type'] : 'walk_in';
            $customerId = (int)($_POST['customer_id'] ?? 0) ?: null;
            $customerName = sanitizeInput($_POST['customer_name'] ?? 'Walk-in');
            $discount = pos_positive_number($_POST['discount'] ?? 0);
            $paidAmount = pos_positive_number($_POST['paid_amount'] ?? 0);
            $items = json_decode((string)($_POST['sale_items'] ?? '[]'), true);
            if (!is_array($items) || !$items) {
                throw new Exception('Please add at least one item to the invoice.');
            }

            $db->beginTransaction();
            $saleRows = [];
            $total = 0.0;
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    throw new Exception('Invalid sale item quantity.');
                }
                $stmt = $db->prepare("SELECT id, COALESCE(NULLIF(product_name, ''), name) AS product_name, sale_price, stock_quantity, status FROM pos_products WHERE id = ? FOR UPDATE");
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product || strtolower((string)$product['status']) !== 'active') {
                    throw new Exception('One selected product is unavailable.');
                }
                if ((int)$product['stock_quantity'] < $qty) {
                    throw new Exception($product['product_name'] . ' has insufficient stock.');
                }
                $unit = (float)$product['sale_price'];
                $subtotal = $unit * $qty;
                $saleRows[] = ['product_id' => $productId, 'quantity' => $qty, 'unit_price' => $unit, 'subtotal' => $subtotal];
                $total += $subtotal;
            }

            $netTotal = max(0, $total - $discount);
            $balance = max(0, $netTotal - $paidAmount);
            $invoice = pos_invoice_no($db);
            $stmt = $db->prepare("INSERT INTO pos_sales (invoice_no, customer_type, customer_id, customer_name, sale_date, total_amount, discount, paid_amount, balance, created_by) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)");
            $stmt->execute([$invoice, $customerType, $customerId, $customerName, $netTotal, $discount, $paidAmount, $balance, $userId]);
            $saleId = (int)$db->lastInsertId();

            $itemStmt = $db->prepare("INSERT INTO pos_sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stockStmt = $db->prepare("UPDATE pos_products SET stock_quantity = stock_quantity - ?, stock = stock - ? WHERE id = ?");
            foreach ($saleRows as $row) {
                $itemStmt->execute([$saleId, $row['product_id'], $row['quantity'], $row['unit_price'], $row['subtotal']]);
                $stockStmt->execute([$row['quantity'], $row['quantity'], $row['product_id']]);
            }
            $db->commit();
            setFlashMessage('success', 'Sale invoice generated: ' . $invoice);
            redirect('index.php?invoice_id=' . $saleId);
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlashMessage('error', $e->getMessage());
        redirect('index.php');
    }
}

$products = $db->query("SELECT *, COALESCE(NULLIF(product_name, ''), name) AS display_name FROM pos_products WHERE LOWER(status) = 'active' AND COALESCE(is_active, 1) = 1 ORDER BY category ASC, display_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$allProducts = $db->query("SELECT *, COALESCE(NULLIF(product_name, ''), name) AS display_name FROM pos_products ORDER BY category ASC, display_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$dailySales = $db->query("SELECT * FROM pos_sales WHERE DATE(sale_date) = CURDATE() ORDER BY sale_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$dailyTotal = array_sum(array_map(static fn($s) => (float)$s['total_amount'], $dailySales));
$dailyPaid = array_sum(array_map(static fn($s) => (float)$s['paid_amount'], $dailySales));

$invoice = null;
$invoiceItems = [];
if (isset($_GET['invoice_id'])) {
    $stmt = $db->prepare('SELECT * FROM pos_sales WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_GET['invoice_id']]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($invoice) {
        $stmt = $db->prepare("SELECT si.*, COALESCE(NULLIF(p.product_name, ''), p.name) AS product_name FROM pos_sale_items si JOIN pos_products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id ASC");
        $stmt->execute([(int)$invoice['id']]);
        $invoiceItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = 'Point of Sale';
include '../../includes/header.php';
?>

<div class="container-fluid pos-module">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4 no-print">
        <div>
            <a href="../../dashboard.php" class="btn btn-sm btn-light border rounded-pill mb-3"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            <h2 class="page-title mb-1"><i class="fas fa-cash-register me-2" style="color:var(--teal);"></i>Point of Sale</h2>
            <div class="text-muted">Sell school items, generate invoices, and track daily sales.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <div class="bg-white px-3 py-2 rounded border"><span class="text-muted small">Today Sales</span><div class="fw-bold"><?= pos_money($dailyTotal) ?></div></div>
            <div class="bg-white px-3 py-2 rounded border"><span class="text-muted small">Today Paid</span><div class="fw-bold text-success"><?= pos_money($dailyPaid) ?></div></div>
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetProductForm()"><i class="fas fa-box me-1"></i>Add Product</button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <div class="row g-4 no-print">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="fw-bold mb-0">Products</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($products as $product): ?>
                            <div class="col-xl-3 col-md-4">
                                <button type="button" class="product-tile w-100 text-start" onclick='addToCart(<?= json_encode([
                                    'id' => (int)$product['id'],
                                    'name' => $product['display_name'],
                                    'price' => (float)$product['sale_price'],
                                    'stock' => (int)$product['stock_quantity'],
                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                                    <div class="fw-bold"><?= pos_h($product['display_name']) ?></div>
                                    <div class="small text-muted"><?= pos_h($product['category']) ?></div>
                                    <div class="mt-2 d-flex justify-content-between"><span><?= pos_money($product['sale_price']) ?></span><span class="badge bg-light text-dark border">Stock <?= (int)$product['stock_quantity'] ?></span></div>
                                </button>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$products): ?><div class="col-12 text-center text-muted py-4">No active products found.</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <form class="card border-0 shadow-sm sticky-top" style="top:90px" method="POST" onsubmit="return prepareSale();">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="process_sale">
                <input type="hidden" name="sale_items" id="sale_items">
                <div class="card-header bg-white"><h5 class="fw-bold mb-0">New Invoice</h5></div>
                <div class="card-body">
                    <div id="cartList" class="mb-3"><div class="text-center text-muted py-4">Select products to begin.</div></div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label class="form-label small fw-bold">Customer Type</label><select name="customer_type" class="form-select"><option value="student">Student</option><option value="staff">Staff</option><option value="walk_in">Walk-in</option></select></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Customer ID</label><input type="number" name="customer_id" class="form-control" min="0"></div>
                        <div class="col-12"><label class="form-label small fw-bold">Customer Name</label><input type="text" name="customer_name" class="form-control" value="Walk-in"></div>
                    </div>
                    <div class="d-flex justify-content-between"><span>Subtotal</span><strong id="subtotalText">PKR 0.00</strong></div>
                    <div class="row g-2 my-2">
                        <div class="col-6"><label class="form-label small">Discount</label><input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control" value="0" oninput="renderCart()"></div>
                        <div class="col-6"><label class="form-label small">Paid</label><input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" class="form-control" value="0" oninput="renderCart()"></div>
                    </div>
                    <div class="d-flex justify-content-between fs-5"><span>Total</span><strong id="totalText" class="text-primary">PKR 0.00</strong></div>
                    <div class="d-flex justify-content-between"><span>Balance</span><strong id="balanceText" class="text-danger">PKR 0.00</strong></div>
                </div>
                <div class="card-footer bg-white"><button class="btn btn-success w-100" id="checkoutBtn" disabled><i class="fas fa-receipt me-1"></i>Generate Invoice</button></div>
            </form>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="fw-bold mb-0">Products / Stock</h5></div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>Product</th><th>Category</th><th>Purchase</th><th>Sale</th><th>Stock</th><th>Status</th><th class="text-end no-print">Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($allProducts as $product): ?>
                                <tr>
                                    <td><?= pos_h($product['display_name']) ?></td><td><?= pos_h($product['category']) ?></td><td><?= pos_money($product['purchase_price']) ?></td><td><?= pos_money($product['sale_price']) ?></td><td><?= (int)$product['stock_quantity'] ?></td><td><span class="badge <?= strtolower((string)$product['status']) === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= pos_h(ucfirst((string)$product['status'])) ?></span></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-outline-primary" onclick='editProduct(<?= json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' data-bs-toggle="modal" data-bs-target="#productModal"><i class="fas fa-pen"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Mark this product inactive?');"><?= csrfTokenInput() ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= (int)$product['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="fw-bold mb-0">Daily Sales Report</h5></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light"><tr><th>Invoice</th><th>Customer</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
                        <tbody>
                            <?php if (!$dailySales): ?><tr><td colspan="4" class="text-center text-muted py-4">No sales today.</td></tr><?php endif; ?>
                            <?php foreach ($dailySales as $sale): ?><tr><td><a href="index.php?invoice_id=<?= (int)$sale['id'] ?>"><?= pos_h($sale['invoice_no']) ?></a><div class="small text-muted"><?= pos_h(date('h:i A', strtotime($sale['sale_date']))) ?></div></td><td><?= pos_h($sale['customer_name'] ?: ucfirst($sale['customer_type'])) ?></td><td class="text-end fw-bold"><?= pos_money($sale['total_amount']) ?></td><td class="text-end"><?= pos_money($sale['balance']) ?></td></tr><?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light"><tr><th colspan="2">Total</th><th class="text-end"><?= pos_money($dailyTotal) ?></th><th></th></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($invoice): ?>
        <div class="card border-0 shadow-sm mt-4 invoice-print" id="invoiceArea">
            <div class="card-body">
                <div class="text-center mb-3"><h4 class="fw-bold mb-0">Quaid-e-Azam Group of Colleges</h4><div>POS Sale Invoice</div></div>
                <div class="row mb-3"><div class="col-md-6"><strong>Invoice:</strong> <?= pos_h($invoice['invoice_no']) ?><br><strong>Customer:</strong> <?= pos_h($invoice['customer_name'] ?: ucfirst($invoice['customer_type'])) ?></div><div class="col-md-6 text-md-end"><strong>Date:</strong> <?= pos_h(date('d M Y h:i A', strtotime($invoice['sale_date']))) ?><br><strong>Type:</strong> <?= pos_h(ucfirst($invoice['customer_type'])) ?></div></div>
                <table class="table table-bordered"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Subtotal</th></tr></thead><tbody><?php foreach ($invoiceItems as $item): ?><tr><td><?= pos_h($item['product_name']) ?></td><td class="text-end"><?= (int)$item['quantity'] ?></td><td class="text-end"><?= pos_money($item['unit_price']) ?></td><td class="text-end"><?= pos_money($item['subtotal']) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="3" class="text-end">Discount</th><th class="text-end"><?= pos_money($invoice['discount']) ?></th></tr><tr><th colspan="3" class="text-end">Total</th><th class="text-end"><?= pos_money($invoice['total_amount']) ?></th></tr><tr><th colspan="3" class="text-end">Paid</th><th class="text-end"><?= pos_money($invoice['paid_amount']) ?></th></tr><tr><th colspan="3" class="text-end">Balance</th><th class="text-end"><?= pos_money($invoice['balance']) ?></th></tr></tfoot></table>
                <div class="text-center no-print"><button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print Invoice</button></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade no-print" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content border-0 shadow" method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" id="product_action" value="add_product">
            <input type="hidden" name="id" id="product_id">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title" id="productModalTitle">Add Product</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label class="form-label">Product Name *</label><input type="text" name="product_name" id="product_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Category *</label><select name="category" id="category" class="form-select" required><?php foreach ($categories as $category): ?><option value="<?= pos_h($category) ?>"><?= pos_h($category) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Purchase Price</label><input type="number" step="0.01" min="0" name="purchase_price" id="purchase_price" class="form-control" value="0"></div>
                <div class="col-md-4"><label class="form-label">Sale Price *</label><input type="number" step="0.01" min="0" name="sale_price" id="sale_price" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Stock Quantity *</label><input type="number" min="0" name="stock_quantity" id="stock_quantity" class="form-control" required></div>
                <div class="col-md-12"><label class="form-label">Status</label><select name="status" id="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            </div>
            <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Product</button></div>
        </form>
    </div>
</div>

<style>
    :root { --teal:#4ec2b5; --navy:#0f2d48; }
    .page-title { font-family:'Playfair Display',serif; font-weight:700; color:var(--navy); }
    .btn-primary { background:var(--teal); border-color:var(--teal); color:var(--navy); font-weight:600; }
    .product-tile { border:1px solid #e5e7eb; background:#fff; border-radius:8px; padding:1rem; min-height:120px; transition:.15s ease; }
    .product-tile:hover { border-color:var(--teal); box-shadow:0 8px 20px rgba(15,45,72,.08); }
    @media print { .no-print, #sidebar, .topbar, .sidebar-backdrop, .btn, form { display:none !important; } #content { margin-left:0 !important; width:100% !important; } body * { visibility:hidden; } .invoice-print, .invoice-print * { visibility:visible; } .invoice-print { position:absolute; left:0; top:0; width:100%; box-shadow:none !important; } }
</style>

<script>
let cart = [];
const fmt = n => 'PKR ' + Number(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
function addToCart(product) {
    const existing = cart.find(i => i.product_id === product.id);
    const currentQty = existing ? existing.quantity : 0;
    if (currentQty + 1 > product.stock) {
        alert('Stock not available for ' + product.name);
        return;
    }
    if (existing) existing.quantity += 1;
    else cart.push({product_id: product.id, name: product.name, unit_price: product.price, stock: product.stock, quantity: 1});
    renderCart();
}
function changeQty(id, delta) {
    const item = cart.find(i => i.product_id === id);
    if (!item) return;
    const next = item.quantity + delta;
    if (next <= 0) cart = cart.filter(i => i.product_id !== id);
    else if (next <= item.stock) item.quantity = next;
    else alert('Stock not available.');
    renderCart();
}
function renderCart() {
    const list = document.getElementById('cartList');
    const subtotal = cart.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);
    const discount = Number(document.getElementById('discount').value || 0);
    const paid = Number(document.getElementById('paid_amount').value || 0);
    const total = Math.max(0, subtotal - discount);
    const balance = Math.max(0, total - paid);
    if (!cart.length) list.innerHTML = '<div class="text-center text-muted py-4">Select products to begin.</div>';
    else list.innerHTML = cart.map(item => `<div class="d-flex justify-content-between align-items-center border-bottom py-2"><div><strong>${item.name}</strong><div class="small text-muted">${fmt(item.unit_price)} x ${item.quantity}</div></div><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" onclick="changeQty(${item.product_id},-1)">-</button><button type="button" class="btn btn-outline-secondary" onclick="changeQty(${item.product_id},1)">+</button></div></div>`).join('');
    document.getElementById('subtotalText').textContent = fmt(subtotal);
    document.getElementById('totalText').textContent = fmt(total);
    document.getElementById('balanceText').textContent = fmt(balance);
    document.getElementById('checkoutBtn').disabled = !cart.length;
}
function prepareSale() {
    if (!cart.length) return false;
    document.getElementById('sale_items').value = JSON.stringify(cart.map(item => ({product_id: item.product_id, quantity: item.quantity})));
    return true;
}
function resetProductForm() {
    document.getElementById('productModalTitle').textContent = 'Add Product';
    document.getElementById('product_action').value = 'add_product';
    document.getElementById('product_id').value = '';
    document.getElementById('product_name').value = '';
    document.getElementById('category').value = 'Cafeteria';
    document.getElementById('purchase_price').value = '0';
    document.getElementById('sale_price').value = '';
    document.getElementById('stock_quantity').value = '';
    document.getElementById('status').value = 'active';
}
function editProduct(product) {
    document.getElementById('productModalTitle').textContent = 'Edit Product';
    document.getElementById('product_action').value = 'edit_product';
    document.getElementById('product_id').value = product.id || '';
    document.getElementById('product_name').value = product.display_name || product.product_name || product.name || '';
    document.getElementById('category').value = product.category || 'Other';
    document.getElementById('purchase_price').value = product.purchase_price || 0;
    document.getElementById('sale_price').value = product.sale_price || product.price || 0;
    document.getElementById('stock_quantity').value = product.stock_quantity || product.stock || 0;
    document.getElementById('status').value = product.status || 'active';
}
</script>

<?php include '../../includes/footer.php'; ?>
