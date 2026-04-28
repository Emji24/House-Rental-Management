const express = require('express');
const { authenticateToken } = require('../middleware/auth');

const router = express.Router();

// Get rental balances report
router.get('/balances', authenticateToken, (req, res) => {
    const db = req.db;
    const query = `
        SELECT t.*, 
               CONCAT(t.lastname, ', ', t.firstname, ' ', t.middlename) as tenant_name,
               h.house_no,
               h.price as monthly_rate
        FROM tenants t 
        INNER JOIN houses h ON h.id = t.house_id 
        WHERE t.status = 1 
        ORDER BY h.house_no DESC
    `;
    
    db.query(query, async (err, tenants) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }

        const results = [];
        for (const tenant of tenants) {
            const months = Math.floor((new Date() - new Date(tenant.date_in)) / (30 * 24 * 60 * 60 * 1000));
            const payable = tenant.monthly_rate * months;
            
            const paidQuery = await new Promise((resolve, reject) => {
                db.query('SELECT SUM(amount) as total_paid FROM payments WHERE tenant_id = ?', [tenant.id], (err, result) => {
                    if (err) reject(err);
                    resolve(result[0]?.total_paid || 0);
                });
            });
            
            const lastPayment = await new Promise((resolve, reject) => {
                db.query('SELECT * FROM payments WHERE tenant_id = ? ORDER BY date_created DESC LIMIT 1', [tenant.id], (err, result) => {
                    if (err) reject(err);
                    resolve(result[0] || null);
                });
            });

            results.push({
                tenant_id: tenant.id,
                tenant_name: tenant.tenant_name,
                house_no: tenant.house_no,
                monthly_rate: tenant.monthly_rate,
                rent_started: tenant.date_in,
                payable_months: months,
                payable_amount: payable,
                total_paid: paidQuery,
                outstanding_balance: payable - paidQuery,
                last_payment_date: lastPayment?.date_created || null,
                last_payment_amount: lastPayment?.amount || 0
            });
        }

        res.json({ success: true, data: results });
    });
});

// Get monthly payments report
router.get('/monthly-payments', authenticateToken, (req, res) => {
    const db = req.db;
    const { month } = req.query;
    const selectedMonth = month || new Date().toISOString().slice(0, 7);

    const query = `
        SELECT p.*, 
               CONCAT(t.lastname, ', ', t.firstname, ' ', t.middlename) as tenant_name,
               h.house_no
        FROM payments p 
        INNER JOIN tenants t ON t.id = p.tenant_id 
        INNER JOIN houses h ON h.id = t.house_id 
        WHERE DATE_FORMAT(p.date_created, '%Y-%m') = ?
        ORDER BY p.date_created ASC
    `;
    
    db.query(query, [selectedMonth], (err, payments) => {
        if (err) {
            return res.status(500).json({ error: 'Database error', success: false });
        }

        const total_amount = payments.reduce((sum, p) => sum + parseFloat(p.amount), 0);

        res.json({ 
            success: true, 
            data: {
                month: selectedMonth,
                payments: payments,
                total_amount: total_amount,
                count: payments.length
            }
        });
    });
});

// Get dashboard statistics
router.get('/dashboard', authenticateToken, (req, res) => {
    const db = req.db;

    Promise.all([
        new Promise((resolve) => db.query('SELECT COUNT(*) as count FROM houses', (err, r) => resolve(r[0]?.count || 0))),
        new Promise((resolve) => db.query('SELECT COUNT(*) as count FROM tenants WHERE status = 1', (err, r) => resolve(r[0]?.count || 0))),
        new Promise((resolve) => db.query('SELECT SUM(amount) as total FROM payments WHERE DATE(date_created) = CURDATE()', (err, r) => resolve(r[0]?.total || 0))),
        new Promise((resolve) => db.query('SELECT SUM(amount) as total FROM payments WHERE MONTH(date_created) = MONTH(CURDATE())', (err, r) => resolve(r[0]?.total || 0))),
        new Promise((resolve) => db.query('SELECT COUNT(*) as count FROM users', (err, r) => resolve(r[0]?.count || 0)))
    ]).then(([total_houses, total_tenants, today_payments, monthly_payments, total_users]) => {
        res.json({
            success: true,
            data: {
                total_houses,
                total_tenants,
                today_payments: parseFloat(today_payments),
                monthly_payments: parseFloat(monthly_payments),
                total_users
            }
        });
    });
});

module.exports = router;