const express = require('express');
const { body, validationResult } = require('express-validator');
const { authenticateToken, authorizeStaffOrAdmin } = require('../middleware/auth');

const router = express.Router();

// Get all tenants
router.get('/', authenticateToken, (req, res) => {
    const db = req.db;
    const query = `
        SELECT t.*, 
               CONCAT(t.lastname, ', ', t.firstname, ' ', t.middlename) as full_name,
               h.house_no, 
               h.price as monthly_rate
        FROM tenants t 
        INNER JOIN houses h ON h.id = t.house_id 
        WHERE t.status = 1 
        ORDER BY t.lastname ASC
    `;
    
    db.query(query, (err, results) => {
        if (err) {
            console.error(err);
            return res.status(500).json({ error: 'Database error', success: false });
        }
        
        // Calculate outstanding balance for each tenant
        const tenants = results.map(tenant => {
            const months = Math.floor((new Date() - new Date(tenant.date_in)) / (30 * 24 * 60 * 60 * 1000));
            const payable = tenant.monthly_rate * months;
            return { ...tenant, payable_months: months, payable_amount: payable };
        });
        
        res.json({ success: true, data: tenants });
    });
});

// Get single tenant with payment details
router.get('/:id', authenticateToken, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    
    const query = `
        SELECT t.*, 
               CONCAT(t.lastname, ', ', t.firstname, ' ', t.middlename) as full_name,
               h.house_no, 
               h.price as monthly_rate
        FROM tenants t 
        INNER JOIN houses h ON h.id = t.house_id 
        WHERE t.id = ? AND t.status = 1
    `;
    
    db.query(query, [id], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length === 0) {
            return res.status(404).json({ error: 'Tenant not found', success: false });
        }
        
        const tenant = results[0];
        const months = Math.floor((new Date() - new Date(tenant.date_in)) / (30 * 24 * 60 * 60 * 1000));
        const payable = tenant.monthly_rate * months;
        
        // Get total paid
        db.query('SELECT SUM(amount) as total_paid FROM payments WHERE tenant_id = ?', [id], (err, paidResult) => {
            const totalPaid = paidResult[0]?.total_paid || 0;
            const outstanding = payable - totalPaid;
            
            // Get last payment
            db.query('SELECT * FROM payments WHERE tenant_id = ? ORDER BY date_created DESC LIMIT 1', [id], (err, lastPayment) => {
                tenant.payable_months = months;
                tenant.payable_amount = payable;
                tenant.total_paid = totalPaid;
                tenant.outstanding_balance = outstanding;
                tenant.last_payment = lastPayment[0] || null;
                
                res.json({ success: true, data: tenant });
            });
        });
    });
});

// Create tenant
router.post('/', authenticateToken, authorizeStaffOrAdmin, [
    body('firstname').notEmpty().withMessage('First name is required'),
    body('lastname').notEmpty().withMessage('Last name is required'),
    body('email').isEmail().withMessage('Valid email is required'),
    body('contact').notEmpty().withMessage('Contact is required'),
    body('house_id').notEmpty().withMessage('House is required'),
    body('date_in').notEmpty().withMessage('Registration date is required')
], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({ errors: errors.array(), success: false });
    }

    const db = req.db;
    const { firstname, lastname, middlename, email, contact, house_id, date_in } = req.body;

    // Check if house is already occupied
    db.query('SELECT * FROM tenants WHERE house_id = ? AND status = 1', [house_id], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length > 0) {
            return res.status(409).json({ error: 'House is already occupied', success: false });
        }

        const query = `INSERT INTO tenants (firstname, lastname, middlename, email, contact, house_id, date_in, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, 1)`;
        db.query(query, [firstname, lastname, middlename, email, contact, house_id, date_in], (err, result) => {
            if (err) {
                return res.status(500).json({ error: 'Database error', success: false });
            }
            res.json({ 
                success: true, 
                message: 'Tenant created successfully',
                data: { id: result.insertId }
            });
        });
    });
});

// Update tenant
router.put('/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    const { firstname, lastname, middlename, email, contact, house_id, date_in } = req.body;

    // Check if changing house would cause conflict
    db.query('SELECT * FROM tenants WHERE house_id = ? AND status = 1 AND id != ?', [house_id, id], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length > 0) {
            return res.status(409).json({ error: 'Selected house is already occupied', success: false });
        }

        const query = `UPDATE tenants SET firstname=?, lastname=?, middlename=?, email=?, contact=?, house_id=?, date_in=? 
                       WHERE id=? AND status=1`;
        db.query(query, [firstname, lastname, middlename, email, contact, house_id, date_in, id], (err, result) => {
            if (err) {
                return res.status(500).json({ error: 'Database error', success: false });
            }
            if (result.affectedRows === 0) {
                return res.status(404).json({ error: 'Tenant not found', success: false });
            }
            res.json({ success: true, message: 'Tenant updated successfully' });
        });
    });
});

// Delete tenant (soft delete)
router.delete('/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;

    db.query('UPDATE tenants SET status = 0 WHERE id = ?', [id], (err, result) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (result.affectedRows === 0) {
            return res.status(404).json({ error: 'Tenant not found', success: false });
        }
        res.json({ success: true, message: 'Tenant deleted successfully' });
    });
});

module.exports = router;