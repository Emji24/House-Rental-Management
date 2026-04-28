const jwt = require('jsonwebtoken');

const authenticateToken = (req, res, next) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN

    if (!token) {
        return res.status(401).json({ error: 'Access token required', success: false });
    }

    jwt.verify(token, process.env.JWT_SECRET, (err, user) => {
        if (err) {
            return res.status(403).json({ error: 'Invalid or expired token', success: false });
        }
        req.user = user;
        next();
    });
};

const authorizeAdmin = (req, res, next) => {
    if (req.user.type !== 1) {
        return res.status(403).json({ error: 'Admin access required', success: false });
    }
    next();
};

const authorizeStaffOrAdmin = (req, res, next) => {
    if (req.user.type !== 1 && req.user.type !== 2) {
        return res.status(403).json({ error: 'Staff or Admin access required', success: false });
    }
    next();
};

module.exports = { authenticateToken, authorizeAdmin, authorizeStaffOrAdmin };