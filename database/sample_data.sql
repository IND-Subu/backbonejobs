-- Sample Data for BackboneJobs
-- Run this after creating the main schema for testing

-- Insert Sample Employers
INSERT INTO employers (company_name, contact_person, email, phone, whatsapp_number, password, company_address, city, state, is_verified, is_active) VALUES
('Prestige Group', 'Rajesh Kumar', 'hr@prestigegroup.com', '9876543210', '9876543210', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '123 MG Road', 'Mumbai', 'Maharashtra', 1, 1),
('DLF Properties', 'Sunita Sharma', 'careers@dlf.com', '9876543211', '9876543211', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '456 Cyber City', 'Bangalore', 'Karnataka', 1, 1),
('Godrej Industries', 'Amit Patel', 'jobs@godrej.com', '9876543212', '9876543212', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '789 Andheri West', 'Mumbai', 'Maharashtra', 1, 1),
('Brigade Group', 'Priya Reddy', 'hr@brigade.com', '9876543213', '9876543213', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '321 MG Road', 'Bangalore', 'Karnataka', 1, 1),
('Phoenix Mills', 'Vikram Singh', 'recruitment@phoenix.com', '9876543214', '9876543214', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '555 Lower Parel', 'Mumbai', 'Maharashtra', 1, 1);

-- Note: Default password for all sample employers is "password123"

-- Insert Sample Jobs
INSERT INTO jobs (employer_id, category_id, title, company_name, description, requirements, responsibilities, job_type, experience_required, education_required, salary_min, salary_max, location, city, state, work_timings, benefits, vacancies, contact_email, contact_phone, whatsapp_number, status) VALUES

-- Security Guard Jobs
(1, 1, 'Security Guard - Night Shift', 'Prestige Group', 'Looking for experienced security guards for our residential complex in Mumbai. Must be alert and responsible.', 'Prior security experience preferred. Good physical fitness. Clean background check.', 'Monitor premises, check visitors, maintain security logs, respond to emergencies.', 'Full-Time', '1-2 years', '10th Pass', 15000, 20000, 'Andheri West, Mumbai', 'Mumbai', 'Maharashtra', '8 PM - 8 AM', 'PF, ESI, Uniform provided', 5, 'security@prestigegroup.com', '9876543210', '9876543210', 'Active'),

(2, 1, 'Security Supervisor', 'DLF Properties', 'Seeking a security supervisor to manage our security team at corporate office in Bangalore.', '3-5 years security experience. Leadership skills. Knowledge of CCTV systems.', 'Supervise security guards, coordinate with management, handle security protocols.', 'Full-Time', '3-5 years', '12th Pass', 25000, 30000, 'Whitefield, Bangalore', 'Bangalore', 'Karnataka', '9 AM - 9 PM', 'PF, ESI, Medical Insurance', 2, 'hr@dlf.com', '9876543211', '9876543211', 'Active'),

-- Housekeeping Jobs
(3, 2, 'Housekeeping Staff', 'Godrej Industries', 'Multiple openings for housekeeping staff at our office premises. Immediate joining.', 'Basic cleaning knowledge. Good health. Punctual.', 'Office cleaning, washroom maintenance, waste disposal, ensuring cleanliness.', 'Full-Time', 'Fresher', 'Below 10th', 12000, 15000, 'Andheri, Mumbai', 'Mumbai', 'Maharashtra', '8 AM - 5 PM', 'Meals, Uniform, PF', 10, 'hr@godrej.com', '9876543212', '9876543212', 'Active'),

(4, 2, 'Housekeeping Supervisor', 'Brigade Group', 'Experienced housekeeping supervisor needed for residential project in Bangalore.', '2+ years housekeeping experience. Team management skills.', 'Manage housekeeping team, ensure quality standards, inventory management.', 'Full-Time', '3-5 years', '10th Pass', 18000, 22000, 'Koramangala, Bangalore', 'Bangalore', 'Karnataka', '7 AM - 4 PM', 'PF, ESI, Accommodation', 1, 'careers@brigade.com', '9876543213', '9876543213', 'Active'),

-- MST Jobs
(5, 3, 'Electrician', 'Phoenix Mills', 'Looking for skilled electrician for maintenance work at shopping mall.', 'ITI in Electrical. 2+ years experience. Knowledge of commercial electrical systems.', 'Electrical maintenance, troubleshooting, installations, safety compliance.', 'Full-Time', '3-5 years', 'Diploma', 20000, 25000, 'Lower Parel, Mumbai', 'Mumbai', 'Maharashtra', '9 AM - 6 PM', 'PF, ESI, Medical, Tools provided', 2, 'jobs@phoenix.com', '9876543214', '9876543214', 'Active'),

(1, 3, 'Plumber', 'Prestige Group', 'Experienced plumber needed for residential complex maintenance.', 'Plumbing experience required. Knowledge of modern fixtures.', 'Plumbing repairs, installations, maintenance of water systems.', 'Full-Time', '1-2 years', '10th Pass', 18000, 22000, 'Bandra, Mumbai', 'Mumbai', 'Maharashtra', '8 AM - 6 PM', 'PF, ESI, Tools', 2, 'maint@prestigegroup.com', '9876543210', '9876543210', 'Active'),

(2, 3, 'HVAC Technician', 'DLF Properties', 'AC technician for office building maintenance. Must have relevant certification.', 'ITI/Diploma in Refrigeration. HVAC experience. Good troubleshooting skills.', 'AC maintenance, repairs, installations, preventive maintenance.', 'Full-Time', '3-5 years', 'Diploma', 22000, 28000, 'Electronic City, Bangalore', 'Bangalore', 'Karnataka', '9 AM - 6 PM', 'PF, Medical, Tools, Training', 3, 'technical@dlf.com', '9876543211', '9876543211', 'Active'),

