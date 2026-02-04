// app.js

document.addEventListener('DOMContentLoaded', () => {
  // --- Auth guard: redirect users to login when not authenticated ---
  const path = window.location.pathname.split('/').pop();
  const storedUser = localStorage.getItem('invoyce_user');
  if (path !== 'login.html' && !storedUser) {
    window.location.href = 'login.html';
    return;
  }
  if (path === 'login.html' && storedUser) {
    window.location.href = 'index.html';
    return;
  }

  // --- Nav user display and logout ---
  const navUserEl = document.getElementById('navUser');
  const user = currentUser();
  if (navUserEl) {
    if (user) {
      navUserEl.innerHTML = `${user.email} <button onclick="logout()">Logout</button>`;
    } else {
      navUserEl.innerHTML = `<a href="login.html" style="color:#fff;text-decoration:none">Login</a>`;
    }
  }

  // --- Set default dates for create invoice page ---
  const invoiceDateInput = document.querySelector('input[name="invoice_date"]');
  const dueDateInput = document.querySelector('input[name="due_date"]');

  if (invoiceDateInput && dueDateInput) {
    invoiceDateInput.valueAsDate = new Date();
    const due = new Date();
    due.setDate(due.getDate() + 30);
    dueDateInput.valueAsDate = due;
  }

  // --- Attach event listeners if elements exist ---
  const toggleBtn = document.getElementById('toggleCustomerBtn');
  const cancelBtn = document.getElementById('cancelCustomerBtn');
  const addCustomerBtn = document.getElementById('addCustomerBtn');
  const addItemBtn = document.getElementById('addItemBtn');

  if (toggleBtn) toggleBtn.addEventListener('click', toggleAddCustomer);
  if (cancelBtn) cancelBtn.addEventListener('click', toggleAddCustomer);
  if (addCustomerBtn) addCustomerBtn.addEventListener('click', addCustomer);
  if (addItemBtn) addItemBtn.addEventListener('click', addRow);

  // --- Load customers if select exists ---
  if (document.getElementById('customerSelect')) {
    loadCustomers();
  }

  // --- Recalculate totals if on create/edit invoice ---
  if (document.getElementById('subtotal')) recalc();

  // --- Load dashboard invoices if table exists ---
  if (document.getElementById('invoicesTableBody')) {
    loadDashboard();
  }

  // --- Form submission for invoices ---
  const invoiceForm = document.getElementById('invoiceForm');
  if (invoiceForm) {
    invoiceForm.addEventListener('submit', submitInvoice);
  }
});

// -------------------- Customers --------------------
function toggleAddCustomer() {
  const form = document.getElementById('addCustomerForm');
  if (!form) return;
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
  if (form.style.display === 'block') {
    document.getElementById('newCustomerName')?.focus();
  } else {
    document.getElementById('newCustomerName').value = '';
    document.getElementById('newCustomerEmail').value = '';
    const msg = document.getElementById('customerMessage');
    if (msg) {
      msg.style.display = 'none';
      msg.textContent = '';
    }
  }
}

function addCustomer() {
  const name = document.getElementById('newCustomerName')?.value.trim();
  const email = document.getElementById('newCustomerEmail')?.value.trim();
  const messageDiv = document.getElementById('customerMessage');
  if (!name) {
    if (messageDiv) {
      messageDiv.textContent = 'Please enter a customer name';
      messageDiv.className = 'alert alert-error';
      messageDiv.style.display = 'block';
    }
    return;
  }

  const formData = new FormData();
  formData.append('action', 'add_customer');
  formData.append('name', name);
  formData.append('email', email || '');

  fetch('api/customers.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const select = document.getElementById('customerSelect');
        if (select) {
          const option = document.createElement('option');
          option.value = data.id;
          option.textContent = data.name;
          option.selected = true;
          select.appendChild(option);
        }
        if (messageDiv) {
          messageDiv.textContent = 'Customer added!';
          messageDiv.className = 'alert alert-success';
          messageDiv.style.display = 'block';
        }
        setTimeout(toggleAddCustomer, 1000);
      } else {
        if (messageDiv) {
          messageDiv.textContent = data.message || 'Error adding customer';
          messageDiv.className = 'alert alert-error';
          messageDiv.style.display = 'block';
        }
      }
    })
    .catch(err => {
      if (messageDiv) {
        messageDiv.textContent = 'Error: ' + err.message;
        messageDiv.className = 'alert alert-error';
        messageDiv.style.display = 'block';
      }
    });
}

function loadCustomers() {
  const select = document.getElementById('customerSelect');
  if (!select) return;

  fetch('api/customers.php')
    .then(res => res.json())
    .then(data => {
      select.innerHTML = '';
      data.forEach(c => {
        const option = document.createElement('option');
        option.value = c.id;
        option.textContent = c.name;
        select.appendChild(option);
      });
    })
    .catch(err => console.error('Error loading customers:', err));
}

