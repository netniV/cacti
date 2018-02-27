<?php
abstract class SchedulerTypeBase implements IScheduler {
	private $startTime;
	private $lastTime;
	private $config;

	function __construct(SchedulerConfig $config, DateTime $startTime) {
		$this->lastTime = new DateTime();
		$this->lastTime->setTimestamp(0);

		$this->setConfig($config);
		$this->setStartTime($startTime);
	}

	function setStartTime(DateTime $startTime) {
		$this->startTime = clone $startTime;
	}

	function getStartTime() : DateTime {
		$dateTime = clone $this->startTime;
		return $dateTime;
	}

	function setLastTime(DateTime $lastTime) {
		$this->lastTime = clone $lastTime;
	}

	function getLastTime() : DateTime {
		$dateTime = clone $this->lastTime;
		return ($this->startTime > $dateTime) ? $this->startTime : $dateTime;
	}

	function setConfig(SchedulerConfig $config) {
		$this->config = clone $config;
	}

	function getConfig() : SchedulerConfig {
		$config = clone $this->config;
		return $config;
	}

	function getNextTime() : DateTime {
		return $this->getNextTimeSinceTime($this->lastTime);
	}

	function getNextTimes(int $count) : SchedulerTimes  {
		$sinceTime = $this->getLastTime();
		return $this->getNextTimesSinceTime($count, $sinceTime);
	}

	function getNextTimesSinceTime(int $count, DateTime $dateTime) {
		$times = new SchedulerTimes();

		while ($count > 0) {
			$count--;

			$dateTime = $this->getNextTimeSinceTime($dateTime);
			$times->addTime($dateTime);
		}

		return $times;
	}
}

class SchedulerTypeManual extends SchedulerTypeBase implements IScheduler {
	function getNextTimeSinceTime(DateTime $sinceTime) : DateTime {
		$now = new DateTime();
		return ($now < $sinceTime) ? $sinceTime : $now;
	}
}

class SchedulerTypeDaily extends SchedulerTypeBase implements IScheduler {
	function getNextTimeSinceTime(DateTime $sinceTime) : DateTime {
		$dateTime = clone $sinceTime;
		$dateTime->add(new DateInterval('P'.$this->getConfig()->Repeat.'D'));
		return $dateTime;
	}
}

class SchedulerTypeWeekly extends SchedulerTypeBase implements IScheduler {
	function getNextTimeSinceTime(DateTime $sinceTime) : DateTime {
		$dateTime = clone $sinceTime;
		$validDays = $this->getConfig()->Day;
		$validTimes = new SchedulerTimes();
		$maxDays = 7 * ($this->getConfig()->Repeat + 1);

		//echo "validDays = ".implode(',',$schedulerConfig->Day).", MaxDays = $maxDays\n";
		while ($maxDays > 0) {
			$dateTime->add(new DateInterval('P1D'));
			$dayOfWeek=$dateTime->format('N');
			if (in_array($dayOfWeek,$validDays)) {
				return clone $dateTime;
			}
			$maxDays--;
		}

		throw new SchedulerException('Failed to find next run date');
/*
		switch ($schedulerConfig->Type) {
			case SchedulerType::Manual:
				return new DateTime();
			case SchedulerType::Daily:
				$dateTime = clone $startTime;
				return array($dateTime);
			case SchedulerType::Weekly:
				$dateTime = clone $startTime;

				break;
		}
		return null;
*/
		return;
	}
}

class SchedulerTypeMonthly extends SchedulerTypeBase implements IScheduler, SchedulerAllowsMultiple {
	function getNextTimeSinceTime(DateTime $sinceTime) : DateTime {
		$dateTime = clone $sinceTime;
		$dateYear = clone $sinceTime;
		$dateYear->modify('+1 year');

		$validDays = $this->getConfig()->DayOfMonth;
		$validMonths = $this->getConfig()->Month;

		$validTimes = new SchedulerTimes();

		//echo "validDays = ".implode(',',$schedulerConfig->Day).", MaxDays = $maxDays\n";
		while ($dateTime < $dateYear) {
			$dateTime->add(new DateInterval('P1D'));

			$month = $dateTime->format('m');
			$day   = $dateTime->format('d');

			$hasDay = (sizeof($validDays) == 0 || in_array($day, $validDays));
			$hasMonth = (sizeof($validMonths) == 0 || in_array($month, $validMonths));

			if ($hasDay && $hasMonth) {
				return clone $dateTime;
			}
		}

		throw new SchedulerException('Failed to find next run date');
/*
		switch ($schedulerConfig->Type) {
			case SchedulerType::Manual:
				return new DateTime();
			case SchedulerType::Daily:
				$dateTime = clone $startTime;
				return array($dateTime);
			case SchedulerType::Weekly:
				$dateTime = clone $startTime;

				break;
		}
		return null;
*/
		return;
	}
}

class SchedulerTypeMonthlyOnDay extends SchedulerTypeBase implements IScheduler, SchedulerAllowsMultiple {
	function getNextTimeSinceTime(DateTime $sinceTime) : DateTime {
		$dateTime = clone $sinceTime;
		$dateYear = clone $sinceTime;
		$dateYear->modify('+1 year');

		$validDays = $this->getConfig()->Day;
		$validMonths = $this->getConfig()->Month;
		$validWeeks = $this->getConfig()->Week;

		$validTimes = new SchedulerTimes();

		//echo "validDays = ".implode(',',$schedulerConfig->Day).", MaxDays = $maxDays\n";
		while ($dateTime < $dateYear) {
			$dateTime->add(new DateInterval('P1D'));

			$week  = Scheduler::WeekOfMonth($dateTime);
			$month = $dateTime->format('m');
			$day   = $dateTime->format('d');

			$hasDay   = (sizeof($validDays) == 0 || in_array($day, $validDays));
			$hasWeek  = (sizeof($validWeeks) == 0 || in_array($week, $validWeeks));
			$hasMonth = (sizeof($validMonths) == 0 || in_array($month, $validMonths));

			if ($hasDay && $hasMonth) {
				return clone $dateTime;
			}
		}

		throw new SchedulerException('Failed to find next run date');
/*
		switch ($schedulerConfig->Type) {
			case SchedulerType::Manual:
				return new DateTime();
			case SchedulerType::Daily:
				$dateTime = clone $startTime;
				return array($dateTime);
			case SchedulerType::Weekly:
				$dateTime = clone $startTime;

				break;
		}
		return null;
*/
		return;
	}
}

