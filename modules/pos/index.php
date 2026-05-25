<?php
/**
 * File: modules/pos/index.php
 * Description: Complete Cafeteria & Uniform POS System
 */

require_once '../../config/db.php';

// Session Check
if (!isLoggedIn()) {
    redirect('../../modules/auth/login.php');
}
requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

// Handle Backend Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'process_sale') {
            $student_name = sanitizeInput($_POST['student_name'] ?? 'Walk-in');
            $class = sanitizeInput($_POST['class'] ?? 'N/A');
            $campus = sanitizeInput($_POST['campus'] ?? ($_SESSION['user_campus'] ?? 'Rajanpur'));
            $items = $_POST['items']; // JSON string
            $total_amount = (float)$_POST['total_amount'];
            $payment_method = $_POST['payment_method'] ?? 'Cash';
            $transaction_id = sanitizeInput($_POST['transaction_id'] ?? '');
            
            // Insert Sale
            $stmt = $db->prepare("INSERT INTO pos_sales (student_name, class, items, total_amount, payment_method, transaction_id, campus) 
                                  VALUES (:name, :class, :items, :total, :method, :tid, :campus)");
            $stmt->execute([
                ':name' => $student_name,
                ':class' => $class,
                ':items' => $items,
                ':total' => $total_amount,
                ':method' => $payment_method,
                ':tid' => $transaction_id,
                ':campus' => $campus
            ]);
            $saleId = (int)$db->lastInsertId();
            
            // Update Stock
            $cartItems = json_decode($items, true);
            foreach ($cartItems as $item) {
                $stmt = $db->prepare("UPDATE pos_products SET stock = stock - :qty WHERE id = :id");
                $stmt->execute([':qty' => $item['qty'], ':id' => $item['id']]);
            }
            
            recordIncome($db, [
                'source' => 'pos',
                'reference_id' => $saleId,
                'campus' => $campus,
                'description' => 'POS sale: ' . $student_name,
                'amount' => $total_amount
            ]);

            $message = "Sale processed successfully! Receipt ID: " . $saleId;
            $messageType = "success";
        } elseif ($action === 'add_product') {
            $stmt = $db->prepare("INSERT INTO pos_products (name, category, price, stock) VALUES (:name, :cat, :price, :stock)");
            $stmt->execute([
                ':name' => sanitizeInput($_POST['name']),
                ':cat' => $_POST['category'],
                ':price' => (float)$_POST['price'],
                ':stock' => (int)$_POST['stock']
            ]);
            $productId = (int)$db->lastInsertId();
            $stockCost = (float)($_POST['purchase_cost'] ?? 0);
            if ($stockCost > 0) {
                recordExpense($db, [
                    'module_name' => 'pos_stock',
                    'reference_id' => $productId,
                    'campus' => $_SESSION['user_campus'] ?? 'Rajanpur',
                    'category' => ($_POST['category'] ?? '') === 'Stationery' ? 'Stationery & Printing' : 'POS Stock',
                    'description' => 'Initial stock purchase: ' . sanitizeInput($_POST['name']),
                    'amount' => $stockCost,
                    'expense_type' => 'auto',
                    'status' => 'pending',
                    'created_by' => getUserId()
                ]);
            }
            $message = "Product added successfully!";
            $messageType = "success";
        } elseif ($action === 'edit_product') {
            $stmt = $db->prepare("UPDATE pos_products SET name = :name, category = :cat, price = :price, stock = :stock WHERE id = :id");
            $stmt->execute([
                ':name' => sanitizeInput($_POST['name']),
                ':cat' => $_POST['category'],
                ':price' => (float)$_POST['price'],
                ':stock' => (int)$_POST['stock'],
                ':id' => $_POST['id']
            ]);
            $message = "Product updated successfully!";
            $messageType = "success";
        } elseif ($action === 'delete_product') {
            $stmt = $db->prepare("UPDATE pos_products SET is_active = 0 WHERE id = :id");
            $stmt->execute([':id' => $_POST['id']]);
            $message = "Product removed successfully!";
            $messageType = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch Data
$products = $db->query("SELECT * FROM pos_products WHERE is_active = 1 ORDER BY category, name")->fetchAll();
$dailySales = $db->query("SELECT * FROM pos_sales WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC")->fetchAll();
$totalDailyRevenue = array_sum(array_column($dailySales, 'total_amount'));

$page_title = "Cafeteria & Uniform POS";
include '../../includes/header.php';
?>
<script src="../../assets/js/payment_helper.js?v=2"></script>

<div class="container-fluid">
    <!-- Top Stats & Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2 class="page-title text-navy mb-0">POS Terminal</h2>
        <div class="d-flex gap-3">
            <div class="bg-white px-4 py-2 rounded-4 shadow-sm border-start border-teal border-4">
                <small class="text-muted d-block">Today's Sales</small>
                <span class="fw-bold text-navy">PKR <?php echo number_format($totalDailyRevenue, 2); ?></span>
            </div>
            <button class="btn btn-navy shadow-sm" data-bs-toggle="modal" data-bs-target="#reportModal">
                <i class="fas fa-chart-line me-2"></i>Daily Report
            </button>
            <button class="btn btn-teal text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#manageProductsModal">
                <i class="fas fa-boxes me-2"></i>Manage Products
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show no-print" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 no-print">
        <!-- Product Grid (Left Side) -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white p-3 border-0">
                    <ul class="nav nav-pills custom-pills" id="posTabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-filter="all">All Items</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-filter="Cafeteria">Cafeteria</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-filter="Uniform">Uniform</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-filter="Stationery">Stationery</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body bg-light p-4">
                    <div class="row g-3" id="productGrid">
                        <?php foreach ($products as $p): ?>
                            <div class="col-xl-3 col-md-4 product-card-wrapper" data-category="<?php echo $p['category']; ?>">
                                <div class="card h-100 border-0 shadow-sm product-card" onclick='addToCart(<?php echo json_encode($p); ?>)'>
                                    <div class="position-relative">
                                        <div class="bg-teal-subtle text-teal p-4 text-center rounded-top-4">
                                            <i class="fas <?php 
                                                echo $p['category'] === 'Cafeteria' ? 'fa-utensils' : 
                                                    ($p['category'] === 'Uniform' ? 'fa-tshirt' : 'fa-pen-fancy'); 
                                            ?> fa-3x opacity-50"></i>
                                        </div>
                                        <span class="badge bg-navy position-absolute top-0 end-0 m-2">
                                            PKR <?php echo number_format($p['price'], 0); ?>
                                        </span>
                                    </div>
                                    <div class="card-body p-3 text-center">
                                        <h6 class="mb-1 fw-bold text-navy"><?php echo htmlspecialchars($p['name']); ?></h6>
                                        <small class="text-muted">Stock: <?php echo $p['stock']; ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Section (Right Side) -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 sticky-top" style="top: 100px;">
                <div class="card-header bg-navy text-white p-4 rounded-top-4 border-0">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Current Order</h5>
                </div>
                <div class="card-body p-0 flex-grow-1 overflow-auto" style="max-height: 400px;">
                    <div id="cartItemsList">
                        <div class="text-center py-5 text-muted" id="emptyCartMsg">
                            <i class="fas fa-cart-plus fa-3x mb-3 opacity-20"></i>
                            <p>Select items to start an order</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white p-4 rounded-bottom-4 border-0 shadow-top">
                    <div class="mb-3">
                        <label class="small text-muted mb-1">Student Details</label>
                        <input type="text" id="cart_student_name" class="form-control form-control-sm mb-2" placeholder="Student Name">
                        <select id="cart_student_class" class="form-select form-select-sm">
                            <option value="">Select Class</option>
                            <option>ICS</option><option>FSc</option><option>BSCS</option>
                            <option>Web Dev</option><option>Graphic Design</option>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-bold" id="cartSubtotal">PKR 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-4">
                        <h4 class="text-navy mb-0">Total</h4>
                        <h4 class="text-teal mb-0" id="cartTotal">PKR 0.00</h4>
                    </div>

                    <button class="btn btn-teal text-white w-100 py-3 rounded-3 shadow-sm fw-bold" id="checkoutBtn" disabled data-bs-toggle="modal" data-bs-target="#checkoutModal">
                        CHECKOUT & PAYMENT
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Checkout Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title">Checkout & Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="checkoutForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Student Name</label>
                            <input type="text" id="pay_student_name" class="form-control" placeholder="Student Name / Walk-in">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <select id="pay_student_class" class="form-select">
                                <option value="">Select Program</option>
                                <optgroup label="Intermediate">
                                    <option>FSc Pre-Medical</option>
                                    <option>FSc Pre-Engineering</option>
                                    <option>ICS</option>
                                    <option>I.Com</option>
                                    <option>FA</option>
                                </optgroup>
                                <optgroup label="Degree">
                                    <option>ADP Arts</option>
                                    <option>ADP Science</option>
                                    <option>BSCS</option>
                                    <option>BS IT</option>
                                </optgroup>
                                <optgroup label="NAVTTC">
                                    <option>Web Development</option>
                                    <option>Graphic Designing</option>
                                    <option>Digital Marketing</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campus</label>
                            <select id="pay_campus" class="form-select">
                                <option>Rajanpur</option>
                                <option>Fazilpur</option>
                                <option>Kot Mithan</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Payment Method</label>
                            <select id="pay_method" name="payment_method" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Online Payment">Online Payment</option>
                                <option value="Cheque">Cheque</option>
                                <option value="EasyPaisa">EasyPaisa</option>
                                <option value="JazzCash">JazzCash</option>
                                <option value="Card/ATM">Card/ATM</option>
                            </select>
                        </div>
                        <div class="col-12" id="tid_field" style="display: none;">
                            <label class="form-label">Transaction ID / Reference</label>
                            <input type="text" id="pay_tid" name="transaction_id" class="form-control" placeholder="Enter transaction ID">
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 bg-light rounded-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Order Total:</span>
                            <span class="fw-bold text-navy" id="modalTotal">PKR 0.00</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-teal text-white fw-bold" onclick="submitOrder()">CONFIRM SALE & PRINT</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Receipt Modal (Hidden usually, shown for print preview) -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0">
            <div class="modal-body p-4" id="receiptArea">
                <!-- Content generated by JS -->
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-navy w-100" onclick="printReceipt()">Print Receipt</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Products Modal -->
<div class="modal fade" id="manageProductsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-teal text-white">
                <h5 class="modal-title">Product Management</h5>
                <button class="btn btn-light btn-sm ms-auto me-2" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetProductModal()">Add Product</button>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-navy"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><span class="badge bg-light text-navy border"><?php echo $p['category']; ?></span></td>
                                <td>PKR <?php echo number_format($p['price'], 2); ?></td>
                                <td>
                                    <span class="fw-bold <?php echo $p['stock'] < 10 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo $p['stock']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-warning" onclick='showEditProduct(<?php echo json_encode($p); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this product?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Product Modals and Reports Modals omitted for brevity, will implement fully -->

<style>
    :root {
        --teal-subtle: rgba(78, 194, 181, 0.1);
    }
    .text-navy { color: var(--navy); }
    .text-teal { color: var(--teal); }
    .bg-navy { background-color: var(--navy) !important; }
    .bg-teal { background-color: var(--teal) !important; }
    .bg-teal-subtle { background-color: var(--teal-subtle); }
    .btn-teal { background-color: var(--teal); border-color: var(--teal); }
    .btn-teal:hover { background-color: var(--teal-dark); border-color: var(--teal-dark); color: white; }
    .btn-outline-teal { color: var(--teal); border-color: var(--teal); }
    .btn-outline-teal:hover, .btn-check:checked + .btn-outline-teal { background-color: var(--teal); color: white; }
    .btn-outline-navy { color: var(--navy); border-color: var(--navy); }
    .btn-outline-navy:hover, .btn-check:checked + .btn-outline-navy { background-color: var(--navy); color: white; }
    .btn-navy { background-color: var(--navy); border-color: var(--navy); color: white; }
    
    .product-card { transition: all 0.2s; cursor: pointer; border-radius: 15px; }
    .product-card:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .product-card:active { transform: scale(0.95); }
    
    .custom-pills .nav-link { color: var(--navy); border-radius: 10px; margin-right: 10px; font-weight: 500; transition: all 0.3s; }
    .custom-pills .nav-link.active { background-color: var(--teal) !important; color: white !important; box-shadow: 0 4px 10px rgba(78, 194, 181, 0.3); }
    
    .cart-item { border-bottom: 1px solid #eee; transition: all 0.2s; }
    .cart-item:hover { background-color: #fcfcfc; }
    
    #receiptArea { font-family: 'Courier New', Courier, monospace; font-size: 14px; }
    
    @media print {
        body * { visibility: hidden; }
        #receiptArea, #receiptArea * { visibility: visible; }
        #receiptArea { position: absolute; left: 0; top: 0; width: 300px; padding: 10px; }
    }
</style>

<script>
let cart = [];

function addToCart(product) {
    const existing = cart.find(item => item.id === product.id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            qty: 1
        });
    }
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (item) {
        item.qty += delta;
        if (item.qty <= 0) {
            cart = cart.filter(i => i.id !== id);
        }
    }
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cartItemsList');
    const emptyMsg = document.getElementById('emptyCartMsg');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (cart.length === 0) {
        list.innerHTML = '';
        emptyMsg.style.display = 'block';
        checkoutBtn.disabled = true;
        updateTotals(0);
        return;
    }
    
    emptyMsg.style.display = 'none';
    checkoutBtn.disabled = false;
    
    let html = '';
    let total = 0;
    cart.forEach(item => {
        total += item.price * item.qty;
        html += `
            <div class="cart-item p-3 d-flex justify-content-between align-items-center">
                <div class="flex-grow-1">
                    <h6 class="mb-0 text-navy fw-bold">${item.name}</h6>
                    <small class="text-muted">PKR ${item.price} x ${item.qty}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-light border p-1" onclick="updateQty(${item.id}, -1)"><i class="fas fa-minus fa-xs"></i></button>
                    <span class="fw-bold mx-1" style="min-width: 20px; text-align: center;">${item.qty}</span>
                    <button class="btn btn-sm btn-light border p-1" onclick="updateQty(${item.id}, 1)"><i class="fas fa-plus fa-xs"></i></button>
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeItem(${item.id})"><i class="fas fa-times fa-xs"></i></button>
                </div>
                <div class="ms-3 text-end fw-bold text-navy" style="min-width: 80px;">
                    PKR ${(item.price * item.qty).toFixed(0)}
                </div>
            </div>
        `;
    });
    list.innerHTML = html;
    updateTotals(total);
}

function removeItem(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function updateTotals(total) {
    document.getElementById('cartSubtotal').innerText = 'PKR ' + total.toLocaleString();
    document.getElementById('cartTotal').innerText = 'PKR ' + total.toLocaleString();
    document.getElementById('modalTotal').innerText = 'PKR ' + total.toLocaleString();
}

// Initialize payment helper
document.addEventListener('DOMContentLoaded', () => {
    setupPaymentMethod('pay_method', 'tid_field');
});

function submitOrder() {
    const total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const studentName = document.getElementById('pay_student_name').value || 'Walk-in';
    const studentClass = document.getElementById('pay_student_class').value || 'N/A';
    const campus = document.getElementById('pay_campus').value;
    const method = document.getElementById('pay_method').value;
    const tid = document.getElementById('pay_tid').value;
    
    if (document.getElementById('pay_tid').required && !tid) {
        alert('Please enter Transaction ID');
        return;
    }

    // Create form data to submit
    const formData = new FormData();
    formData.append('action', 'process_sale');
    formData.append('student_name', studentName);
    formData.append('class', studentClass);
    formData.append('campus', campus);
    formData.append('items', JSON.stringify(cart));
    formData.append('total_amount', total);
    formData.append('payment_method', method);
    formData.append('transaction_id', tid);
    
    fetch('', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(data => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('checkoutModal')).hide();
        generateReceipt(studentName, studentClass, method, total, tid);
        cart = [];
        renderCart();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('receiptModal')).show();
    })
    .catch(err => {
        alert('Error processing sale: ' + err);
    });
}

