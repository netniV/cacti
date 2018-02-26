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

	foreach ($dateArray as $nextTime) {
		if (!$first) $result .= ', ';
		$result .= "\n\t\t".$nextTime->format('Y-m-d H-i-s');
		$first = false;
	}
	return $result;
}

function DailyCheck($days, $startTime,$config) {
	try {
		$config->Type=SchedulerType::Daily;
		$config->Repeat=$days;
		$nextTimes = Scheduler::getNextTime($startTime, $config);
		if (!is_array($nextTimes)) {
			$nextTimes = array($nextTimes);
		}

		echo 'Daily ['.$config->Type.'-'.$config->Repeat.'] '.$startTime->format('Y-m-d H-i-s').' is now '.GetDates($nextTimes)."\n";
	} catch (Exception $e) {
		echo 'Daily ['.$config->Type.'-'.$config->Repeat.'] Failed to perform check due to \''.$e->getMessage().'\''."\n";
		GetStackTrace($e->getTrace());
	}
}

function WeeklyCheck($days,$weekdays, $startTime,$config) {
	try {
		$config->Type=SchedulerType::Weekly;
		$config->Day = $weekdays;
		$config->Repeat=$days;
		$nextTime = Scheduler::getNextTime($startTime, $config);
		echo 'Weekly Single ['.$config->Type.'-'.$config->Repeat.'] '.$startTime->format('Y-m-d H-i-s').' is now '.GetDates($nextTime)."\n";

		$nextTime = Scheduler::getNextTimes($startTime, 8, $config);
		echo 'Weekly Multi  ['.$config->Type.'-'.$config->Repeat.'] '.$startTime->format('Y-m-d H-i-s').' is now '.GetDates($nextTime)."\n";
	} catch (Exception $e) {
		echo 'Weekly ['.$config->Type.'-'.$config->Repeat.'] Failed to perform check due to \''.$e->getMessage().'\''."\n";
		GetStackTrace($e->getTrace());
	}
}

function MonthlyCheck($days,$months, $startTime,$config) {
	try {
		$config->Type=SchedulerType::Monthly;
		$config->Month = $months;
		$config->DayOfMonth = $days;

		$nextTime = Scheduler::getNextTime($startTime, $config);
		echo 'Monthly Single ['.$config->Type.'-'.$config->Repeat.'] '.$startTime->format('Y-m-d H-i-s').' is now '.GetDates($nextTime)."\n";

		$nextTime = Scheduler::getNextTimes($startTime, 8, $config);
		echo 'Monthly Multi  ['.$config->Type.'-'.$config->Repeat.'] '.$startTime->format('Y-m-d H-i-s').' is now '.GetDates($nextTime)."\n";
	} catch (Exception $e) {
		echo 'Monthly ['.$config->Type.'-'.$config->Repeat.'] Failed to perform check due to \''.$e->getMessage().'\''."\n";
		GetStackTrace($e->getTrace());
	}
}

function MonthlyOnDayCheck($days,$weeks,$months, $startTime,$config) {
	try {
		$config->Type=SchedulerType::MonthlyOnDay;
		$config->Month = $months;
		$config->Week = $weeks;
		$config->Day = $days;

		$nextTime = Scheduler::getNextTime($startTime, $config);
		echo 'MonthlyOnDay Single ['.$config->Type.'-'.$config->Repeat.'] '.$startTime->format('Y-m-d H-i-s').' is now '.GetDates($nextTime)."\n";

		$nextTime = Scheduler::getNextTimes($startTime, 8, $config);
		echo 'MonthlyOnDay Multi  ['.$config->Type.'-'.$config->Repeat.'] '.$startTime->format('Y-m-d H-i-s').' is now '.GetDates($nextTime)."\n";
	} catch (Exception $e) {
		echo 'MonthlyOnDay ['.$config->Type.'-'.$config->Repeat.'] Failed to perform check due to \''.$e->getMessage().'\''."\n";
		GetStackTrace($e->getTrace());
	}
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
