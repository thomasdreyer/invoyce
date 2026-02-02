function addRow() {
    const tbody = document.querySelector('#itemsTable tbody');
    const row = document.createElement('tr');
  
    row.innerHTML = `
      <td><input name="items[][desc]" required></td>
      <td><input type="number" name="items[][qty]" value="1" min="1" onchange="recalc()" required></td>
      <td><input type="number" name="items[][price]" step="0.01" value="0" onchange="recalc()" required></td>
      <td><input type="number" name="items[][discount_percent]" step="0.01" min="0" max="100" value="0" onchange="recalc()" style="width: 80px;" placeholder="%"></td>
      <td><input type="number" name="items[][discount_amount]" step="0.01" min="0" value="0" onchange="recalc()" style="width: 80px;" placeholder="R"></td>
      <td class="line-total">0.00</td>
      <td><button type="button" onclick="this.closest('tr').remove(); recalc();">✕</button></td>
    `;
  
    tbody.appendChild(row);
  }
  
  function recalc() {
    let subtotal = 0;
  
    document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
      const qty = parseFloat(row.querySelector('input[name$="[qty]"]').value || 0);
      const price = parseFloat(row.querySelector('input[name$="[price]"]').value || 0);
      const discountPercent = parseFloat(row.querySelector('input[name$="[discount_percent]"]').value || 0);
      const discountAmount = parseFloat(row.querySelector('input[name$="[discount_amount]"]').value || 0);
      
      const lineSubtotal = qty * price;
      let lineDiscount = 0;
      
      // Apply item discount (percent takes precedence if both are provided)
      if (discountPercent > 0) {
        lineDiscount = lineSubtotal * (discountPercent / 100);
      } else if (discountAmount > 0) {
        lineDiscount = discountAmount;
      }
      
      const lineTotal = lineSubtotal - lineDiscount;
      row.querySelector('.line-total').textContent = lineTotal.toFixed(2);
      subtotal += lineTotal;
    });
  
    const tax = subtotal * 0.15;
    const totalAfterTax = subtotal + tax;
    
    // Apply invoice-level discount
    const invoiceDiscountPercent = parseFloat(document.getElementById('invoice_discount_percent').value || 0);
    const invoiceDiscountAmount = parseFloat(document.getElementById('invoice_discount_amount').value || 0);
    
    let invoiceDiscount = 0;
    if (invoiceDiscountPercent > 0) {
      invoiceDiscount = totalAfterTax * (invoiceDiscountPercent / 100);
    } else if (invoiceDiscountAmount > 0) {
      invoiceDiscount = invoiceDiscountAmount;
    }
    
    const total = totalAfterTax - invoiceDiscount;
  
    document.getElementById('subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('tax').textContent = tax.toFixed(2);
    
    // Show/hide invoice discount row
    const discountRow = document.getElementById('invoiceDiscountRow');
    if (invoiceDiscount > 0) {
      discountRow.style.display = 'block';
      document.getElementById('invoice_discount').textContent = invoiceDiscount.toFixed(2);
    } else {
      discountRow.style.display = 'none';
    }
    
    document.getElementById('total').textContent = total.toFixed(2);
  }
  