-- Facility Management
(3, 4, 'Facility Manager', 'Godrej Industries', 'Facility manager for corporate office. Must have experience in managing building operations.', 'Degree/Diploma in Facility Management. 5+ years experience. Good communication skills.', 'Overall facility management, vendor coordination, maintenance scheduling.', 'Full-Time', '5+ years', 'Graduate', 35000, 45000, 'Vikhroli, Mumbai', 'Mumbai', 'Maharashtra', '9 AM - 7 PM', 'PF, Medical Insurance, Performance Bonus', 1, 'fm@godrej.com', '9876543212', '9876543212', 'Active'),

-- Helper Jobs
(4, 5, 'Office Helper', 'Brigade Group', 'Office helper needed for daily office support activities.', 'Basic education. Good physical health. Willingness to learn.', 'Office cleaning, tea/coffee service, filing support, errands.', 'Full-Time', 'Fresher', 'Below 10th', 10000, 12000, 'Marathahalli, Bangalore', 'Bangalore', 'Karnataka', '9 AM - 6 PM', 'PF, Meals', 3, 'admin@brigade.com', '9876543213', '9876543213', 'Active'),

-- Driver Jobs
(5, 6, 'Company Driver', 'Phoenix Mills', 'Need experienced driver with clean driving record. Must have light motor vehicle license.', 'Valid driving license. 3+ years driving experience. Good knowledge of Mumbai routes.', 'Drive company vehicle, maintain vehicle cleanliness, ensure timely pickups/drops.', 'Full-Time', '3-5 years', '10th Pass', 18000, 22000, 'Worli, Mumbai', 'Mumbai', 'Maharashtra', '8 AM - 8 PM', 'PF, Fuel, Maintenance', 2, 'transport@phoenix.com', '9876543214', '9876543214', 'Active'),

(1, 6, 'Delivery Driver', 'Prestige Group', 'Delivery driver for materials transport between our project sites.', 'Driving license. Knowledge of Mumbai routes. Ability to lift packages.', 'Deliver materials to sites, maintain delivery logs, vehicle maintenance.', 'Full-Time', '1-2 years', '10th Pass', 15000, 18000, 'Mulund, Mumbai', 'Mumbai', 'Maharashtra', '7 AM - 4 PM', 'PF, Fuel allowance', 4, 'logistics@prestigegroup.com', '9876543210', '9876543210', 'Active'),

-- Pantry Staff
(2, 8, 'Pantry Boy', 'DLF Properties', 'Pantry staff needed for corporate office pantry management.', 'Experience in pantry/kitchen work. Good hygiene. Food handling knowledge.', 'Tea/coffee preparation, pantry cleaning, inventory management, serving.', 'Full-Time', 'Less than 1 year', 'Below 10th', 12000, 15000, 'MG Road, Bangalore', 'Bangalore', 'Karnataka', '8 AM - 5 PM', 'Meals, Uniform, PF', 2, 'admin@dlf.com', '9876543211', '9876543211', 'Active'),

-- Part-Time Jobs
(3, 2, 'Part-Time Housekeeping', 'Godrej Industries', 'Part-time housekeeping staff for weekend shifts only.', 'Basic cleaning experience. Available on weekends.', 'Weekend cleaning duties, maintaining cleanliness standards.', 'Part-Time', 'Fresher', 'Any', 8000, 10000, 'Goregaon, Mumbai', 'Mumbai', 'Maharashtra', 'Weekends 8 AM - 4 PM', 'Paid weekly', 5, 'weekend@godrej.com', '9876543212', '9876543212', 'Active'),

-- Contract Jobs
(4, 1, 'Event Security', 'Brigade Group', 'Contract basis security for events and exhibitions.', 'Security experience. Available for events. Good communication.', 'Event security, crowd management, access control.', 'Contract', '1-2 years', '10th Pass', 800, 1200, 'Various locations, Bangalore', 'Bangalore', 'Karnataka', 'Event based', 'Per day payment', 20, 'events@brigade.com', '9876543213', '9876543213', 'Active'),

-- Office Boy
(5, 7, 'Office Boy', 'Phoenix Mills', 'Office boy for head office support activities.', 'Basic education. Good manners. Presentable.', 'Document delivery, office errands, reception support, general assistance.', 'Full-Time', 'Fresher', '10th Pass', 11000, 14000, 'Parel, Mumbai', 'Mumbai', 'Maharashtra', '9 AM - 6 PM', 'PF, Meals, Uniform', 2, 'admin@phoenix.com', '9876543214', '9876543214', 'Active');

-- Insert Sample Users (Job Seekers)
INSERT INTO users (name, email, phone, password, current_location, experience_years, education, is_active) VALUES
('Ramesh Verma', 'ramesh.verma@email.com', '8765432101', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mumbai, Maharashtra', 3, '10th Pass', 1),
('Suresh Yadav', 'suresh.y@email.com', '8765432102', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bangalore, Karnataka', 1, '12th Pass', 1),
('Kavita Devi', 'kavita.d@email.com', '8765432103', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mumbai, Maharashtra', 0, 'Below 10th', 1),
('Mohan Kumar', 'mohan.k@email.com', '8765432104', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bangalore, Karnataka', 5, 'Diploma', 1),
('Anjali Singh', 'anjali.s@email.com', '8765432105', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mumbai, Maharashtra', 2, '10th Pass', 1);

-- Note: Default password for all sample users is "password123"

-- Update job statistics
UPDATE jobs SET views = FLOOR(RAND() * 100) + 20 WHERE status = 'Active';
