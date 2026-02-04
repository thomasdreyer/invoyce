<?php
require '../config/db.php';
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

/* -----------------------------
   Helpers
----------------------------- */
function respond($data){
    echo json_encode($data);
    exit;
}

/* ===== Note: Use api/customers.php for add/list customers ===== */
if($action === 'add_customer' && $method==='POST'){
    respond(['success'=>false,'message'=>'Use POST to /api/customers.php instead']);
}

/* -----------------------------
   Create invoice
----------------------------- */
if($action === 'create_invoice' && $method==='POST'){
    try{
        $data = json_decode(file_get_contents('php://input'), true);

        $customer_id = intval($data['customer_id'] ?? 0);
        $invoice_date = trim($data['invoice_date'] ?? '');
        $due_date = trim($data['due_date'] ?? '');
        $items = $data['items'] ?? [];
        $status = in_array($data['status'] ?? 'draft', ['draft','sent','paid','overdue']) ? $data['status'] : 'draft';
        $invoice_discount_percent = floatval($data['invoice_discount_percent'] ?? 0);
        $invoice_discount_amount = floatval($data['invoice_discount_amount'] ?? 0);

        if(!$customer_id || !$invoice_date || !$due_date || empty($items)){
            respond(['success'=>false,'message'=>'Missing required fields']);
        }

        // Validate dates
        if(!strtotime($invoice_date) || !strtotime($due_date)){
            respond(['success'=>false,'message'=>'Invalid date format']);
        }

        // Validate discount values
        if($invoice_discount_percent < 0 || $invoice_discount_percent > 100){
            respond(['success'=>false,'message'=>'Invoice discount percent must be between 0 and 100']);
        }
        if($invoice_discount_amount < 0){
            respond(['success'=>false,'message'=>'Invoice discount amount cannot be negative']);
        }

        // Validate items
        if(count($items) > 1000) respond(['success'=>false,'message'=>'Too many line items']);

        $subtotal = 0;
        foreach($items as $item){
            $qty = floatval($item['qty'] ?? 0);
            $price = floatval($item['price'] ?? 0);
            $disc_pct = floatval($item['discount_percent'] ?? 0);
            $disc_amt = floatval($item['discount_amount'] ?? 0);

            if($qty < 0 || $price < 0) respond(['success'=>false,'message'=>'Quantity and price must be non-negative']);
            if($disc_pct < 0 || $disc_pct > 100) respond(['success'=>false,'message'=>'Item discount percent must be 0-100']);
            if($disc_amt < 0) respond(['success'=>false,'message'=>'Item discount amount must be non-negative']);

            $line_sub = $qty*$price;
            if($disc_pct > 0){
                $line_sub -= $line_sub*($disc_pct/100);
            }elseif($disc_amt > 0){
                $line_sub -= $disc_amt;
            }
            if($line_sub < 0) $line_sub = 0;

            $subtotal += $line_sub;
        }

        $tax = $subtotal*0.15;
        $total_after_tax = $subtotal+$tax;

        if($invoice_discount_percent>0){
            $invoice_discount = $total_after_tax*($invoice_discount_percent/100);
        }else{
            $invoice_discount = $invoice_discount_amount;
        }

        $total = $total_after_tax-$invoice_discount;
        $share_token = bin2hex(random_bytes(32));

        // Generate invoice number (INV-YYYY-00001) - optional if column doesn't exist
        $invoice_number = null;
        try {
            $year = date('Y');
            $lastInvoice = $pdo->query("
                SELECT invoice_number FROM invoices 
                WHERE invoice_number LIKE 'INV-$year-%' 
                ORDER BY id DESC LIMIT 1
            ")->fetch();
            
            $nextNum = 1;
            if($lastInvoice && $lastInvoice['invoice_number']){
                $parts = explode('-', $lastInvoice['invoice_number']);
                $nextNum = intval(end($parts)) + 1;
            }
            $invoice_number = 'INV-' . $year . '-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            // invoice_number column might not exist yet, that's ok
        }

        // Insert invoice with optional invoice_number
        if($invoice_number) {
            $stmt = $pdo->prepare("
                INSERT INTO invoices
                (invoice_number, customer_id, invoice_date, due_date, subtotal, tax,
                 discount_percent, discount_amount, total, status, share_token)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $invoice_number,$customer_id,$invoice_date,$due_date,$subtotal,$tax,
                $invoice_discount_percent,$invoice_discount_amount,
                $total,$status,$share_token
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO invoices
                (customer_id, invoice_date, due_date, subtotal, tax,
                 discount_percent, discount_amount, total, status, share_token)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $customer_id,$invoice_date,$due_date,$subtotal,$tax,
                $invoice_discount_percent,$invoice_discount_amount,
                $total,$status,$share_token
            ]);
        }

        $invoice_id = $pdo->lastInsertId();

        foreach($items as $item){
            $qty = floatval($item['qty']);
            $price = floatval($item['price']);
            $line_total = $qty*$price;

            if(!empty($item['discount_percent'])){
                $line_total -= $line_total*($item['discount_percent']/100);
            }elseif(!empty($item['discount_amount'])){
                $line_total -= floatval($item['discount_amount']);
            }

            $stmt = $pdo->prepare("
                INSERT INTO invoice_items
                (invoice_id, description, quantity, unit_price,
                 discount_percent, discount_amount, line_total)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $invoice_id,$item['desc'],$qty,$price,
                $item['discount_percent'] ?? 0,
                $item['discount_amount'] ?? 0,
                $line_total
            ]);
        }

        respond(['success'=>true,'id'=>$invoice_id]);

    }catch(Exception $e){
        respond(['success'=>false,'message'=>$e->getMessage()]);
    }
}

