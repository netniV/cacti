<?php
class Scheduler {
	public static function WeekOfMonth($when = null) {
		if ($when === null) $when = new DateTime();
		$week = $when->format('U'); // weeks start on Sunday
		$firstWeekOfMonth = strftime('%U', strtotime($when->format('Y-m-01')));
		return 1 + ($week < $firstWeekOfMonth ? $week : $week - $firstWeekOfMonth);
	}

	public static function getNextTime(DateTime $startTime, SchedulerConfig $config) : DateTime {
		$schedulerClassType = SchedulerType::getNameFromValue($config->Type, true);
		$schedulerClass = "SchedulerType$schedulerClassType";

		if (!class_exists($schedulerClass) || $schedulerClassType == null) {
			throw new SchedulerException('Failed to find class '. $schedulerClass);
		}

		$scheduler = new $schedulerClass($config, $startTime);
		return $scheduler->getNextTime();
	}

	public static function getNextTimes(DateTime $startTime, int $count, SchedulerConfig $config) : SchedulerTimes {
		$schedulerClassType = SchedulerType::getNameFromValue($config->Type, true);
		$schedulerClass = "SchedulerType$schedulerClassType";

		if (!class_exists($schedulerClass) || $schedulerClassType == null) {
			throw new SchedulerException('Failed to find class '. $schedulerClass);
		}

		$scheduler = new $schedulerClass($config, $startTime);
		return $scheduler->getNextTimes($count);
	}
}
