<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['auth_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['auth_user_id'];
$response = ['success' => false, 'message' => ''];

try {
    // Handle file upload if a new image is provided
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profiles/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $new_filename;
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
        if (!in_array(strtolower($file_extension), $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, JPEG, PNG, WEBP, and AVIF files are allowed.');
        }
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)) {
            $profile_image = $target_path;
        }
    }
    
    // Get other form data
    $full_name = $_POST['full_name'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $gender = $_POST['gender'] ?? '';
    
    // Update the database
    if ($profile_image) {
        $query = "UPDATE user_profile SET 
                 full_name = ?, 
                 phone_number = ?, 
                 dob = ?, 
                 gender = ?,
                 profile_image = ?
                 WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssi", $full_name, $phone_number, $dob, $gender, $profile_image, $user_id);
    } else {
        $query = "UPDATE user_profile SET 
                 full_name = ?, 
                 phone_number = ?, 
                 dob = ?, 
                 gender = ?
                 WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssi", $full_name, $phone_number, $dob, $gender, $user_id);
    }
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully';
    } else {
        throw new Exception('Failed to update profile');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>