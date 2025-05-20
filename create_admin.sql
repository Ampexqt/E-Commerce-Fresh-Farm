-- SQL script to create an admin user
-- This creates an admin with:
-- Username: admin
-- Email: admin@freshfarm.com
-- Password: Admin@123 (hashed with bcrypt)
-- Role: admin

INSERT INTO users 
(username, first_name, last_name, email, phone_number, password, role, active) 
VALUES 
('admin', 'Admin', 'User', 'admin@freshfarm.com', '+1234567890', 
'$2y$10$D9JgzEwOiJwBdtpk9Dq15.CXD6WXrX11/ybpjpkQ0ncdpb.mZdz2W', 'admin', 1);

-- Note: The password hash is for 'Admin@123'
-- If you want a different password, generate a new hash with password_hash() in PHP 