const express = require('express');
const { body, validationResult } = require('express-validator');
const { authenticateToken, authorizeStaffOrAdmin } = require('../middleware/auth');

const router = express.Router();

// Get all payments
router.get('/', authenticateToken, (req, res) => {
    const db = req.db;
    const query = `
        SELECT p.*, 
               CONCAT(t.lastname, ', ', t.firstname, ' ', t.middlename) as tenant_name,
               h.house_no
        FROM payments p 
        INNER JOIN tenants t ON t.id = p.tenant_id 
        INNER JOIN houses h ON h.id = t.house_id 
        WHERE t.status = 1 
        ORDER BY p.date_created DESC
    `;
    
    db.query(query, (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        res.json({ success: true, data: results });
    });
});

// Get payments by tenant
router.get('/tenant/:tenantId', authenticateToken, (req, res) => {
    const db = req.db;
    const { tenantId } = req.params;

    db.query('SELECT * FROM payments WHERE tenant_id = ? ORDER BY date_created DESC', [tenantId], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        res.json({ success: true, data: results });
    });
});

// Create payment
router.post('/', authenticateToken, authorizeStaffOrAdmin, [
    body('tenant_id').notEmpty().withMessage('Tenant is required'),
    body('amount').isNumeric().withMessage('Amount must be a number'),
    body('invoice').notEmpty().withMessage('Invoice is required')
], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({ errors: errors.array(), success: false });
    }

    const db = req.db;
    const { tenant_id, amount, invoice } = req.body;
    const date_created = new Date();

    const query = 'INSERT INTO payments (tenant_id, amount, invoice, date_created) VALUES (?, ?, ?, ?)';
    db.query(query, [tenant_id, amount, invoice, date_created], (err, result) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        res.json({ 
            success: true, 
            message: 'Payment recorded successfully',
            data: { id: result.insertId }
        });
    });
});

// Update payment
router.put('/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    const { amount, invoice } = req.body;

    const query = 'UPDATE payments SET amount = ?, invoice = ? WHERE id = ?';
    db.query(query, [amount, invoice, id], (err, result) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (result.affectedRows === 0) {
            return res.status(404).json({ error: 'Payment not found', success: false });
        }
        res.json({ success: true, message: 'Payment updated successfully' });
    });
});

// Delete payment
router.delete('/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;

    db.query('DELETE FROM payments WHERE id = ?', [id], (err, result) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (result.affectedRows === 0) {
            return res.status(404).json({ error: 'Payment not found', success: false });
        }
        res.json({ success: true, message: 'Payment deleted successfully' });
    });
});

module.exports = router;