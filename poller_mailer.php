#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* we are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset ($_SERVER['argv'][0]) || isset ($_SERVER['REQUEST_METHOD']) || isset ($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting(E_ALL);
$dir = dirname(__FILE__);
chdir($dir);

/* record the start time */
$poller_start = microtime(true);

include ('./include/global.php');
include_once($config['include_path'] . '/vendor/phpmailer/PHPMailerAutoload.php');

global $config, $database_default, $archived, $purged;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$force    = false;
$archived = 0;
$purged   = 0;

if (sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--version' :
			case '-V' :
			case '-v' :
				display_version();
				exit;
			case '--help' :
			case '-H' :
			case '-h' :
				display_help();
				exit;
			case '--force' :
				$force = true;
				break;
			case '--debug' :
				$debug = true;
				break;
			default :
				echo 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

maint_debug('Checking for Items that need mailing');

/* are my tables already present? */
$count = db_fetch_cell('SELECT count(*)
	FROM mailer_item
	WHERE status = 1');

$items = db_fetch_assoc('SELECT * FROM mailer_item WHERE status = 1 AND retries < 3');
$failed = 0;
foreach ($items as $item) {
	/* PROCESS ITEMS BY USING PROCESS_MAIL FUNCTION */
	/* READ ALL DATA IN AT THIS POINT               */
}

$poller_end = microtime(true);
$string = sprintf('MAILER STATS: Time:%4.4f Processed:%s Failed:%s', ($poller_end - $poller_start), $count, $failed);
cacti_log($string, true, 'MAILER');

exit(0);

function maint_debug($message) {
	global $debug;

	if ($debug) {
		echo trim($message) . "\n";
	}
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Mailer Poller, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*
 * display_help
 * displays the usage of the function
 */
function display_help() {
	display_version();

	echo "\nusage: poller_mailer.php [--force] [--debug]\n\n";
	echo "Cacti's mailer poller.  This poller is repsonsible for executing periodic\n";
	echo "mailing activities for Cacti\n\n";
	echo "Optional:\n";
	echo "    --force   - Force immediate execution, e.g. for testing.\n";
	echo "    --debug   - Display verbose output during execution.\n\n";
}

/** mailer - function to send mails to users
 *  @arg $from - a string email address, or an array in array(email_address, name format)
 *  @arg $to - either a string of comma delimited email addresses, or an array of addresses in email_address => name format
 *  @arg $cc - either a string of comma delimited email addresses, or an array of addresses in email_address => name format
 *  @arg $bcc - either a string of comma delimited email addresses, or an array of addresses in email_address => name format
 *  @arg $replyto - a string email address, or an array in array(email_address, name format)
 *  @arg $subject - the email subject
 *  @arg $body - the email body, in HTML format.  If content_text is not set, the function will attempt to extract
 *       from the HTML format.
 *  @arg $body_text - the email body in TEXT format.  If set, it will override the stripping tags method
 *  @arg $attachments - the emails attachments as an array
 *  @arg $headers - an array of name value pairs representing custom headers.
 *  @arg $html - if set to true, html is the default, otherwise text format will be used
 */
function process_mail($from, $to, $cc, $bcc, $replyto, $subject, $body, $body_text = '', $attachments = '', $headers = '', $html = true) {
	global $config;

	// Set the to information
	if ($to == '') {
		return __('Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.');
	}

	/* perform data substitution */
	if (strpos($subject, '|date_time|') !== false) {
	    $date = read_config_option('date');
		if (!empty($date)) {
			$time = strtotime($date);
		} else {
			$time = time();
		}

		$subject = str_replace('|date_time|', date(CACTI_DATE_TIME_FORMAT, $time), $subject);
	}

	if (is_array($to)) {
		$toText = $to[1] . ' <' . $to[0] . '>';
	} else {
		$toText = $to;
	}

	if (is_array($from)) {
		$fromText = $from[1] . ' <' . $from[0] . '>';
	} else {
		$fromText = $from;
	}

	$body = str_replace('<SUBJECT>', $subject, $body);
	$body = str_replace('<TO>',      $toText, $body);
	$body = str_replace('<FROM>',    $fromText, $body);

	// Create the PHPMailer instance
	$mail = new PHPMailer;

	// Set a reasonable timeout of 5 seconds
	$timeout = read_config_option('settings_smtp_timeout');
	if (empty($timeout) || $timeout < 0 || $timeout > 300) {
		$mail->Timeout = 5;
	} else {
		$mail->Timeout = $timeout;
	}

	// Set the subject
	$mail->Subject = $subject;

	// Support i18n
	$mail->CharSet = 'UTF-8';
	$mail->Encoding = 'base64';

	$how = read_config_option('settings_how');
	if ($how < 0 || $how > 2) {
		$how = 0;
	}

	if ($how == 0) {
		$mail->isMail();
	} else if ($how == 1) {
		$mail->Sendmail = read_config_option('settings_sendmail_path');
		$mail->isSendmail();
	} else if ($how == 2) {
		$mail->isSMTP();
		$mail->Host     = read_config_option('settings_smtp_host');
		$mail->Port     = read_config_option('settings_smtp_port');

		if (read_config_option('settings_smtp_username') != '') {
			$mail->SMTPAuth = true;
			$mail->Username = read_config_option('settings_smtp_username');

			if (read_config_option('settings_smtp_password') != '') {
				$mail->Password = read_config_option('settings_smtp_password');
			}
		} else {
			$mail->SMTPAuth = false;
		}

		// Set a reasonable timeout of 5 seconds
		$timeout = read_config_option('settings_smtp_timeout');
		if (empty($timeout) || $timeout < 0 || $timeout > 300) {
			$mail->Timeout = 10;
		} else {
			$mail->Timeout = $timeout;
		}

		$secure  = read_config_option('settings_smtp_secure');
		if (!empty($secure) && $secure != 'none') {
			$mail->SMTPSecure = true;
			if (substr_count($mail->Host, ':') == 0) {
				$mail->Host = $secure . '://' . $mail->Host;
			}
		} else {
			$mail->SMTPAutoTLS = false;
			$mail->SMTPSecure = false;
		}
	}

	// Set the from information
	if (!is_array($from)) {
		$fromname = '';
		if ($from == '') {
			$fromname = read_config_option('settings_from_name');
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'Cacti@cacti.net';
			}

			if ($fromname == '') {
				$fromname = 'Cacti';
			}
		}

		$mail->setFrom($from, $fromname);
	} else {
		$mail->setFrom($from[0], $from[1]);
	}

	if (!is_array($to)) {
		$to = explode(',', $to);

		foreach($to as $t) {
			$t = trim($t);
			if ($t != '') {
				$mail->addAddress($t);
			}
		}
	} else {
		foreach($to as $email => $name) {
			$mail->addAddress($email, $name);
		}
	}

	if (!is_array($cc)) {
		if ($cc != '') {
			$cc = explode(',', $cc);
			foreach($cc as $c) {
				$c = trim($c);
				$mail->addCC($c);
			}
		}
	} else {
		foreach($cc as $email => $name) {
			$mail->addCC($email, $name);
		}
	}

	if (!is_array($bcc)) {
		if ($bcc != '') {
			$bcc = explode(',', $bcc);
			foreach($bcc as $bc) {
				$bc = trim($bc);
				$mail->addBCC($bc);
			}
		}
	} else {
		foreach($bcc as $email => $name) {
			$mail->addBCC($email, $name);
		}
	}

	if (!is_array($replyto)) {
		if ($replyto != '') {
			$mail->addReplyTo($replyto);
		}
	} else {
		$mail->addReplyTo($replyto[0], $replyto[1]);
	}

	// Set the wordwrap limits
	$wordwrap = read_config_option('settings_wordwrap');
	if ($wordwrap == '') {
		$wordwrap = 76;
	} elseif ($wordwrap > 9999) {
		$wordwrap = 9999;
	} elseif ($wordwrap < 0) {
		$wordwrap = 76;
	}

	$mail->WordWrap = $wordwrap;
	$mail->setWordWrap();

	$i = 0;

	// Handle Graph Attachments
	if (is_array($attachments) && sizeof($attachments) && substr_count($body, '<GRAPH>') > 0) {
		foreach($attachments as $attachment) {
			if ($attachment['attachment'] != '') {
				/* get content id and create attachment */
				$cid = getmypid() . '_' . $i . '@' . 'localhost';

				/* attempt to attach */
				if ($mail->addStringEmbeddedImage($attachment['attachment'], $cid, $attachment['filename'], 'base64', $attachment['mime_type'], $attachment['inline']) === false) {
					cacti_log('ERROR: ' . $mail->ErrorInfo, false);

					return $mail->ErrorInfo;
				}

				$body = str_replace('<GRAPH>', "<br><br><img src='cid:$cid'>", $body);

				$i++;
			} else {
				$body = str_replace('<GRAPH>' . $attachment['local_graph_id'] . '>', "<img src='" . $attachment['filename'] . "' ><br>Could not open!<br>" . $attachment['filename'], $body);
			}
		}
	} elseif (is_array($attachments) && sizeof($attachments) && substr_count($body, '<GRAPH:') > 0) {
		foreach($attachments as $attachment) {
			if ($attachment['attachment'] != '') {
				/* get content id and create attachment */
				$cid = getmypid() . '_' . $i . '@' . 'localhost';

				/* attempt to attach */
				if ($mail->addStringEmbeddedImage($attachment['attachment'], $cid, $attachment['filename'], 'base64', $attachment['mime_type'], $attachment['inline']) === false) {
					cacti_log('ERROR: ' . $mail->ErrorInfo, false);

					return $mail->ErrorInfo;
				}

				/* handle the body text */
				switch ($attachment['inline']) {
					case 'inline':
						$body = str_replace('<GRAPH:' . $attachment['local_graph_id'] . ':' . $attachment['timespan'] . '>', "<img src='cid:$cid' >", $body);
						break;
					case 'attachment':
						$body = str_replace('<GRAPH:' . $attachment['local_graph_id'] . ':' . $attachment['timespan'] . '>', '', $body);
						break;
				}

				$i++;
			} else {
				$body = str_replace('<GRAPH:' . $attachment['local_graph_id'] . ':' . $attachment['timespan'] . '>', "<img src='" . $attachment['filename'] . "' ><br>Could not open!<br>" . $attachment['filename'], $body);
			}
		}
	}

	/* process custom headers */
	if (is_array($headers) && sizeof($headers)) {
		foreach($headers as $name => $value) {
			$mail->addCustomHeader($name, $value);
		}
	}

	// Set both html and non-html bodies
	$text = array('text' => '', 'html' => '');
	if ($body_text != '' && $html == true) {
		$text['html']  = $body . '<br>';
		$text['text']  = $body_text;
		$mail->isHTML(true);
		$mail->Body    = $text['html'];
		$mail->AltBody = $text['text'];
	} elseif ($attachments == '' && $html == false) {
		if ($body_text != '') {
			$body = $body_text;
		} else {
			$body = str_replace('<br>',  "\n", $body);
			$body = str_replace('<BR>',  "\n", $body);
			$body = str_replace('</BR>', "\n", $body);
		}

		$text['text']  = strip_tags($body);
		$mail->isHTML(false);
		$mail->Body    = $text['text'];
	} elseif ($html == false) {
		$text['text']  = strip_tags($body);
		$mail->isHTML(false);
		$mail->Body    = $text['text'];
	} else {
		$text['html']  = $body . '<br>';
		$text['text']  = strip_tags(str_replace('<br>', "\n", $body));
		$mail->isHTML(true);
		$mail->Body    = $text['html'];
		$mail->AltBody = $text['text'];
	}

	if ($mail->send()) {
		cacti_log("Mail Successfully Sent to '" . $toText . "', Subject: '" . $mail->Subject . "'", false, 'MAILER');

		return '';
	} else {
		cacti_log("Mail Failed to '" . $toText . "', Subject: '" . $mail->Subject . "'", false, 'MAILER');

		return $mail->ErrorInfo;
	}
}