// -------------------- Line Items --------------------
function addRow() {
  const tbody = document.querySelector('#itemsTable tbody');
  if (!tbody) return;

  const row = document.createElement('tr');
  row.innerHTML = `
    <td><input type="text" name="items[][desc]" required></td>
    <td><input type="number" name="items[][qty]" value="1" min="1" onchange="recalc()"></td>
    <td><input type="number" name="items[][price]" value="0" min="0" step="0.01" onchange="recalc()"></td>
    <td><input type="number" name="items[][discount_percent]" value="0" min="0" max="100" onchange="recalc()"></td>
    <td><input type="number" name="items[][discount_amount]" value="0" min="0" step="0.01" onchange="recalc()"></td>
    <td class="lineTotal">0.00</td>
    <td><button type="button" onclick="this.closest('tr').remove(); recalc();">Remove</button></td>
  `;
  tbody.appendChild(row);
  recalc();
}

function recalc() {
  const rows = document.querySelectorAll('#itemsTable tbody tr');
  let subtotal = 0;

  rows.forEach(row => {
    const qty = parseFloat(row.querySelector('[name*="[qty]"]')?.value) || 0;
    const price = parseFloat(row.querySelector('[name*="[price]"]')?.value) || 0;
    const discPerc = parseFloat(row.querySelector('[name*="[discount_percent]"]')?.value) || 0;
    const discAmt = parseFloat(row.querySelector('[name*="[discount_amount]"]')?.value) || 0;

    let lineTotal = qty * price;
    if (discPerc > 0) lineTotal -= lineTotal * (discPerc / 100);
    else lineTotal -= discAmt;

    const lineTotalCell = row.querySelector('.lineTotal');
    if (lineTotalCell) lineTotalCell.textContent = lineTotal.toFixed(2);

    subtotal += lineTotal;
  });

  const tax = subtotal * 0.15;
  const invDiscPerc = parseFloat(document.getElementById('invoice_discount_percent')?.value) || 0;
  const invDiscAmt = parseFloat(document.getElementById('invoice_discount_amount')?.value) || 0;
  const totalAfterTax = subtotal + tax;
  const invoiceDiscount = invDiscPerc > 0 ? totalAfterTax * (invDiscPerc / 100) : invDiscAmt;
  const total = totalAfterTax - invoiceDiscount;

  const subtotalEl = document.getElementById('subtotal');
  const taxEl = document.getElementById('tax');
  const discountRow = document.getElementById('invoiceDiscountRow');
  const discountEl = document.getElementById('invoice_discount');
  const totalEl = document.getElementById('total');

  if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2);
  if (taxEl) taxEl.textContent = tax.toFixed(2);
  if (discountRow && discountEl) {
    if (invoiceDiscount > 0) {
      discountRow.style.display = 'table-row';
      discountEl.textContent = invoiceDiscount.toFixed(2);
    } else {
      discountRow.style.display = 'none';
    }
  }
  if (totalEl) totalEl.textContent = total.toFixed(2);
}

