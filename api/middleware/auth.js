const jwt = require('jsonwebtoken');

const authenticateToken = (req, res, next) => {
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1];

    if (!token) {
        return res.status(401).json({
            error: 'Access token required',
            success: false
        });
    }

    jwt.verify(token, process.env.JWT_SECRET, (err, user) => {
        if (err) {
            return res.status(403).json({
                error: 'Invalid or expired token',
                success: false
            });
        }

        req.user = {
            ...user,
            type: Number(user.type)
        };

        next();
    });
};

const authorizeAdmin = (req, res, next) => {
    if (Number(req.user.type) !== 1) {
        return res.status(403).json({
            error: 'Admin access required',
            success: false
        });
    }

    next();
};

const authorizeStaffOrAdmin = (req, res, next) => {
    const type = Number(req.user.type);

    if (type !== 1 && type !== 2) {
        return res.status(403).json({
            error: 'Staff or Admin access required',
            success: false
        });
    }

    next();
};

module.exports = {
    authenticateToken,
    authorizeAdmin,
    authorizeStaffOrAdmin
};