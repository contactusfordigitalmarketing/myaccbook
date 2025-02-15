-- Create the database
CREATE DATABASE IF NOT EXISTS myaccbook;

-- Use the database
USE myaccbook;

-- Create the table
CREATE TABLE IF NOT EXISTS entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    remarks TEXT NULL,
    date_time DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample data
INSERT INTO entries (type, amount, remarks) VALUES 
('Income', 5000.00, 'Salary for January'),
('Expense', 1200.50, 'Grocery Shopping'),
('Income', 3000.00, 'Freelance Work Payment'),
('Expense', 800.75, 'Electricity Bill'),
('Expense', 1500.00, 'Car Maintenance');

