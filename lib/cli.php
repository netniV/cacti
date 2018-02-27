<?php

Cli::init();
abstract class Cli {
	private static $quiet = false;
	private static $highest;
	private static $codes;
	private static $func_help;

	public static function init() {
		if (!isset(Cli::$codes)) {
			Cli::RegisterExitInternal('EXIT_UNKNOWN',-1, "ERROR: Failed due to unknown reason\n");
			Cli::RegisterExitInternal('EXIT_NORMAL',  0, "");
			Cli::RegisterExitInternal('EXIT_ABNORMAL',1, "WARNING: Abnormal exit\n\n");
			Cli::RegisterExitInternal('EXIT_ARGERR',  2, "ERROR: Invalid Argument (%s)\n\n");
			Cli::RegisterExitInternal('EXIT_ARGNUM',  3, "ERROR: Argument is not numeric (%s)\n\n");
			Cli::RegisterExitInternal('EXIT_ARGMIS',  4, "ERROR: Argument requires value (%s)\n\n");
			Cli::RegisterExitInternal('EXIT_ARGDUP',  5, "ERROR: Multiple values specified for non-multi argument (%s)\n\n");
			Cli::RegisterExitInternal('EXIT_BADEXIT', 6, "ERROR: Bad exit value specified in Cli::RegisterExit() call: %d\n\n");
			Cli::RegisterExitInternal('EXIT_DUPEXIT', 7, "ERROR: Duplicate exit value specified in Cli::RegisterExit() call: %d (%s)\n\n");
			Cli::RegisterExitInternal('EXIT_DUPNAME', 8, "ERROR: Duplicate exit name specified in Cli::RegisterExit() call: %s (%d)\n\n");
		}
	}

	public static function RegisterHelpFunction($function) {
		if (function_exists($function)) {
			Cli::$func_help = $function;
		}
	}

	public static function RegisterExit($name, $text) {
		if (Cli::$highest < 100) {
			Cli::$highest = 100;
		}

		Cli::RegisterExitWithValue($name, ++Cli::$highest, $text);
	}

	public static function RegisterExitWithValue($name, $value, $text) {
		if (abs($value) < 100) {
			Cli::Exit(EXIT_BADEXIT, $value, 0);
		}
		Cli::RegisterExitInternal($name, $value, $text);
	}

	private static function RegisterExitInternal($name, $value, $text) {
		if (!isset(Cli::$codes)) {
			Cli::$codes = array();
		}

		if (array_key_exists($name, Cli::$codes)) {
			Cli::Exit(EXIT_DUPNAME, array($name, $value));
		}

		if (array_key_exists($value, Cli::$codes)) {
			Cli::Exit(EXIT_DUPEXIT, array($value, $name));
		}

		if ($value > Cli::$highest) {
			Cli::$highest = $value;
		}

		define($name,$value);
		Cli::$codes[$name] = $text;
		Cli::$codes[$value] = $text;
	}

	public static function Exit($exit_value,$args = array(),$display_help = 0) {
		if (!Cli::$quiet) {
			$function = Cli::$func_help;
			if ($display_help && function_exists($function)) {
				$function($exit_value);
			}

			if (!isset($args)) {
				$args = array();
			} else if (!is_array($args)) {
				$args = array($args);
			}

			if (!array_key_exists($exit_value,Cli::$codes)) {
				$format = Cli::$codes[EXIT_UNKNOWN];
			} else {
				$format = Cli::$codes[$exit_value];
			}
			call_user_func_array('printf', array_merge((array)$format, $args));
		}

		exit($exit_value);
	}

	function GetOpts($short, $long, &$remaining = null) {
		$remaining = '';
		$argv = $_SERVER['argv'];
		$argc = $_SERVER['argc'];
		$result = array();

		$options = array();
		Cli::GetShortOpts($options, $short);
		Cli::GetLongOpts($options, $long);

		$ignoreOptions = false;
		for ($loop = 1; $loop < $argc; $loop++) {
			$name = null;
			$value = null;

			$arg = $argv[$loop];
			if ($arg == '--') {
				$ignoreOptions = true;
			} else {
				$option = $ignoreOptions ? null : Cli::FindOpt($arg, $options);
				if ($option != null)
				{
					if ($option['value']) {
						if (!isset($option['result']) && $loop < $argc -1) {
							$option2 = Cli::FindOpt($argv[$loop+1], $options);
							if ($option2 == null ) {
								$option['result'] = $argv[$loop+1];
								$loop++;
							}
						}

						if (!$option['optional'] && !isset($option['result'])) {
							Cli::Exit(EXIT_ARGMIS,$option['text']);
						}
					}

					$name = $option['text'];
					$value = isset($option['result']) ? $option['result'] : '';
					if (array_key_exists($name, $result)) {
						$result_val = $result[$name];
						if (!is_array($result_val)) {
							$result_val = array($result_val);
						}

						$result_val[] = $value;
					} else {
						$result_val = $value;
					}

					$result[$name] = $result_val;
				} else {
					$remaining .= (strlen($remaining)?' ':'') . $arg;
				}
			}
		}

		return $result;
	}

