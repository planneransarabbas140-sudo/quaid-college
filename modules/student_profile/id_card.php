<?php
// File: modules/student_profile/id_card.php - Generate Student ID Card
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM students WHERE id = :id");
$stmt->execute([':id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    die('Student not found!');
}

if (getUserRole() === 'student' && (int)($student['user_id'] ?? 0) !== (int)getUserId()) {
    setFlashMessage('error', 'You are not allowed to view that ID card.');
    redirect('index.php');
}

$studentDisplayId = getStudentDisplayId($student);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID Card - <?php echo $student['first_name'] . ' ' . $student['last_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            .id-card { box-shadow: none; }
        }
        .id-card {
            width: 350px;
            margin: 20px auto;
            background: linear-gradient(135deg, #1a3c5e 0%, #2c5a7a 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .logo {
            text-align: center;
            margin-bottom: 15px;
        }
        .logo i {
            font-size: 40px;
            color: #c9a84c;
        }
        .photo {
            text-align: center;
            margin: 15px 0;
        }
        .photo img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid #c9a84c;
            object-fit: cover;
        }
        .details {
            margin-top: 15px;
        }
        .details p {
            margin: 5px 0;
            font-size: 12px;
        }
        .details strong {
            color: #c9a84c;
        }
        .signature {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.3);
            padding-top: 10px;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="no-print text-center mt-4">
        <button onclick="window.print()" class="btn btn-primary">Print ID Card</button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>
    
    <div class="id-card">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h6>Quaid-e-Azam Group of Colleges</h6>
            <small>Student Identity Card</small>
        </div>
        
        <div class="photo">
            <?php 
            $photo_path = '../../uploads/students/photos/' . ($student['photo'] ?? '');
            $photo_exists = !empty($student['photo']) && file_exists(dirname(__FILE__) . '/../../uploads/students/photos/' . $student['photo']);
            ?>
            <img src="<?= $photo_exists ? $photo_path : "../../assets/images/default-student.png" ?>"
                 alt="Student Photo"
                 style="width:100px;height:100px;object-fit:cover;border-radius:50%;border:3px solid #4ec2b5;">
        </div>
        
        <div class="details">
            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($studentDisplayId); ?></p>
            <p><strong>Name:</strong> <?php echo $student['first_name'] . ' ' . $student['last_name']; ?></p>
            <p><strong>Class/Section:</strong> <?php echo $student['class'] . '-' . $student['section']; ?></p>
            <p><strong>Roll Number:</strong> <?php echo $student['roll_number']; ?></p>
            <p><strong>Blood Group:</strong> <?php echo $student['blood_group']; ?></p>
            <p><strong>Guardian:</strong> <?php echo $student['guardian_name']; ?></p>
            <p><strong>Emergency:</strong> <?php echo $student['emergency_contact']; ?></p>
        </div>
        
        <div class="signature">
            <p>Valid for Academic Year <?php echo getCurrentAcademicYear(); ?></p>
            <p>Principal's Signature</p>
        </div>
    </div>
    
    <script src="https://kit.fontawesome.com/your-kit.js"></script>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        }
    </script>
</body>
</html>
