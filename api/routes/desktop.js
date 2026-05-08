const express = require('express');
const { authenticateToken, authorizeStaffOrAdmin } = require('../middleware/auth');

const router = express.Router();

function splitFullName(fullName) {
    const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return { firstname: '', middlename: '', lastname: '' };
    if (parts.length === 1) return { firstname: parts[0], middlename: '', lastname: parts[0] };
    if (parts.length === 2) return { firstname: parts[0], middlename: '', lastname: parts[1] };
    return {
        firstname: parts[0],
        middlename: parts.slice(1, -1).join(' '),
        lastname: parts[parts.length - 1]
    };
}

function getOrCreateCategory(db, categoryName) {
    return new Promise((resolve, reject) => {
        const name = String(categoryName || 'General').trim() || 'General';
        db.query('SELECT id FROM categories WHERE name = ? LIMIT 1', [name], (err, rows) => {
            if (err) return reject(err);
            if (rows.length > 0) return resolve(rows[0].id);
            db.query('INSERT INTO categories (name) VALUES (?)', [name], (err2, result) => {
                if (err2) return reject(err2);
                resolve(result.insertId);
            });
        });
    });
}

router.get('/categories', authenticateToken, (req, res) => {
    const db = req.db;

    db.query('SELECT id, name FROM categories ORDER BY name ASC', (err, rows) => {
        if (err) {
            return res.status(500).json({
                success: false,
                error: err.message
            });
        }

        res.json({
            success: true,
            data: rows
        });
    });
});

// Desktop Properties: maps desktop property table fields to house_rental_db.houses
router.get('/properties', authenticateToken, (req, res) => {
    const db = req.db;
    const query = `
        SELECT
            h.id AS property_id,
            h.house_no,
            h.description AS address,
            COALESCE(h.owner_no, '') AS owner_no,
            COALESCE(h.owner_id, '') AS owner_id,
            c.name AS p_type,
            COALESCE(h.rooms, '') AS rooms,
            h.price AS property_rent
        FROM houses h
        LEFT JOIN categories c ON c.id = h.category_id
        ORDER BY h.id DESC
    `;
    db.query(query, (err, rows) => {
        if (err) return res.status(500).json({ success: false, error: err.message });
        res.json({ success: true, data: rows });
    });
});

router.post('/properties', authenticateToken, authorizeStaffOrAdmin, async (req, res) => {
    const db = req.db;
    const { property_id, address, owner_no, owner_id, p_type, rooms, property_rent } = req.body;
    const houseNo = String(property_id || '').trim();
    if (!houseNo) return res.status(400).json({ success: false, error: 'property_id is required' });

    try {
        const categoryId = await getOrCreateCategory(db, p_type);
        const query = `
            INSERT INTO houses (house_no, description, category_id, price, owner_no, owner_id, rooms)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        `;
        db.query(query, [houseNo, address || '', categoryId, Number(property_rent || 0), owner_no || '', owner_id || '', rooms || ''], (err, result) => {
            if (err) {
                if (err.code === 'ER_DUP_ENTRY') return res.status(409).json({ success: false, error: 'Property already exists' });
                return res.status(500).json({ success: false, error: err.message });
            }
            res.json({ success: true, message: 'Property saved', data: { id: result.insertId } });
        });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

router.put('/properties/:id', authenticateToken, authorizeStaffOrAdmin, async (req, res) => {
    const db = req.db;
    const { id } = req.params;
    const { property_id, address, owner_no, owner_id, p_type, rooms, property_rent } = req.body;
    try {
        const categoryId = await getOrCreateCategory(db, p_type);
        const query = `
            UPDATE houses
            SET house_no = ?, description = ?, category_id = ?, price = ?, owner_no = ?, owner_id = ?, rooms = ?
            WHERE id = ? OR house_no = ?
        `;
        db.query(query, [property_id || id, address || '', categoryId, Number(property_rent || 0), owner_no || '', owner_id || '', rooms || '', id, id], (err, result) => {
            if (err) return res.status(500).json({ success: false, error: err.message });
            if (result.affectedRows === 0) return res.status(404).json({ success: false, error: 'Property not found' });
            res.json({ success: true, message: 'Property updated' });
        });
    } catch (err) {
        res.status(500).json({ success: false, error: err.message });
    }
});

router.delete('/properties/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    db.query('DELETE FROM houses WHERE id = ? OR house_no = ?', [id, id], (err, result) => {
        if (err) return res.status(500).json({ success: false, error: err.message });
        if (result.affectedRows === 0) return res.status(404).json({ success: false, error: 'Property not found' });
        res.json({ success: true, message: 'Property deleted' });
    });
});