function generateReceipt(name, cls, method, total, tid) {
    const now = new Date();
    const dateStr = now.toLocaleDateString();
    const timeStr = now.toLocaleTimeString();
    const campus = '<?php echo $_SESSION['user_campus'] ?? 'Rajanpur'; ?>';
    
    let itemsHtml = '';
    cart.forEach(item => {
        itemsHtml += `<div>${item.name.padEnd(20)} x${item.qty.toString().padEnd(2)} PKR ${item.price * item.qty}</div>`;
    });
    
    let tidHtml = tid ? `<div class="small">TID: ${tid}</div>` : '';
    
    document.getElementById('receiptArea').innerHTML = `
        <div class="text-center">
            <h6 class="fw-bold mb-1">Quaid-e-Azam Group of Colleges</h6>
            <div class="small">Campus: ${campus}</div>
            <div class="small mb-2">Date: ${dateStr} Time: ${timeStr}</div>
            <div class="border-bottom border-dark mb-2"></div>
        </div>
        <div class="mb-2">
            ${itemsHtml}
        </div>
        <div class="border-bottom border-dark mb-2"></div>
        <div class="d-flex justify-content-between fw-bold mb-1">
            <span>Total:</span>
            <span>PKR ${total.toFixed(2)}</span>
        </div>
        <div class="small">Payment: ${method}</div>
        ${tidHtml}
        <div class="text-center small mt-3">
            <div>Customer: ${name} (${cls})</div>
            <div class="mt-2 fw-bold">Thank You!</div>
        </div>
    `;
}

