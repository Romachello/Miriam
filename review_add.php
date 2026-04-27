<?php
session_start();
header('Content-Type: application/json');

$reviewsFile = 'data/reviews.json';

// Получить капчу (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_captcha') {
   $num1 = rand(1, 10);
   $num2 = rand(1, 10);
   $answer = $num1 + $num2;
   $captchaId = bin2hex(random_bytes(8));
   $_SESSION['captcha'][$captchaId] = $answer;
   echo json_encode(['question' => "$num1 + $num2 = ?", 'id' => $captchaId]);
   exit;
}

// Добавить отзыв (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $name = trim($_POST['review_name'] ?? '');
   $message = trim($_POST['review_message'] ?? '');
   $captchaAnswer = trim($_POST['captcha_answer'] ?? '');
   $captchaId = trim($_POST['captcha_id'] ?? '');

   if (empty($name) || empty($message)) {
      echo json_encode(['success' => false, 'error' => 'Имя и текст обязательны']);
      exit;
   }
   if (mb_strlen($message) > 1000) {
      echo json_encode(['success' => false, 'error' => 'Сообщение слишком длинное']);
      exit;
   }
   if (!isset($_SESSION['captcha'][$captchaId]) || $_SESSION['captcha'][$captchaId] != $captchaAnswer) {
      echo json_encode(['success' => false, 'error' => 'Неверный ответ капчи']);
      exit;
   }
   unset($_SESSION['captcha'][$captchaId]);

   // Читаем существующие отзывы
   $reviews = [];
   if (file_exists($reviewsFile)) {
      $json = file_get_contents($reviewsFile);
      $reviews = json_decode($json, true) ?? [];
   }

   $newReview = [
      'name' => htmlspecialchars($name),
      'message' => nl2br(htmlspecialchars($message)),
      'date' => date('d.m.Y H:i')
   ];
   array_unshift($reviews, $newReview);
   $reviews = array_slice($reviews, 0, 30); // не более 30

   if (file_put_contents($reviewsFile, json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
      echo json_encode(['success' => true]);
   } else {
      echo json_encode(['success' => false, 'error' => 'Не удалось сохранить отзыв. Проверьте права на папку data/']);
   }
   exit;
}
echo json_encode(['success' => false, 'error' => 'Некорректный запрос']);