// -------------------- Dashboard / Index --------------------
async function loadDashboard() {
  const tbody = document.getElementById('invoicesTableBody');
  if (!tbody) return;

  try {
    const res = await fetch('api/invoices_list.php');
    if (!res.ok) throw new Error('API error');
    const invoices = await res.json();

    tbody.innerHTML = '';
    invoices.forEach(inv => {
      const total = parseFloat(inv.total) || 0;
      const tax = parseFloat(inv.tax) || 0;
      const subtotal = parseFloat(inv.subtotal) || 0;
      const invNumber = inv.invoice_number || `INV-${inv.id}`;

      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${invNumber}</td>
        <td>${inv.customer_name}</td>
        <td>R${subtotal.toFixed(2)}</td>
        <td>R${tax.toFixed(2)}</td>
        <td>R${total.toFixed(2)}</td>
        <td>${inv.status}</td>
        <td>
          <a href="view_invoice.html?id=${inv.id}" class="btn btn-sm btn-primary">View</a>
          <a href="edit_invoice.html?id=${inv.id}" class="btn btn-sm btn-secondary">Edit</a>
          <a href="api/delete_invoice.php?id=${inv.id}" class="btn btn-sm btn-danger" onclick="return confirm('Delete this invoice?')">Delete</a>
        </td>
      `;
      tbody.appendChild(row);
    });
  } catch (err) {
    console.error('Error loading invoices:', err);
    tbody.innerHTML = `<tr><td colspan="7">Error loading invoices</td></tr>`;
  }
}

// -------------------- Form Submission --------------------
function submitInvoice(e) {
  e.preventDefault();
  const messageDiv = document.getElementById('formMessage');
  
  // Validate customer
  const customerId = document.getElementById('customerSelect').value;
  if (!customerId) {
    if (messageDiv) {
      messageDiv.textContent = 'Please select a customer';
      messageDiv.className = 'alert alert-error';
      messageDiv.style.display = 'block';
    }
    return;
  }

  // Collect form data
  const form = e.target;
  const invoiceDate = form.querySelector('[name="invoice_date"]').value;
  const dueDate = form.querySelector('[name="due_date"]').value;
  const rows = document.querySelectorAll('#itemsTable tbody tr');

  if (rows.length === 0) {
    if (messageDiv) {
      messageDiv.textContent = 'Please add at least one item';
      messageDiv.className = 'alert alert-error';
      messageDiv.style.display = 'block';
    }
    return;
  }

  const items = [];
  rows.forEach(row => {
    const desc = row.querySelector('[name*="[desc]"]').value.trim();
    const qty = parseFloat(row.querySelector('[name*="[qty]"]').value) || 0;
    const price = parseFloat(row.querySelector('[name*="[price]"]').value) || 0;
    const discPerc = parseFloat(row.querySelector('[name*="[discount_percent]"]').value) || 0;
    const discAmt = parseFloat(row.querySelector('[name*="[discount_amount]"]').value) || 0;

    // Validate item data
    if (!desc) {
      if (messageDiv) {
        messageDiv.textContent = 'All items must have a description';
        messageDiv.className = 'alert alert-error';
        messageDiv.style.display = 'block';
      }
      throw new Error('Missing item description');
    }
    if (qty <= 0) {
      if (messageDiv) {
        messageDiv.textContent = 'Quantity must be greater than 0';
        messageDiv.className = 'alert alert-error';
        messageDiv.style.display = 'block';
      }
      throw new Error('Invalid quantity');
    }
    if (price < 0) {
      if (messageDiv) {
        messageDiv.textContent = 'Price cannot be negative';
        messageDiv.className = 'alert alert-error';
        messageDiv.style.display = 'block';
      }
      throw new Error('Invalid price');
    }
    if (discPerc < 0 || discPerc > 100) {
      if (messageDiv) {
        messageDiv.textContent = 'Discount percent must be between 0 and 100';
        messageDiv.className = 'alert alert-error';
        messageDiv.style.display = 'block';
      }
      throw new Error('Invalid discount percent');
    }
    if (discAmt < 0) {
      if (messageDiv) {
        messageDiv.textContent = 'Discount amount cannot be negative';
        messageDiv.className = 'alert alert-error';
        messageDiv.style.display = 'block';
      }
      throw new Error('Invalid discount amount');
    }

    items.push({ desc, qty, price, discount_percent: discPerc, discount_amount: discAmt });
  });

  try {
    const invDiscPerc = parseFloat(document.getElementById('invoice_discount_percent').value) || 0;
    const invDiscAmt = parseFloat(document.getElementById('invoice_discount_amount').value) || 0;

    const payload = {
      customer_id: parseInt(customerId),
      invoice_date: invoiceDate,
      due_date: dueDate,
      items: items,
      invoice_discount_percent: invDiscPerc,
      invoice_discount_amount: invDiscAmt,
      status: 'draft'
    };

    fetch('api/invoices.php?action=create_invoice', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (messageDiv) {
            messageDiv.textContent = 'Invoice created successfully! Redirecting...';
            messageDiv.className = 'alert alert-success';
            messageDiv.style.display = 'block';
          }
          setTimeout(() => window.location.href = `view_invoice.html?id=${data.id}`, 1500);
        } else {
          if (messageDiv) {
            messageDiv.textContent = 'Error: ' + (data.message || 'Unknown error');
            messageDiv.className = 'alert alert-error';
            messageDiv.style.display = 'block';
          }
        }
      })
      .catch(err => {
        if (messageDiv) {
          messageDiv.textContent = 'Error: ' + err.message;
          messageDiv.className = 'alert alert-error';
          messageDiv.style.display = 'block';
        }
      });
  } catch (err) {
    console.error('Validation error:', err);
  }
}

// -------------------- Authentication Helpers --------------------
async function requestOtp(email) {
  const res = await fetch('api/auth.php?action=request_otp', {
    method: 'POST',
    body: new URLSearchParams({ email })
  });
  return res.json();
}

async function verifyOtp(email, code) {
  const res = await fetch('api/auth.php?action=verify_otp', {
    method: 'POST',
    body: new URLSearchParams({ email, code })
  });
  return res.json();
}

function currentUser() {
  try {
    return JSON.parse(localStorage.getItem('invoyce_user')) || null;
  } catch (e) {
    return null;
  }
}

function logout() {
  localStorage.removeItem('invoyce_user');
  window.location.href = 'login.html';
}

