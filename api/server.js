const express = require('express');
const mysql = require('mysql2');
const cors = require('cors');
const morgan = require('morgan');
const dotenv = require('dotenv');
const path = require('path');

// Load environment variables
dotenv.config();

// Initialize Express app
const app = express();

// Import route modules
const authRoutes = require('./routes/auth');
const housesRoutes = require('./routes/houses');
const tenantsRoutes = require('./routes/tenants');
const paymentsRoutes = require('./routes/payments');
const reportsRoutes = require('./routes/reports');
const usersRoutes = require('./routes/users');
const categoriesRoutes = require('./routes/categories');
const desktopRoutes = require('./routes/desktop');

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cors({
    origin: process.env.ALLOWED_ORIGINS?.split(',') || '*',
    credentials: true
}));
app.use(morgan('dev'));

// Database connection pool
const db = mysql.createPool({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'house_rental_db',
    port: process.env.DB_PORT || 3307, // 👈 ADD THIS LINE
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

// Make db available to routes
app.use((req, res, next) => {
    req.db = db;
    next();
});

// API Routes
app.use('/api/auth', authRoutes);
app.use('/api/houses', housesRoutes);
app.use('/api/tenants', tenantsRoutes);
app.use('/api/payments', paymentsRoutes);
app.use('/api/reports', reportsRoutes);
app.use('/api/users', usersRoutes);
app.use('/api/categories', categoriesRoutes);
app.use('/api/desktop', desktopRoutes);

// Health check endpoint
app.get('/api/health', (req, res) => {
    res.json({ 
        status: 'OK', 
        timestamp: new Date(),
        message: 'House Rental API is running'
    });
});

// API Documentation endpoint
app.get('/api/docs', (req, res) => {
    res.json({
        name: 'House Rental Management API',
        version: '1.0.0',
        endpoints: {
            auth: {
                login: 'POST /api/auth/login',
                register: 'POST /api/auth/register',
                logout: 'POST /api/auth/logout',
                me: 'GET /api/auth/me'
            },
            houses: {
                list: 'GET /api/houses',
                getById: 'GET /api/houses/:id',
                create: 'POST /api/houses',
                update: 'PUT /api/houses/:id',
                delete: 'DELETE /api/houses/:id'
            },
            tenants: {
                list: 'GET /api/tenants',
                getById: 'GET /api/tenants/:id',
                create: 'POST /api/tenants',
                update: 'PUT /api/tenants/:id',
                delete: 'DELETE /api/tenants/:id'
            },
            payments: {
                list: 'GET /api/payments',
                getByTenant: 'GET /api/payments/tenant/:tenantId',
                create: 'POST /api/payments',
                update: 'PUT /api/payments/:id',
                delete: 'DELETE /api/payments/:id'
            },
            reports: {
                balances: 'GET /api/reports/balances',
                monthlyPayments: 'GET /api/reports/monthly-payments'
            },
            users: {
                list: 'GET /api/users',
                create: 'POST /api/users',
                update: 'PUT /api/users/:id',
                delete: 'DELETE /api/users/:id'
            },
            categories: {
                list: 'GET /api/categories',
                create: 'POST /api/categories',
                update: 'PUT /api/categories/:id',
                delete: 'DELETE /api/categories/:id'
            }
        }
    });
});

// Error handling middleware
app.use((err, req, res, next) => {
    console.error(err.stack);
    res.status(500).json({ 
        error: 'Something went wrong!',
        message: process.env.NODE_ENV === 'development' ? err.message : undefined
    });
});

// 404 handler
app.use((req, res) => {
    res.status(404).json({ error: 'Endpoint not found' });
});

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`🚀 House Rental API running on http://localhost:${PORT}`);
    console.log(`📚 API Documentation: http://localhost:${PORT}/api/docs`);
});