// Desktop Tenants: maps desktop tenant table fields to house_rental_db.tenants
router.get('/tenants', authenticateToken, (req, res) => {
    const db = req.db;
    const query = `
        SELECT
            t.id AS tenant_id,
            COALESCE(t.tenant_no, '') AS tenant_no,
            COALESCE(h.owner_id, '') AS owner_id,
            h.price AS property_rent,
            t.date_in AS start_date,
            COALESCE(t.end_date, '') AS end_date,
            CONCAT(t.firstname, ' ', t.middlename, ' ', t.lastname) AS tenant_name,
            h.house_no AS property_id,
            h.house_no
        FROM tenants t
        LEFT JOIN houses h ON h.id = t.house_id
        WHERE t.status = 1
        ORDER BY t.id DESC
    `;
    db.query(query, (err, rows) => {
        if (err) return res.status(500).json({ success: false, error: err.message });
        res.json({ success: true, data: rows });
    });
});

router.post('/tenants', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { tenant_no, property_id, property_rent, start_date, end_date, tenant_name, email, contact } = req.body;
    const names = splitFullName(tenant_name);
    const houseNo = String(property_id || '').trim();

    if (!tenant_name) return res.status(400).json({ success: false, error: 'tenant_name is required' });
    if (!houseNo) return res.status(400).json({ success: false, error: 'property_id is required' });

    db.query('SELECT id FROM houses WHERE id = ? OR house_no = ? LIMIT 1', [houseNo, houseNo], (err, houses) => {
        if (err) return res.status(500).json({ success: false, error: err.message });
        if (houses.length === 0) return res.status(404).json({ success: false, error: 'Property/house not found' });
        const houseId = houses[0].id;

        db.query('SELECT id FROM tenants WHERE house_id = ? AND status = 1', [houseId], (err2, existing) => {
            if (err2) return res.status(500).json({ success: false, error: err2.message });
            if (existing.length > 0) return res.status(409).json({ success: false, error: 'Property already has an active tenant' });

            const query = `
                INSERT INTO tenants (firstname, middlename, lastname, email, contact, house_id, status, date_in, tenant_no, end_date)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
            `;
            db.query(query, [
                names.firstname,
                names.middlename,
                names.lastname,
                email || 'tenant@example.com',
                contact || 'N/A',
                houseId,
                start_date || new Date().toISOString().slice(0, 10),
                tenant_no || '',
                end_date || null
            ], (err3, result) => {
                if (err3) return res.status(500).json({ success: false, error: err3.message });
                res.json({ success: true, message: 'Tenant saved', data: { id: result.insertId } });
            });
        });
    });
});

router.put('/tenants/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    const { tenant_no, property_id, start_date, end_date, tenant_name, email, contact } = req.body;
    const names = splitFullName(tenant_name);
    const houseNo = String(property_id || '').trim();

    db.query('SELECT id FROM houses WHERE id = ? OR house_no = ? LIMIT 1', [houseNo, houseNo], (err, houses) => {
        if (err) return res.status(500).json({ success: false, error: err.message });
        if (houses.length === 0) return res.status(404).json({ success: false, error: 'Property/house not found' });
        const houseId = houses[0].id;

        const query = `
            UPDATE tenants
            SET firstname=?, middlename=?, lastname=?, email=?, contact=?, house_id=?, date_in=?, tenant_no=?, end_date=?
            WHERE id=? AND status=1
        `;
        db.query(query, [
            names.firstname,
            names.middlename,
            names.lastname,
            email || 'tenant@example.com',
            contact || 'N/A',
            houseId,
            start_date || new Date().toISOString().slice(0, 10),
            tenant_no || '',
            end_date || null,
            id
        ], (err2, result) => {
            if (err2) return res.status(500).json({ success: false, error: err2.message });
            if (result.affectedRows === 0) return res.status(404).json({ success: false, error: 'Tenant not found' });
            res.json({ success: true, message: 'Tenant updated' });
        });
    });
});

router.delete('/tenants/:id', authenticateToken, authorizeStaffOrAdmin, (req, res) => {
    const db = req.db;
    const { id } = req.params;
    db.query('UPDATE tenants SET status = 0 WHERE id = ?', [id], (err, result) => {
        if (err) return res.status(500).json({ success: false, error: err.message });
        if (result.affectedRows === 0) return res.status(404).json({ success: false, error: 'Tenant not found' });
        res.json({ success: true, message: 'Tenant deleted' });
    });
});

module.exports = router;