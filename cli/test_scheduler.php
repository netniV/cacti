<?php
function __($text) {
	return $text;
}

function GetTraceValue($trace, $key, $default = '') {
	if (array_key_exists($key, $trace)) {
		return $trace[$key];
	}
	return $default;
}

function GetStackTrace($traces) {
	foreach ($traces as $trace) {
		$args = "";
		if (array_key_exists('args',$trace)) {
			$argv = $trace['args'];
			$argc = sizeof($argv);

			$argn = 1;
			while ($argn < $argc)
			{
				$value = $argv[$argn];
				$type = "";
				if (is_array($value)) {
					$type="array(" . sizeof($value) . ")";
				} else {
					$type=gettype($value);
				}
				$args .= (strlen($args) ? ', ' : '');
				$args .= $type;
				$argn++;
			}
		}
		$type = GetTraceValue($trace,'type',":");

		$function = GetTraceValue($trace, 'function', '<unknown>');
		$class = GetTraceValue($trace, 'class', '');
		$line =	GetTraceValue($trace, 'line', '');
		$file =	GetTraceValue($trace, 'file', '<unknown>');

		$file_paths = pathinfo($file);

		$message = sprintf("\n%20s %2s %s(%s)",$class,$type,$function,$args);
		$stack = sprintf("%20s %2s %d (%20s)", $file_paths['basename'], 'at', $line, $file_paths['dirname']);
		echo "$message\n$stack\n";
	}
}

function GetDates($dateArray) {
	$first = true;
	$result = '';
	if (!($dateArray instanceOf Iterator || is_array($dateArray))) {
		$dateArray = array($dateArray);
	}

	$loop = 0;
	foreach ($dateArray as $nextTime) {
		$result .= ($loop++ % 3 == 0 ? "\n\t\t" : ', ') .$nextTime->format('Y-m-d H-i-s');
		$first = false;
	}
	return $result;
}

function PrintDebugVals($debugPrefix, $headerLine, $debugOutput) {
	$debugVals = sprintf("%s%s", $debugPrefix, $debugOutput);
	$debugVals .= str_repeat(' ', strlen($headerLine) - strlen($debugVals));
	printf("\n| %s |", $debugVals);
}

function PrintDebug(string $mode, SchedulerConfig $config, string $typeName, string $multi, DateTime $startTime, $nextTime) {
	$debugMode = sprintf("Mode: %15s, ", $mode);
	$debugPref = str_repeat(' ',strlen($debugMode));

	$debugText = sprintf("%sStartTime: %19s, Type: %d (%20s)",
		$debugMode, $startTime->format('Y-m-d h:i:s'), $config->Type, $typeName);
	$debugLine = str_repeat('-',strlen($debugText)+2);

	printf("\n+%s+\n| %s |", $debugLine, $debugText);
	PrintDebugVals($debugPref, $debugText, sprintf("Multi....: %3s, Repeat: %2d", $multi, $config->Repeat));

	switch ($config->Type) {
		case SchedulerType::Weekly:
		case SchedulerType::MonthlyOnDay:
			PrintDebugVals($debugPref, $debugText, sprintf("Days.....: %s", implode(",", $config->Day)));
	}

	switch ($config->Type) {
		case SchedulerType::MonthlyOnDay:
			PrintDebugVals($debugPref, $debugText, sprintf("Weeks....: %s", implode(",", $config->Week)));
	}

	switch ($config->Type) {
		case SchedulerType::Monthly:
		case SchedulerType::MonthlyOnDay:
			PrintDebugVals($debugPref, $debugText, sprintf("Months...: %s", implode(",", $config->Month)));
	}

	switch ($config->Type) {
		case SchedulerType::Monthly:
			PrintDebugVals($debugPref, $debugText, sprintf("Month Day: %s", implode(",", $config->DayOfMonth)));
	}

	printf("\n+%s+\n", $debugLine);
	print GetDates($nextTime)."\n";
}

function ReadKey($prompt)
{
	stream_set_blocking(STDIN, 0);
	readline_callback_handler_install($prompt, function() {});
	$char = stream_get_contents(STDIN, 1);
	readline_callback_handler_remove();
	$status = stream_get_meta_data(STDIN);
	stream_set_blocking(STDIN, 1);
	return $status['timed_out'] ? false : $char;
}

