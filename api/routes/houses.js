const express = require('express');
const { body, validationResult } = require('express-validator');
const { authenticateToken, authorizeStaffOrAdmin } = require('../middleware/auth');

const router = express.Router();

// Get all houses
router.get('/', authenticateToken, (req, res) => {
    const db = req.db;
    const query = `
        SELECT h.*, c.name as category_name 
        FROM houses h 
        INNER JOIN categories c ON c.id = h.category_id 
        ORDER BY h.id DESC
    `;
    
    db.query(query, (err, results) => {
        if (err) {
            console.error(err);
            return res.status(500).json({ error: 'Database error', success: false });
        }
        res.json({ success: true, data: results });
    });
});

// Get single house
router.get('/:id', authenticateToken, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    
    const query = `
        SELECT h.*, c.name as category_name 
        FROM houses h 
        INNER JOIN categories c ON c.id = h.category_id 
        WHERE h.id = ?
    `;
    
    db.query(query, [id], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length === 0) {
            return res.status(404).json({ error: 'House not found', success: false });
        }
        res.json({ success: true, data: results[0] });
    });
});

// Create house (Staff/Admin only)
router.post('/', authenticateToken, authorizeStaffOrAdmin, [
    body('house_no').notEmpty().withMessage('House number is required'),
    body('category_id').notEmpty().withMessage('Category is required'),
    body('price').isNumeric().withMessage('Price must be a number'),
    body('description').optional()
], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({ errors: errors.array(), success: false });
    }

    const db = req.db;
    const { house_no, description, category_id, price } = req.body;

    // Check if house number already exists
    db.query('SELECT * FROM houses WHERE house_no = ?', [house_no], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length > 0) {
            return res.status(409).json({ error: 'House number already exists', success: false });
        }

        const query = 'INSERT INTO houses (house_no, description, category_id, price) VALUES (?, ?, ?, ?)';
        db.query(query, [house_no, description, category_id, price], (err, result) => {
            if (err) {
                return res.status(500).json({ error: 'Database error', success: false });
            }
            res.json({ 
                success: true, 
                message: 'House created successfully',
                data: { id: result.insertId, house_no, description, category_id, price }
            });
        });
    });
});

// Update house
router.put('/:id', authenticateToken, authorizeStaffOrAdmin, [
    body('house_no').notEmpty().withMessage('House number is required'),
    body('category_id').notEmpty().withMessage('Category is required'),
    body('price').isNumeric().withMessage('Price must be a number')
], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({ errors: errors.array(), success: false });
    }

    const db = req.db;
    const { id } = req.params;
    const { house_no, description, category_id, price } = req.body;

    // Check if house exists and house_no is unique
    db.query('SELECT * FROM houses WHERE house_no = ? AND id != ?', [house_no, id], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length > 0) {
            return res.status(409).json({ error: 'House number already exists', success: false });
        }

        const query = 'UPDATE houses SET house_no = ?, description = ?, category_id = ?, price = ? WHERE id = ?';
        db.query(query, [house_no, description, category_id, price, id], (err, result) => {
            if (err) {
                return res.status(500).json({ error: 'Database error', success: false });
            }
            if (result.affectedRows === 0) {
                return res.status(404).json({ error: 'House not found', success: false });
            }
            res.json({ success: true, message: 'House updated successfully' });
        });
    });
});

// Delete house
router.delete('/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;

    // Check if house has active tenants
    db.query('SELECT * FROM tenants WHERE house_id = ? AND status = 1', [id], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length > 0) {
            return res.status(400).json({ 
                error: 'Cannot delete house with active tenants', 
                success: false 
            });
        }

        db.query('DELETE FROM houses WHERE id = ?', [id], (err, result) => {
            if (err) {
                return res.status(500).json({ error: 'Database error', success: false });
            }
            if (result.affectedRows === 0) {
                return res.status(404).json({ error: 'House not found', success: false });
            }
            res.json({ success: true, message: 'House deleted successfully' });
        });
    });
});

module.exports = router;