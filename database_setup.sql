-- Asset Categories Table
CREATE TABLE IF NOT EXISTS asset_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sample categories
INSERT INTO asset_categories (category_name, description) VALUES
('Laptops', 'Portable computers and notebooks'),
('Desktops', 'Desktop computers and workstations'),
('Monitors', 'Display monitors and screens'),
('Printers', 'Printers and multifunction devices'),
('Furniture', 'Desks, chairs, cabinets, and office furniture'),
('Networking', 'Routers, switches, and networking equipment'),
('Software Licenses', 'Software licenses and subscriptions'),
('Mobile Devices', 'Smartphones and tablets'),
('Peripheral Devices', 'Keyboards, mice, headphones, and other peripherals'),
('Other', 'Other assets and equipment');
