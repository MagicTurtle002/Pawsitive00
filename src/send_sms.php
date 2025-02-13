<?php
require '../config/dbh.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'] ?? null;
    $petName = $_POST['pet_name'] ?? 'Unknown Pet';
    $ownerName = $_POST['owner_name'] ?? 'Owner';
    $followUpDate = $_POST['follow_up_date'] ?? 'Unknown Date';
    $notes = $_POST['notes'] ?? 'No notes provided';

    if (!$phone) {
        echo json_encode(['status' => 'error', 'message' => 'Phone number is missing.']);
        exit();
    }

    $access_token = "YOUR_GLOBE_ACCESS_TOKEN";  // Replace with actual access token
    $shortcode = "2158XXXX";  // Replace with your Globe Labs Short Code

    $message = "Hello $ownerName, this is a reminder from Pawsitive. 
    $petName has a follow-up on $followUpDate. Notes: $notes.";

    $url = "https://devapi.globelabs.com.ph/smsmessaging/v1/outbound/$shortcode/requests";

    $data = [
        "outboundSMSMessageRequest" => [
            "senderAddress" => $shortcode,
            "outboundSMSTextMessage" => ["message" => $message],
            "address" => $phone
        ]
    ];

    $options = [
        "http" => [
            "header" => [
                "Content-Type: application/json",
                "Authorization: Bearer $access_token"
            ],
            "method" => "POST",
            "content" => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response) {
        echo json_encode(['status' => 'success', 'message' => 'Reminder sent successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send SMS.']);
    }
}
?>