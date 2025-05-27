CREATE DATABASE jaxe_ops;
   USE jaxe_ops;

-- USERS TABLE
CREATE TABLE users (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
email VARCHAR(100) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
role ENUM('communication_manager', 'content_creator', 'designer', 'videographer', 'community_manager', 'admin') NOT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- CLIENTS TABLE
CREATE TABLE clients (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
active_contract BOOLEAN DEFAULT TRUE,
social_networks TEXT,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- PROJECTS TABLE
CREATE TABLE projects (
id INT AUTO_INCREMENT PRIMARY KEY,
client_id INT NOT NULL,
month_year VARCHAR(7) NOT NULL, -- Format: MM-YYYY
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- PUBLICATIONS TABLE
CREATE TABLE publications (
id INT AUTO_INCREMENT PRIMARY KEY,
project_id INT NOT NULL,
general_theme VARCHAR(255),
specific_theme VARCHAR(255),
publication_type ENUM('visual', 'video', 'carousel', 'reel', 'other'),
publication_date DATETIME,
message TEXT,
objectives TEXT,
instructions TEXT,
description TEXT,
content_url TEXT,
source_file_url TEXT,
status ENUM('pending', 'published', 'validated') DEFAULT 'pending',
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- TASKS TABLE
CREATE TABLE tasks (
id INT AUTO_INCREMENT PRIMARY KEY,
publication_id INT NOT NULL,
assignee_id INT NOT NULL,
description TEXT,
status ENUM('pending', 'in_progress', 'blocked', 'completed' 'unvalidated' 'validated') DEFAULT 'pending',
created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
due_date DATE,
FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
);

-- CONVERSATIONS TABLE
CREATE TABLE conversations (
id INT AUTO_INCREMENT PRIMARY KEY,
task_id INT NOT NULL,
author_id INT NOT NULL,
message TEXT NOT NULL,
sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- TASK VALIDATORS TABLE
CREATE TABLE task_validators (
id INT AUTO_INCREMENT PRIMARY KEY,
task_id INT NOT NULL,
user_id INT NOT NULL,
validated BOOLEAN DEFAULT FALSE,
validated_at DATETIME,
FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- RESULTS TABLE
CREATE TABLE results (
id INT AUTO_INCREMENT PRIMARY KEY,
publication_id INT NOT NULL,
platform VARCHAR(50),
collection_date DATE,
views INT DEFAULT 0,
reach INT DEFAULT 0,
shares INT DEFAULT 0,
comments INT DEFAULT 0,
likes INT DEFAULT 0,
link_clicks INT DEFAULT 0,
FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
);