function PauseOrContinue(int $seconds) {
	$sec_time = time();
	print "\n";
	$prompt = "\rPress P to pause, ESC to Quit, SPACE to continue (%d): ";
	$spaces = str_repeat(' ', strlen($prompt)+1);
	while (true) {
		$micro_time = microtime(true);
		$sec_now = time();
		$diff = floor($sec_now - $sec_time);
		if ($diff > 0) {
			$sec_time = $sec_now;
			$seconds--;
		}

		if ($seconds <= 0) break;

		printf($prompt."                  ", $seconds);
		printf($prompt, $seconds);

		$paused = false;
		$loop = 0;
		while (true) {
			$loop++;
			$micro_now = microtime(true);
			//printf("\rLoop: %7d %d, Time: %0.10f, Paused: %-3s   ", $loop, $seconds, $micro_now - $micro_time, $paused ? 'Yes':'No');
			if (!$paused && $micro_now - $micro_time > 0.5) {
				break;
			}

			while ($char = ReadKey('')) {
				if ($char == 'P' || $char == 'p') {
					$paused = !$paused;
					if (!$paused) {
						$sec_time = time();
					}
				} elseif ($char == ' ') {
					$seconds = 0;
					break;
				} elseif (ord($char) == 27) {
					printf("\r%s\r",$spaces);
					exit;
				}
			}
			usleep(100000);
		}
	}

	printf("\r%s\r",$spaces);
}

function DailyCheck($days, $startTime,$config) {
	try {
		$config->Type=SchedulerType::Daily;
		$config->Repeat=$days;
		$nextTime = Scheduler::getNextTime($startTime, $config);
		PrintDebug("Daily", $config, '', 'No', $startTime, $nextTime);
	} catch (Exception $e) {
		echo 'Daily ['.$config->Type.'-'.$config->Repeat.'] Failed to perform check due to \''.$e->getMessage().'\''."\n";
		GetStackTrace($e->getTrace());
	}
	PauseOrContinue(15);
}

function WeeklyCheck($days,$weekdays, $startTime,$config) {
	try {
		$config->Type=SchedulerType::Weekly;
		$config->Day = $weekdays;
		$config->Repeat=$days;
		$nextTime = Scheduler::getNextTime($startTime, $config);
		PrintDebug("Weekly", $config, '', 'No', $startTime, $nextTime);

		$nextTime = Scheduler::getNextTimes($startTime, 8, $config);
		PrintDebug("Weekly", $config, '', 'Yes', $startTime, $nextTime);
	} catch (Exception $e) {
		echo 'Weekly ['.$config->Type.'-'.$config->Repeat.'] Failed to perform check due to \''.$e->getMessage().'\''."\n";
		GetStackTrace($e->getTrace());
	}
	PauseOrContinue(15);
}

function MonthlyCheck($days,$months, $startTime,$config) {
	try {
		$config->Type=SchedulerType::Monthly;
		$config->Month = $months;
		$config->DayOfMonth = $days;

		$nextTime = Scheduler::getNextTime($startTime, $config);
		PrintDebug("Monthly", $config, '', 'No', $startTime, $nextTime);

		$nextTime = Scheduler::getNextTimes($startTime, 8, $config);
		PrintDebug("Monthly", $config, '', 'Yes', $startTime, $nextTime);
	} catch (Exception $e) {
		echo 'Monthly ['.$config->Type.'-'.$config->Repeat.'] Failed to perform check due to \''.$e->getMessage().'\''."\n";
		GetStackTrace($e->getTrace());
	}
	PauseOrContinue(15);
}

function MonthlyOnDayCheck($days,$weeks,$months, $startTime,$config) {
	try {
		$config->Type=SchedulerType::MonthlyOnDay;
		$config->Month = $months;
		$config->Week = $weeks;
		$config->Day = $days;

		$nextTime = Scheduler::getNextTime($startTime, $config);
		PrintDebug("MonthlyOnDay", $config, '', 'No', $startTime, $nextTime);

		$nextTime = Scheduler::getNextTimes($startTime, 8, $config);
		PrintDebug("MonthlyOnDay", $config, '', 'Yes', $startTime, $nextTime);
	} catch (Exception $e) {
		echo 'MonthlyOnDay ['.$config->Type.'-'.$config->Repeat.'] Failed to perform check due to \''.$e->getMessage().'\''."\n";
		GetStackTrace($e->getTrace());
	}
	PauseOrContinue(15);
}

include(dirname(__FILE__).'/../lib/scheduler.php');

$config = new SchedulerConfig();
$startTime = new DateTime("1-1-2018 00:00:00");
DailyCheck(1,$startTime,$config);
Dailycheck(5,$startTime,$config);
DailyCheck('as',$startTime,$config);
DailyCheck(10,$startTime,$config);
DailyCheck(15,$startTime,$config);
WeeklyCheck(2,array(3,5),$startTime,$config);
MonthlyCheck(array(3,20),array(1,4,6),$startTime,$config);
MonthlyCheck(array(3,20),array(1,4,6),$startTime,$config);
MonthlyOnDayCheck(array(3,7),array(1,3),array(1,4,7),$startTime,$config);
