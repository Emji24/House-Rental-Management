const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { body, validationResult } = require('express-validator');
const { authenticateToken } = require('../middleware/auth');

const router = express.Router();

// Login
router.post('/login', [
    body('username').notEmpty().withMessage('Username is required'),
    body('password').notEmpty().withMessage('Password is required')
], async (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
        return res.status(400).json({ errors: errors.array(), success: false });
    }

    const { username, password } = req.body;
    const db = req.db;

    try {
        const query = 'SELECT * FROM users WHERE username = ?';
        db.query(query, [username], async (err, results) => {
            if (err) {
                console.error(err);
                return res.status(500).json({ error: 'Database error', success: false });
            }

            if (results.length === 0) {
                return res.status(401).json({ error: 'Invalid credentials', success: false });
            }

            const user = results[0];
            
            // Compare password (using md5 for compatibility with PHP system)
            const md5 = require('crypto').createHash('md5').update(password).digest('hex');
            
            if (user.password !== md5) {
                return res.status(401).json({ error: 'Invalid credentials', success: false });
            }

            // Check if user is admin for main admin login
            if (user.type !== 1) {
                return res.status(403).json({ error: 'Admin access required', success: false });
            }

            // Generate JWT token
            const token = jwt.sign(
                { id: user.id, username: user.username, type: user.type, name: user.name },
                process.env.JWT_SECRET,
                { expiresIn: process.env.JWT_EXPIRE }
            );

            // Remove password from response
            delete user.password;

            res.json({
                success: true,
                token,
                user,
                message: 'Login successful'
            });
        });
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: 'Server error', success: false });
    }
});

// Get current user info
router.get('/me', authenticateToken, (req, res) => {
    const db = req.db;
    const userId = req.user.id;

    db.query('SELECT id, name, username, type FROM users WHERE id = ?', [userId], (err, results) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }
        if (results.length === 0) {
            return res.status(404).json({ error: 'User not found', success: false });
        }
        res.json({ success: true, user: results[0] });
    });
});

module.exports = router;