function printReceipt() {
    window.print();
}

function showEditProduct(p) {
    document.getElementById('productModalTitle').innerText = 'Edit Product';
    document.getElementById('productAction').value = 'edit_product';
    document.getElementById('productId').value = p.id;
    document.getElementById('productName').value = p.name;
    document.getElementById('productCategory').value = p.category;
    document.getElementById('productPrice').value = p.price;
    document.getElementById('productStock').value = p.stock;
    
    var modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

function resetProductModal() {
    document.getElementById('productModalTitle').innerText = 'Add New Product';
    document.getElementById('productAction').value = 'add_product';
    document.getElementById('productId').value = '';
    document.getElementById('productForm').reset();
}
</script>

<!-- Combined Add/Edit Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title" id="productModalTitle">Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="productForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="productAction" value="add_product">
                    <input type="hidden" name="id" id="productId">
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" id="productName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" id="productCategory" class="form-select" required>
                            <option>Cafeteria</option>
                            <option>Uniform</option>
                            <option>Stationery</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Price (PKR)</label>
                            <input type="number" step="0.01" name="price" id="productPrice" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" id="productStock" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stock Purchase Cost (optional)</label>
                        <input type="number" step="0.01" name="purchase_cost" id="productPurchaseCost" class="form-control" placeholder="Creates pending expense for stationery/POS stock">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-navy w-100">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title">Daily Sales Report - <?php echo date('d M Y'); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Time</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th class="text-end pe-4">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailySales as $sale): ?>
                            <tr>
                                <td class="ps-4"><?php echo date('h:i A', strtotime($sale['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($sale['student_name']); ?></td>
                                <td><span class="badge bg-light text-navy border"><?php echo $sale['payment_method']; ?></span></td>
                                <td class="text-end pe-4 fw-bold">PKR <?php echo number_format($sale['total_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="ps-4">Total Revenue</td>
                            <td class="text-end pe-4 text-teal">PKR <?php echo number_format($totalDailyRevenue, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
