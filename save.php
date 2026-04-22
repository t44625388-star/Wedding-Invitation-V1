<?php
// Sirf POST request ko accept karein
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Generate a Unique ID for this specific RSVP (Prevents deletion bugs in Admin Panel)
    $id = uniqid('rsvp_');
    
    // Sanitize Inputs (Protects from XSS Hackers)
    $name = htmlspecialchars($_POST['name'] ?? 'Unknown');
    $email = htmlspecialchars($_POST['email'] ?? 'No Email');
    $attendance = htmlspecialchars($_POST['attendance'] ?? '');
    $guests = htmlspecialchars($_POST['guests'] ?? '0');
    $dietary = htmlspecialchars($_POST['dietary'] ?? '');
    
    date_default_timezone_set("Asia/Kolkata"); 
    $timestamp = date("d-M-Y h:i A");

    // Naya data with ID
    $new_rsvp = [
        "id" => $id,
        "name" => $name,
        "email" => $email,
        "attendance" => $attendance,
        "guests" => $guests,
        "message" => $dietary,
        "date" => $timestamp
    ];

    $file = 'data.json';

    // ===============================================
    // SECURE FILE WRITING WITH EXCLUSIVE LOCK (flock)
    // ===============================================
    $fp = fopen($file, 'c+'); // Open file for reading & writing
    
    if (flock($fp, LOCK_EX)) { // Get exclusive lock so no one else writes at the same millisecond
        
        $filesize = filesize($file);
        if ($filesize > 0) {
            $current_data = fread($fp, $filesize);
            $array_data = json_decode($current_data, true);
        } else {
            $array_data = [];
        }
        
        if (!is_array($array_data)) $array_data = [];
        
        $array_data[] = $new_rsvp;
        $final_data = json_encode($array_data, JSON_PRETTY_PRINT);
        
        ftruncate($fp, 0); // Purana data erase karein
        rewind($fp);      // Cursor starting mein set karein
        fwrite($fp, $final_data); // Naya complete JSON write karein
        
        flock($fp, LOCK_UN); // Release the lock
        $success = true;
    } else {
        $success = false;
    }
    fclose($fp);

    // ===============================================
    // RESPONSE (Alert & Redirect back to Master SPA File)
    // ===============================================
    if($success) {
        echo "<script>
            alert('Aapka RSVP successfully receive ho gaya hai! Thank You.');
            window.location.href = 'index.html'; 
        </script>";
    } else {
        echo "<script>
            alert('Server is busy right now. Please try submitting again.');
            window.history.back();
        </script>";
    }
} else {
    // Agar manually save.php kholne ki koshish kare, to bhaga do.
    header("Location: index.html");
    exit();
}
?>