<?php
header('Content-Type: application/json');
$reviewsFile = 'data/reviews.json';
if (file_exists($reviewsFile)) {
   $json = file_get_contents($reviewsFile);
   $reviews = json_decode($json, true) ?? [];
   echo json_encode(['success' => true, 'reviews' => $reviews]);
} else {
   echo json_encode(['success' => true, 'reviews' => []]);
}
