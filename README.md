# ID-verification-system
A system providing students an interface to match their details with their facial recognition to avoid error and mismatch of details

# KMTC Student Photo Verification System

A comprehensive web-based system for managing student passport photo assignments at Kenya Medical Training College.

## 🚀 System Overview

This system allows students to:
- Verify their identity using Student ID
- Browse available passport photos
- Select and assign their own photo
- Automatically rename photos to student numbers

Administrators can:
- Import student data from Excel/CSV
- Import passport photos in bulk
- Manage student records and photo assignments
- Reset incorrect photo assignments

## 📁 Project Structure
kmtc-photo-system/
├── index.html # Main student interface
├── api/ # Backend API endpoints
│ ├── config.php # Database configuration
│ ├── verify_student.php # Student ID verification
│ ├── get_photos.php # Get available photos (with pagination)
│ └── assign_photo.php # Assign photo to student
├── scripts/ # Admin management scripts
│ ├── import_excel.php # Import student data from CSV
│ ├── import_photos.php # Import/manage passport photos
│ └── manage_students.php # Student management interface
└── uploads/ # File storage
├── pending/ # Original unassigned photos
└── assigned/ # Renamed student photos

text

## 🛠️ Installation & Setup

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Web browser (Chrome, Firefox, Safari)

### Step 1: Setup Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create new database: `kmtc_photo_system`
3. Run the SQL script provided in the setup instructions

### Step 2: File Structure
1. Extract project files to: `C:\xampp\htdocs\kmtc-photo-system\`
2. Ensure folder permissions allow file uploads

### Step 3: Configuration
1. Edit `api/config.php` if using different database credentials
2. Ensure `uploads/` folder has write permissions

## 📊 System Usage Guide

### For Students (Photo Selection)

1. **Access System**: Go to `http://localhost/kmtc-photo-system/`
2. **Enter Student ID**: Type your student number and click "Verify ID"
3. **Verify Details**: Confirm your name and registration number
4. **Browse Photos**: Use pagination to browse all available photos
5. **Select Photo**: Click on your passport photo
6. **Confirm Selection**: Review and confirm your choice
7. **Completion**: System renames photo to your student number

### For Administrators

#### Importing Student Data

**Method 1: CSV Import**
1. Go to: `http://localhost/kmtc-photo-system/scripts/import_excel.php`
2. Prepare CSV file with columns:
   - `Student ID` (or `student_no`, `student_id`)
   - `Full Name` (or `name`, `full_name`) 
   - `Registration No` (or `id`, `reg_no`)
3. Download sample CSV to verify format
4. Upload and import

**Method 2: Manual Database Entry**
- Use phpMyAdmin to insert records directly into `students` table

#### Importing Photos

**Method 1: Direct Upload**
1. Go to: `http://localhost/kmtc-photo-system/scripts/import_photos.php`
2. Click "Upload Photos" tab
3. Select multiple photos from computer
4. Photos are automatically imported to database

**Method 2: Folder Import**
1. Place photos in `uploads/pending/` folder
2. Name photos as: `IMG_001.jpg`, `IMG_002.jpg`, etc.
3. Go to import page and click "Import from Folder"

#### Managing Students & Photos

1. Go to: `http://localhost/kmtc-photo-system/scripts/manage_students.php`
2. View all students and their photo status
3. **Reset Photo**: Returns photo to available pool, allows student to choose again
4. **Delete Student**: Removes student record entirely

## 🔧 Management Features

### Photo Assignment Reset
- **Use Case**: Student selected wrong photo
- **Action**: Click "Reset Photo" in management interface
- **Result**: 
  - Photo returns to available pool
  - Student can choose again
  - Assigned photo file is deleted

### Student Record Deletion
- **Use Case**: Remove student entirely
- **Action**: Click "Delete" in management interface  
- **Result**:
  - Student record removed
  - Any assigned photo is freed
  - Photo file is deleted

### Bulk Operations
- Import hundreds of students via CSV
- Upload multiple photos simultaneously
- View real-time statistics and progress

## 📝 File Naming Conventions

### Student Photos (Before Assignment)
- Format: `IMG_001.jpg`, `IMG_002.jpg`, etc.
- Supported: JPG, JPEG, PNG, GIF
- Photo ID is derived from filename (without extension)

### Student Photos (After Assignment)  
- Format: `[StudentNumber].jpg` (e.g., `CS00123.jpg`)
- Automatically renamed during assignment
- Stored in `uploads/assigned/` folder

## 🗃️ Database Schema

### Students Table
```sql
student_id      VARCHAR(20)   PRIMARY KEY
full_name       VARCHAR(100)  NOT NULL
registration_no VARCHAR(20)   NOT NULL  
assigned_photo_id VARCHAR(20) NULL
photo_file_path VARCHAR(255)  NULL
Unassigned Photos Table
sql
photo_id     VARCHAR(20)   PRIMARY KEY  
file_path    VARCHAR(255)  NOT NULL
file_size    INT
file_type    VARCHAR(50)
assigned     BOOLEAN       DEFAULT FALSE
🔒 System Features
Photo Pagination: Handles large numbers of photos efficiently

Duplicate Prevention: Students cannot assign multiple photos

Error Handling: Comprehensive error messages and recovery

Progress Tracking: Visual progress indicators

Mobile Responsive: Works on all device sizes

Data Validation: Client and server-side validation

🚨 Troubleshooting
Common Issues
Photos not displaying:

Check file exists in uploads/pending/

Verify database file_path is correct

Check folder permissions

Import errors:

Ensure CSV format is correct

Check column names match expected format

Verify database connection

Student not found:

Check student exists in database

Verify Student ID case sensitivity

Ensure student hasn't already assigned photo

File Permissions
uploads/ folder must be writable

Ensure Apache has permission to create files

Check disk space availability

📞 Support
For technical support or issues:

Check this README first

Verify all setup steps are completed

Check XAMPP error logs

Contact system administrator

🎯 Typical Workflow
Setup: Import students and photos

Student Access: Students select their photos

Verification: System renames and organizes photos

Management: Admin handles corrections as needed

Completion: All photos renamed to student numbers

System Version: 1.0
Last Updated: 2024
Developed For: Kenya Medical Training College

text

## 3. Quick Access URLs

**Main System**: `http://localhost/kmtc-photo-system/`  
**Student Management**: `http://localhost/kmtc-photo-system/scripts/manage_students.php`  
**Photo Import**: `http://localhost/kmtc-photo-system/scripts/import_photos.php`  
**Student Import**: `http://localhost/kmtc-photo-system/scripts/import_excel.php`

## Key Management Features Added:

1. **✅ Reset Photo Assignment** - Returns photo to available pool
2. **✅ Delete Student** - Complete removal with cleanup
3. **✅ Search Functionality** - Find students quickly
4. **✅ Statistics Dashboard** - Overview of system status
5. **✅ Confirmation Dialogs** - Prevent accidental actions
6. **✅ Comprehensive README** - Complete documentation

Now you have a full management system where you can:
- See all students and their photo status
- Reset photos if students choose wrong ones
- Delete students entirely
- Search and filter students
- Get system statistics

The README provides complete instructions for both administrators and students!
