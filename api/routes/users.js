const express = require('express');
const { body, validationResult } = require('express-validator');
const { authenticateToken, authorizeAdmin } = require('../middleware/auth');

const router = express.Router();

// Get all users Admin only
router.get('/', authenticateToken, authorizeAdmin, (req, res) => {
    const db = req.db;
    const typeNames = ['', 'Admin', 'Client'];

    db.query(
        'SELECT id, name, username, type FROM users ORDER BY name ASC',
        (err, results) => {
            if (err) {
                console.error('Get users error:', err);
                return res.status(500).json({
                    error: 'Database error',
                    success: false
                });
            }

            const users = results.map(user => ({
                ...user,
                type_name: typeNames[user.type] || 'Unknown'
            }));

            res.json({
                success: true,
                data: users
            });
        }
    );
});

// Create user Admin only
router.post('/', authenticateToken, authorizeAdmin, [
    body('name').notEmpty().withMessage('Name is required'),
    body('username').notEmpty().withMessage('Username is required'),
    body('password').notEmpty().withMessage('Password is required'),
    body('type').isIn(['1', '2', 1, 2]).withMessage('Invalid user type')
], (req, res) => {
    const errors = validationResult(req);

    if (!errors.isEmpty()) {
        return res.status(400).json({
            errors: errors.array(),
            success: false
        });
    }

    const db = req.db;
    const { name, username, password, type } = req.body;

    db.query(
        'SELECT * FROM users WHERE username = ?',
        [username],
        (err, results) => {
            if (err) {
                console.error('Check username error:', err);
                return res.status(500).json({
                    error: 'Database error',
                    success: false
                });
            }

            if (results.length > 0) {
                return res.status(409).json({
                    error: 'Username already exists',
                    success: false
                });
            }

            const md5Password = require('crypto')
                .createHash('md5')
                .update(password)
                .digest('hex');

            const query = `
                INSERT INTO users (name, username, password, type)
                VALUES (?, ?, ?, ?)
            `;

            db.query(
                query,
                [name, username, md5Password, type],
                (err, result) => {
                    if (err) {
                        console.error('Create user error:', err);
                        return res.status(500).json({
                            error: 'Database error',
                            success: false
                        });
                    }

                    res.json({
                        success: true,
                        message: 'User created successfully',
                        data: {
                            id: result.insertId
                        }
                    });
                }
            );
        }
    );
});

// Update user Admin only
router.put('/:id', authenticateToken, authorizeAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    const { name, username, password, type } = req.body;

    db.query(
        'SELECT * FROM users WHERE username = ? AND id != ?',
        [username, id],
        (err, results) => {
            if (err) {
                console.error('Check username update error:', err);
                return res.status(500).json({
                    error: 'Database error',
                    success: false
                });
            }

            if (results.length > 0) {
                return res.status(409).json({
                    error: 'Username already exists',
                    success: false
                });
            }

            let query = 'UPDATE users SET name = ?, username = ?, type = ?';
            let params = [name, username, type];

            if (password && password.trim() !== '') {
                const md5Password = require('crypto')
                    .createHash('md5')
                    .update(password)
                    .digest('hex');

                query += ', password = ?';
                params.push(md5Password);
            }

            query += ' WHERE id = ?';
            params.push(id);

            db.query(query, params, (err, result) => {
                if (err) {
                    console.error('Update user error:', err);
                    return res.status(500).json({
                        error: 'Database error',
                        success: false
                    });
                }

                if (result.affectedRows === 0) {
                    return res.status(404).json({
                        error: 'User not found',
                        success: false
                    });
                }

                res.json({
                    success: true,
                    message: 'User updated successfully'
                });
            });
        }
    );
});

// Delete user Admin only
router.delete('/:id', authenticateToken, authorizeAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;

    if (parseInt(id) === req.user.id) {
        return res.status(400).json({
            error: 'Cannot delete your own account',
            success: false
        });
    }

    db.query(
        'DELETE FROM users WHERE id = ?',
        [id],
        (err, result) => {
            if (err) {
                console.error('Delete user error:', err);
                return res.status(500).json({
                    error: 'Database error',
                    success: false
                });
            }

            if (result.affectedRows === 0) {
                return res.status(404).json({
                    error: 'User not found',
                    success: false
                });
            }

            res.json({
                success: true,
                message: 'User deleted successfully'
            });
        }
    );
});

module.exports = router;