	private static function GetLongOpts(array &$options, array &$long) {
		if (isset($long)) {
			if (!is_array($long)) {
				$long = array($long);
			}

			if (sizeof($long)) {
				$index = 0;
				foreach ($long as $long_text) {
					$long_text = trim($long_text);
					if (strlen($long_text) == 0 || !preg_match("~[A-Za-z0-9:\-]~", $long_text)) {
						Cli::Exit(EXIT_OPTERR,$long_text);
					}

					$long_val  = Cli::CheckOptString('value',$long_text);
					$long_opt  = Cli::CheckOptString('optional',$long_text);

					if ($long_opt) $long_text = substr($long_text, 0, -1);

					Cli::AddOpt($options, count($options), $long_text, $long_val, $long_opt);
				}
			}
		}
	}

	private static function GetShortOpts(array &$options, $short) {
		if (!preg_match("~[A-Za-z0-9:]~", $short)) {
			Cli::Exit(EXIT_OPTERR,$short);
		}

		$options = array();
		for ($loop = 0; $loop < strlen($short); $loop++) {
			$short_text = $short[$loop];
			$short_val = Cli::CheckOpt('value', $short, $loop);
			$short_opt = Cli::CheckOpt('value', $short, $loop);

			Cli::AddOpt($options, $loop, $short_text, $short_val, $short_opt);
		}
	}

	private static function FindOpt(string $arg, array $options) {
		$found = null;

		if (strlen($arg) && $arg[0] == '-') {
			while (strlen($arg) && $arg[0] == '-') {
				$arg = substr($arg,1);
			}

			foreach ($options as $option) {
				if ($arg == $option['text']) {
					$found = $option;
					unset($found['result']);
				}

				$length_arg = strlen($arg);
				$length_txt = strlen($option['text']);
				$substr_txt = substr($arg,0,$length_txt);

				//echo sprintf("%3d arg, %3d txt, %15s = %s\n", $length_arg, $length_txt, $option['text'], $substr_txt);
				if ($length_arg > $length_txt && $substr_txt == $option['text']) {
					$separator_pos = strlen($option['text']);
					$separator = $arg[$separator_pos];

					//echo "$arg $separator found\n";
					if ($separator == '=') {
						$found = $option;
						$separator_pos++;
						if ($separator_pos < strlen($arg)) {
							$substr_txt = substr($arg, $separator_pos);
							//echo "Setting $substr_txt\n";
							$found['result'] = $substr_txt;
						} else {
							//echo "Unsetting result\n";
							unset($found['result']);
						}
					}
				}
			}
		}

		//echo sprintf("Cli::Find('%s', options()) return %s%s\n",
		//	$arg,
		//	$found == null ? '<null>' : str_replace("\n","",var_export($found, true)),
		//	$found == null ? '' : ' '.(isset($found['result']) ? str_replace("\n","",var_export($found,true)) : '<null>'));
		return $found;
	}

	private static function AddOpt(array &$options, $index, $text, $val, $opt) {
		$option = array(
			'text' => $text,
			'value' => $val,
			'optional' => $opt
		);

		//echo sprintf("Adding option %2s (%3d%s%s)\n", $text, $index, ($val?' hasValue':''), ($opt?' hasOptional':''));
		$options[] = $option;
	}

	private static function CheckOpt(string $label, string $short, &$index) {
		$result = false;
		$colon = '<unset>';

		if ($index < strlen($short) - 1) {
			$colon = $short[$index+1];
			if ($colon == ':') {
				$index++;
				$result = true;
			}
		}

		//echo sprintf("%15s Cli::CheckOpt('%s', %3d) returned %-5s (%s)\n", $label, $short, $index, $result ? 'Yes' : 'No', $colon);
		return $result;
	}

	private static function CheckOptString($label, string &$text) {
		$result = false;
		$output = $text;
		$colon = '<unset>';

		if (strlen($text) > 1) {
			$colon = $text[strlen($text) - 1];
			if ($colon == ':') {
				$result = true;
				$output = substr($text, 0, - 1);
			}
		}

		//echo sprintf("%15s Cli::CheckOptString('%s returned %-5s (%s - %s)\n", $label, $text . '\')', $result ? 'Yes' : 'No', $colon, $output);
		$text = $output;
		return $result;
	}
}
