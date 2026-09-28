Lead CSV Import System

A full-stack Lead CSV Import System built with Laravel API + React + MySQL.

The application allows users to upload a CSV file containing lead records, processes the import asynchronously using Laravel Queues, validates each record, detects duplicate emails, stores successful leads, records failed rows, and sends a failed-records CSV report by email.

🚀 Tech Stack
Backend

Laravel

PHP

MySQL

Laravel Queue

Laravel Sanctum

REST API

Frontend

React

Vite

Tailwind CSS

Axios

Development

Git / GitHub

Postman

Mailpit / SMTP-compatible mail service

✨ Features

CSV file upload

Asynchronous CSV processing using Laravel Queue

Chunk-based processing for large CSV files

CSV header validation

Lead field validation

Email format validation

Required field validation

Duplicate email detection within the CSV

Duplicate email detection against existing database records

Successful and failed record tracking

Import progress tracking

Failed records stored separately

Failed records CSV generation

Failed records CSV sent through email

Import status API

RESTful API endpoints

React frontend

CORS configuration for React frontend

📁 Project Structure
lead-csv-import/
│
├── laravel/
│   ├── app/
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   │       └── LeadImportController.php
│   │   │
│   │   ├── Jobs/
│   │   │   ├── ProcessLeadCsv.php
│   │   │   └── GenerateFailedRecordsCsv.php
│   │   │
│   │   ├── Mail/
│   │   │   └── FailedRecordsMail.php
│   │   │
│   │   ├── Models/
│   │   │   ├── Lead.php
│   │   │   ├── LeadImport.php
│   │   │   └── LeadImportFailure.php
│   │   │
│   │   └── Services/
│   │       ├── LeadCsvImporter.php
│   │       └── LeadCsvImporter_old.php
│   │
│   ├── database/
│   │   └── migrations/
│   │
│   ├── resources/
│   │   └── views/
│   │       └── emails/
│   │           └── failed_records.blade.php
│   │
│   └── routes/
│       └── api.php
│
├── react/
│   ├── src/
│   │   ├── App.jsx
│   │   ├── App.css
│   │   └── main.jsx
│   │
│   ├── package.json
│   └── vite.config.js
│
└── start-dev.ps1

🗄️ Database
The application uses the following main tables:

leads
Stores successfully imported lead records.

Column	Description
id	Primary key
name	Lead name
email	Lead email
phone	Lead phone number
company	Company name
created_at	Creation timestamp
updated_at	Update timestamp

lead_imports
Tracks each CSV import.

Column	Description
id	Import ID
user_id	User who uploaded the file
original_filename	Original CSV filename
file_path	Stored CSV path
total_records	Total CSV records
processed_records	Records processed
success_count	Successfully imported records
failed_count	Failed records
status	pending / processing / completed / failed
error_message	Import-level error
started_at	Processing start time
completed_at	Processing completion time

lead_import_failures
Stores records that failed validation or duplicate checks.

It stores the original lead information along with the reason for failure.

🔄 Import Flow
React Frontend
      │
      │ CSV Upload
      ▼
Laravel API
      │
      │ Create Import Record
      ▼
Queue Job
      │
      │ ProcessLeadCsv
      ▼
LeadCsvImporter
      │
      ├── Validate CSV Header
      │
      ├── Validate Records
      │
      ├── Detect CSV Duplicates
      │
      ├── Check Database Duplicates
      │
      ├── Insert Valid Leads
      │
      └── Store Failed Records
      │
      ▼
GenerateFailedRecordsCsv
      │
      ▼
Failed Records CSV
      │
      ▼
Email Notification

🔌 API Endpoints
Upload CSV
POST /api/lead-imports

Upload a CSV file using multipart/form-data.

Example field:

file: contacts.csv

Example response:

{
    "message": "CSV uploaded successfully.",
    "import": {
        "id": 10,
        "original_filename": "test_users.csv",
        "status": "pending"
    }
}

Check Import Status
GET /api/lead-imports/{id}

Example response:

{
    "id": 10,
    "filename": "test_users.csv",
    "status": "completed",
    "total_records": 4,
    "processed_records": 4,
    "success_count": 4,
    "failed_count": 0,
    "error_message": null,
    "started_at": "2026-09-28T14:45:42.000000Z",
    "completed_at": "2026-09-28T14:45:42.000000Z"
}

