const express = require('express');
const { body, validationResult } = require('express-validator');
const { authenticateToken, authorizeStaffOrAdmin } = require('../middleware/auth');

const router = express.Router();

// Get all categories
router.get('/', authenticateToken, (req, res) => {
    const db = req.db;
    
    db.query('SELECT * FROM categories ORDER BY name ASC', (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        res.json({ success: true, data: results });
    });
});

// Create category
router.post('/', authenticateToken, authorizeStaffOrAdmin, [
    body('name').notEmpty().withMessage('Category name is required')
], (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({ errors: errors.array(), success: false });
    }

    const db = req.db;
    const { name } = req.body;

    db.query('INSERT INTO categories (name) VALUES (?)', [name], (err, result) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        res.json({ 
            success: true, 
            message: 'Category created successfully',
            data: { id: result.insertId, name }
        });
    });
});

// Update category
router.put('/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    const { name } = req.body;

    db.query('UPDATE categories SET name = ? WHERE id = ?', [name, id], (err, result) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (result.affectedRows === 0) {
            return res.status(404).json({ error: 'Category not found', success: false });
        }
        res.json({ success: true, message: 'Category updated successfully' });
    });
});

// Delete category
router.delete('/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;

    // Check if category has houses
    db.query('SELECT * FROM houses WHERE category_id = ?', [id], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length > 0) {
            return res.status(400).json({ 
                error: 'Cannot delete category with existing houses', 
                success: false 
            });
        }

        db.query('DELETE FROM categories WHERE id = ?', [id], (err, result) => {
            if (err) {
                return res.status(500).json({ error: 'Database error', success: false });
            }
            if (result.affectedRows === 0) {
                return res.status(404).json({ error: 'Category not found', success: false });
            }
            res.json({ success: true, message: 'Category deleted successfully' });
        });
    });
});

module.exports = router;