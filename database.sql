CREATE TABLE personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    DOB DATE,
    contact_number VARCHAR(20),
    role ENUM('Commander','HR Manager','Medical Officer','Training Department','Ground Staff'),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT,
    skill_name VARCHAR(100),
    skill_level ENUM('Beginner','Intermediate','Advanced','Expert'),
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
);

CREATE TABLE medical_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT,
    record_date DATE,
    description TEXT,
    document_path VARCHAR(255),
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
);

CREATE TABLE training_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT,
    training_name VARCHAR(255),
    start_date DATE,
    end_date DATE,
    result VARCHAR(50),
    document_path VARCHAR(255),
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
);

CREATE TABLE deployments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT,
    unit VARCHAR(255),
    start_date DATE,
    end_date DATE,
    location VARCHAR(255),
    description TEXT,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT,
    user_id INT, 
    action VARCHAR(255),
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
);