Download Failed Records
GET /api/lead-imports/{id}/failed-records

This endpoint downloads the generated failed-records CSV for the selected import.

✅ CSV Validation
Each CSV record is validated before insertion.

Required fields:

name

email

phone

company

Email validation checks whether the email is valid.

Duplicate emails are handled in two ways:

1. Duplicate inside CSV
If the same email occurs more than once in the uploaded CSV, the duplicate record is marked as failed.

Duplicate email in CSV file.

2. Duplicate already present in database
If the email already exists in the leads table:

Duplicate email already exists.

Failed records are stored in lead_import_failures.

⚡ Queue Processing
CSV processing is handled asynchronously using Laravel Queue Jobs.

Start the queue worker:

php artisan queue:work

The main processing job is:

ProcessLeadCsv

After processing, if failed records exist, the following job is dispatched:

GenerateFailedRecordsCsv

This keeps the CSV upload request fast and moves heavy processing into the background.

📦 Chunk Processing
The importer processes records in chunks instead of loading the complete CSV into memory.

Current chunk size:

private const CHUNK_SIZE = 1000;

This approach is useful for handling large CSV files more efficiently.

🛠️ Backend Setup
Navigate to the Laravel project:

cd laravel

Install PHP dependencies:

composer install

Create environment file:

copy .env.example .env

Generate application key:

php artisan key:generate

Configure the database in .env.

Example:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lead_import
DB_USERNAME=root
DB_PASSWORD=

Run migrations:

php artisan migrate

📧 Mail Configuration
Configure the mail settings in .env.

For local development, an SMTP testing service such as Mailpit can be used.

Example:

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="admin@yopmail.com"
MAIL_FROM_NAME="Lead Import System"

▶️ Run Laravel
From the laravel directory:

php artisan serve

The Laravel API will normally be available at:

http://127.0.0.1:8000

Start the queue worker in another terminal:

php artisan queue:work

⚛️ Frontend Setup
Open another terminal:

cd react

Install dependencies:

npm.cmd install

Start the React development server:

npm.cmd run dev

Frontend:

http://localhost:5173

🌐 CORS
The Laravel API is configured to allow requests from the React development server:

http://localhost:5173

This allows the React frontend to communicate with the Laravel API during development.

🧪 Testing with Postman
Upload
POST http://127.0.0.1:8000/api/lead-imports

Body:

form-data

Field:

file = contacts.csv

Check Status
GET http://127.0.0.1:8000/api/lead-imports/10

Download Failed Records
GET http://127.0.0.1:8000/api/lead-imports/10/failed-records

📊 Example Import Result
For a CSV containing 15 records, the API can return:

{
    "id": 9,
    "filename": "contacts_dirty.csv",
    "status": "completed",
    "total_records": 15,
    "processed_records": 15,
    "success_count": 0,
    "failed_count": 15,
    "error_message": null
}

For a valid CSV containing 4 records:

{
    "id": 10,
    "filename": "test_users.csv",
    "status": "completed",
    "total_records": 4,
    "processed_records": 4,
    "success_count": 4,
    "failed_count": 0,
    "error_message": null
}

🔐 Environment & Security
Sensitive environment configuration is not committed to Git.

Before running the application, create your own:

laravel/.env

Do not commit database passwords, API keys, SMTP credentials, or other secrets.

🚀 Running the Complete Application
You need three processes during local development:

Terminal 1 — Laravel
cd laravel
php artisan serve

Terminal 2 — Queue Worker
cd laravel
php artisan queue:work

Terminal 3 — React
cd react
npm.cmd run dev

The project also includes:

start-dev.ps1

which can be used to simplify starting the development services.

📌 Important Notes
The queue worker must be running for background CSV processing.

Database migrations must be executed before the first import.

React communicates with the Laravel API through Axios.

Failed records are stored separately from successful leads.

Failed records can be downloaded through the API.

A failed-record email can contain the generated CSV attachment.

👨‍💻 Project Purpose
This project demonstrates:

REST API development

Laravel service-layer architecture

Queue-based background processing

CSV parsing and validation

Database transactions and duplicate handling

Chunk-based processing

Email notifications

React frontend integration

API testing with Postman

MySQL database design

Git/GitHub project management

📄 License
This project was created as a technical assignment/demo project.
