<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // не показываем ошибки пользователю, но логируем

// ------------------- НАСТРОЙКИ -------------------
// Если вы хотите использовать SMTP (рекомендуется), укажите данные:
$useSMTP = true;   // true - использовать SMTP, false - использовать mail()
$smtpConfig = [
   'host'     => 'smtp.gmail.com',
   'username' => 'gorovatskaya.m@gmail.com',   // ваша почта
   'password' => '**** ваш пароль приложения ****', // пароль приложения Gmail
   'port'     => 587,
   'encryption' => 'tls'
];
$toEmail = 'gorovatskaya.m@gmail.com';
$backupFile = __DIR__ . '/data/leads_backup.json'; // резервная папка

// Если нет PHPMailer, либо переключитесь на mail(), либо установите
$phpmailerPath = __DIR__ . '/vendor/autoload.php'; // путь к автозагрузке Composer

// -------------------------------------------------

function sendWithPHPMailer($to, $subject, $body, $fromEmail, $fromName = 'Мириам Горовацкая')
{
   global $smtpConfig, $phpmailerPath;

   if (!file_exists($phpmailerPath)) {
      return ['success' => false, 'message' => 'PHPMailer not installed. Run "composer require phpmailer/phpmailer"'];
   }
   require_once $phpmailerPath;
   $mail = new PHPMailer\PHPMailer\PHPMailer(true);
   try {
      $mail->isSMTP();
      $mail->Host       = $smtpConfig['host'];
      $mail->SMTPAuth   = true;
      $mail->Username   = $smtpConfig['username'];
      $mail->Password   = $smtpConfig['password'];
      $mail->SMTPSecure = $smtpConfig['encryption'];
      $mail->Port       = $smtpConfig['port'];
      $mail->setFrom($fromEmail, $fromName);
      $mail->addAddress($to);
      $mail->isHTML(false);
      $mail->Subject = $subject;
      $mail->Body    = $body;
      $mail->send();
      return ['success' => true];
   } catch (Exception $e) {
      return ['success' => false, 'message' => $mail->ErrorInfo];
   }
}

function sendWithNativeMail($to, $subject, $body, $fromEmail)
{
   $headers = "From: $fromEmail\r\n";
   $headers .= "Reply-To: $fromEmail\r\n";
   $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
   return mail($to, $subject, $body, $headers);
}

function saveLeadToFile($data)
{
   global $backupFile;
   $leads = [];
   if (file_exists($backupFile)) {
      $content = file_get_contents($backupFile);
      $leads = json_decode($content, true) ?? [];
   }
   $leads[] = array_merge($data, ['timestamp' => date('Y-m-d H:i:s')]);
   file_put_contents($backupFile, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ---------- ОБРАБОТКА ЗАПРОСА ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $name = htmlspecialchars(trim($_POST['name'] ?? ''));
   $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
   $email = htmlspecialchars(trim($_POST['email'] ?? ''));
   $date = htmlspecialchars(trim($_POST['excursion_date'] ?? ''));
   $message = htmlspecialchars(trim($_POST['message'] ?? ''));

   if (empty($name) || empty($phone)) {
      echo json_encode(['success' => false, 'message' => 'Пожалуйста, укажите имя и телефон']);
      exit;
   }

   $subject = "Новая заявка на экскурсию от $name";
   $body = "Имя: $name\nТелефон: $phone\nEmail: $email\nЖелаемая дата: $date\nСообщение: $message\n---\nОтправлено с сайта Мириам Горовацкой";
   $fromEmail = !empty($email) ? $email : 'no-reply@gorovatskaya-tours.ru';

   $sent = false;
   $errorMsg = '';

   if ($useSMTP && file_exists($phpmailerPath)) {
      $result = sendWithPHPMailer($toEmail, $subject, $body, $fromEmail, $name);
      if ($result['success']) {
         $sent = true;
      } else {
         $errorMsg = $result['message'];
      }
   }

   if (!$sent) {
      // fallback на mail()
      if (sendWithNativeMail($toEmail, $subject, $body, $fromEmail)) {
         $sent = true;
      } else {
         $errorMsg = 'Не удалось отправить письмо через mail()';
      }
   }

   // Если отправка не удалась — сохраняем заявку в файл (чтобы не потерять)
   if (!$sent) {
      saveLeadToFile([
         'name' => $name,
         'phone' => $phone,
         'email' => $email,
         'date' => $date,
         'message' => $message,
         'error' => $errorMsg
      ]);
      echo json_encode(['success' => false, 'message' => 'Заявка не отправлена по email, но сохранена на сервере. Мы свяжемся с вами вручную.']);
   } else {
      echo json_encode(['success' => true, 'message' => 'Заявка успешно отправлена!']);
   }
   exit;
} else {
   echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
}
?>