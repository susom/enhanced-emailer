<?php
namespace Stanford\EnhancedEmailer;

require_once "emLoggerTrait.php";

use PHPMailer\PHPMailer\PHPMailer;
use \System;
use \Message;

class EnhancedEmailer extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    private $is_valid;
    private $last_error;
    private $email_config;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}

	public function redcap_email( $to, $from, $subject, $body, $cc, $bcc, $from_name, $attachments ) {

        // If EM Config is not valid, then skip and use default mailer
        if (!$this->getIsValid()) {
            $this->emError("Config is not valid - using fallback mailer: " . $this->last_error);
            return true;
        }

        try {
            // Get EM configuration
            $email_configs = $this->getEmailConfig();
            $default_config = null;
            $matched_config = null;
            foreach ($email_configs as $i => $email_config) {
                if ($email_config['is_default']) $default_config = $email_config;
                $result = preg_match($email_config['pattern'], $from);
                switch ($result) {
                    case 0:
                        // Not a match
                        break;
                    case 1:
                        // Match
                        $matched_config = $email_config;
                        break 2;
                    case false:
                        // Error
                        $this->emError("Invalid regular expression in config $i", $email_config);
                }
            }

            // Use default if available and no match
            if (is_null($matched_config) && !is_null($default_config)) {
                $this->emDebug("Using default config for $from");
                $matched_config = $default_config;
            }

            // Abort if no matched outbound configuration
            if (is_null($matched_config)) {
                $this->emDebug("Did not deliver email for $from because it matched no configurations and there was no default configuration");
                return false;
            }

            // Delivery email using $matched_config;
            $type = $matched_config['type'];        // smtp

            // Set from name if missing
            if ($type === 'smtp') {

                // TODO: Add context from What'sApp so we can create message class with appropriate helpers
                $m = new Message();

                $htmlBody = $body;
                $textBody = $m->formatPlainTextBody($body);


                // TODO: make sure displayname and replyToDisplayName are right

                $mail = new PHPMailer;
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                // HTML body
                $mail->msgHTML($htmlBody);
                // Format email body for text/plain: Replace HTML link with "LINKTEXT (URL)" and fix tabs and line breaks
                $mail->AltBody = $textBody;
                // From, Reply-To, and Return-Path. Also, set Display Name if possible.
                // From/Sender and Reply-To
                $mail->setFrom($from, $from_name, false);
                $mail->addReplyTo($from, $from_name);
                $mail->Sender = $from; // Return-Path; This also represents the -f header in mail().
                // To, CC, and BCC
                foreach (preg_split("/[;,]+/", $to) as $thisTo) {
                    $thisTo = trim($thisTo);
                    if ($thisTo == '') continue;
                    $mail->addAddress($thisTo);
                }
                if ($cc != "") {
                    foreach (preg_split("/[;,]+/", $cc) as $thisCc) {
                        $thisCc = trim($thisCc);
                        if ($thisCc == '') continue;
                        $mail->addCC($thisCc);
                    }
                }
                if ($bcc != "") {
                    foreach (preg_split("/[;,]+/", $bcc) as $thisBcc) {
                        $thisBcc = trim($thisBcc);
                        if ($thisBcc == '') continue;
                        $mail->addBCC($thisBcc);
                    }
                }
                // Attachments
                $enforceProtectedEmail = false;     // ADDED BY ANDY

                // TODO: Also not sure about cids...
                // if (!empty($attachments) && !$enforceProtectedEmail) {
                //     foreach ($attachments as $attachmentName=>$this_attachment_path) {
                //         $cid = isset($this->cids[$this_attachment_path]) ? $this->cids[$this_attachment_path] : null;
                //         if ($cid == null) {
                //             $mail->addAttachment($this_attachment_path, $attachmentName);
                //         } else {
                //             $mail->addAttachment($this_attachment_path, $cid, PHPMailer::ENCODING_BASE64, '', 'inline');
                //         }
                //     }
                // }

                // $this->logEmailContent($this->getFrom(), implode("; ", preg_split("/[;,]+/", $this->getTo() ?? "")), $this->formatPlainTextBody($this->getBody()), 'EMAIL', $emailCategory, $this->project_id, $this->record,
                //     implode("; ", preg_split("/[;,]+/", $this->getCc() ?? "")), implode("; ", preg_split("/[;,]+/", $this->getBcc() ?? "")), $this->getSubject() ?? "", $this->getBody() ?? "", $this->getAttachmentsWithNames(), $enforceProtectedEmail, $this->event_id, $this->form, $this->instance, $lang_id);

                // If we're using Protected Email mode, then replace the email body
                // if ($enforceProtectedEmail) {
                //     $cidToAdd = $this->setProtectedBody($lang_id);
                //     // Add the logo CID manually to attachments (because the img-to-CID code was already run on the real email text beforehand)
                //     if (!empty($cidToAdd)) {
                //         $mail->addAttachment($cidToAdd['filename'], $cidToAdd['cid'], PHPMailer::ENCODING_BASE64, '', 'inline');
                //     }
                //     // Modify existing email body with new text
                //     $mail->msgHTML($this->getBody());
                //     $mail->AltBody = $this->formatPlainTextBody($this->getBody());
                // }

                // Send it
                $sentSuccessfully = $mail->send();
                // Add error message, if failed to send
                if (!$sentSuccessfully) {
                    $this->emError("Error sending: " . $mail->ErrorInfo);
                    // $this->ErrorInfo = $mail->ErrorInfo;
                } else {
                    $this->emDebug("Mail Sent!");
                    // $this->logSuccessfulSend();
                }

                // Return boolean for success/fail
                // return $sentSuccessfully;
                return false;
            }
        } catch (\Exception $e) {
            $this->emError("Exception: " . $e->getMessage(), $e->getTrace());
        }

	}


	public function redcap_module_save_configuration( $project_id ) {
        // TODO: Check for valid configuration
	}



    // Quick check for valid config
    private function getIsValid() {
        if(is_null($this->is_valid)) {
            // Check for valid config
            $this->is_valid = $this->getSystemSetting("is-valid");
        }
        return $this->is_valid;
    }


    private function getEmailConfig() {
        if (empty($this->config)) {
            $this->email_config = $this->getSystemSetting('email-config');
        }
        return $this->email_config;
    }


}
