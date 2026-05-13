const express = require('express');
const { authenticateToken, authorizeAdmin } = require('../middleware/auth');

const router = express.Router();

// Public because login.php needs the system name before the user logs in.
router.get('/', (req, res) => {
    const db = req.db;
    db.query('SELECT * FROM system_settings LIMIT 1', (err, results) => {
        if (err) {
            return res.status(500).json({ success: false, error: 'Database error' });
        }
        res.json({ success: true, data: results[0] || {} });
    });
});

// Admin only for updating settings.
router.put('/', authenticateToken, authorizeAdmin, (req, res) => {
    const db = req.db;
    const { name, email, contact, about_content } = req.body;

    db.query('SELECT id FROM system_settings LIMIT 1', (err, rows) => {
        if (err) {
            return res.status(500).json({ success: false, error: 'Database error' });
        }

        if (rows.length > 0) {
            db.query(
                'UPDATE system_settings SET name = ?, email = ?, contact = ?, about_content = ? WHERE id = ?',
                [name, email, contact, about_content, rows[0].id],
                (err2) => {
                    if (err2) return res.status(500).json({ success: false, error: 'Database error' });
                    res.json({ success: true, message: 'Settings updated', data: { id: rows[0].id, name, email, contact, about_content } });
                }
            );
        } else {
            db.query(
                'INSERT INTO system_settings (name, email, contact, about_content) VALUES (?, ?, ?, ?)',
                [name, email, contact, about_content],
                (err2, result) => {
                    if (err2) return res.status(500).json({ success: false, error: 'Database error' });
                    res.json({ success: true, message: 'Settings created', data: { id: result.insertId, name, email, contact, about_content } });
                }
            );
        }
    });
});

module.exports = router;
