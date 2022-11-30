<?php
namespace util;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envio de email.
 */
class Mailer {
    private static $mailer = null; // Instância do PHPMailer

    /**
     * Obtém uma instância do PHPMailer já configurada
     * @return \PHPMailer\PHPMailer\PHPMailer
     */
    private static function getMailer() {
        if (!self::$mailer) {
            self::$mailer = new PHPMailer(true); // true enable exceptions
            self::$mailer->isSMTP();
            self::$mailer->SMTPDebug = 0;
            self::$mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            self::$mailer->Host = MAIL_HOST;
            self::$mailer->SMTPAuth = true;
            self::$mailer->Username = MAIL_USERNAME;
            self::$mailer->Password = MAIL_PASSWORD;
            self::$mailer->Port = MAIL_PORT;
            self::$mailer->setFrom(MAIL_USERNAME, MAIL_FROM);
            self::$mailer->isHTML(true);
        }
        return self::$mailer;
    }

    /**
     * Envia email para o destinatário informados com o assunto e mensagem informados.
     * @param string       $subject
     * @param string       $content
     * @param string|array $email Se desejar envio múltiplo informe um array onde cada item contenha um array com 'email' e 'nome'
     * @param string       [$nome] Não necessário informar se $email foi informado múltiplo
     */
    public static function send($subject, $content, $email, $nome = null) {
        $mailer = self::getMailer();
        $mailer->Subject = utf8_decode($subject);
        $mailer->Body = utf8_decode($content);
        $mailer->clearAddresses();
        if (gettype($email) == 'string') {
            $mailer->addAddress($email, utf8_decode($nome ?: $email));
        } else {
            foreach ($email as $recipient) {
                $mailer->addAddress($recipient['email'], utf8_decode($recipient['nome']));
            }
        }
        try {
            $mailer->send();
            return true;
        } catch (Exception $e) {
            return $mail->ErrorInfo;
        }
    }
}
?>