/* -----------------------------
   Get invoice
----------------------------- */
if($action === 'get_invoice'){
    $id = intval($_GET['id'] ?? 0);
    if($id <= 0) respond(['success'=>false,'message'=>'Invalid invoice ID']);

    $stmt = $pdo->prepare("
        SELECT i.*, c.name AS customer_name, c.email
        FROM invoices i
        JOIN customers c ON i.customer_id=c.id
        WHERE i.id=?
    ");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();

    if(!$invoice) respond(['success'=>false,'message'=>'Invoice not found']);

    $items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=?");
    $items->execute([$id]);
    $items = $items->fetchAll();

    respond(['success'=>true,'invoice'=>$invoice,'items'=>$items]);
}

/* -----------------------------
   Update invoice
----------------------------- */
if($action === 'update_invoice' && $method==='POST'){
    try{
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if($id <= 0) respond(['success'=>false,'message'=>'Invalid invoice ID']);

        $pdo->prepare("
            UPDATE invoices
            SET customer_id=?, invoice_date=?, due_date=?, status=?,
                discount_percent=?, discount_amount=?, total=?
            WHERE id=?
        ")->execute([
            $data['customer_id'],
            $data['invoice_date'],
            $data['due_date'],
            $data['status'],
            $data['invoice_discount_percent'] ?? 0,
            $data['invoice_discount_amount'] ?? 0,
            $data['total'],
            $id
        ]);

        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$id]);

        foreach($data['items'] as $item){
            $qty = floatval($item['qty']);
            $price = floatval($item['price']);
            $line_total = $qty*$price;

            if(!empty($item['discount_percent'])){
                $line_total -= $line_total*($item['discount_percent']/100);
            }elseif(!empty($item['discount_amount'])){
                $line_total -= floatval($item['discount_amount']);
            }

            $stmt = $pdo->prepare("
                INSERT INTO invoice_items
                (invoice_id, description, quantity, unit_price,
                 discount_percent, discount_amount, line_total)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $id,$item['desc'],$qty,$price,
                $item['discount_percent'] ?? 0,
                $item['discount_amount'] ?? 0,
                $line_total
            ]);
        }

        respond(['success'=>true]);

    }catch(Exception $e){
        respond(['success'=>false,'message'=>$e->getMessage()]);
    }
}

/* -----------------------------
   Delete invoice
----------------------------- */
if($action === 'delete_invoice' && $method==='POST'){
    $id = intval($_POST['id'] ?? 0);
    if($id <= 0) respond(['success'=>false,'message'=>'Invalid invoice ID']);

    try{
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]);
        respond(['success'=>true]);
    }catch(Exception $e){
        respond(['success'=>false,'message'=>$e->getMessage()]);
    }
}

respond(['success'=>false,'message'=>'Invalid API action']);
