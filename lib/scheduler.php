<?php
declare(strict_types=1);

interface IScheduler {
	function getNextTimeSinceTime(DateTime $lastTime) : DateTime;
}

interface SchedulerAllowsMultiple {
}

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

class SchedulerTimes implements Countable, ArrayAccess, SeekableIterator {
	protected $values = [];

	function __construct() {
		$this->values = [];
	}

	static function FromArray(array $values) : SchedulerDates {
		$dates = new SchedulerDates;
		foreach ($values as $value) {
			if ($value != null) {
				if ($value instanceOf DateTime) {
					$dates->addTime($value);
				} else {
					throw new SchedulerException('Invalid class \'' . get_class($value) . '\'');
				}
			}
		}
	}

	function addTime(DateTime $value) {
		$date = clone $value;
		$this->values[] = $date;
	}

	function removeTime(DateTime $value) {
		if (array_search($value, $values, true)) {
			unset($values[$value]);
		}
	}

	function toArray() : array {
		$array = clone $this->values;
		return $array;
	}

	function logit($name) {
		//file_put_contents('/tmp/array.log',sprintf("%s(%d)\n",$name, $this->position), FILE_APPEND);
		//file_put_contents('/tmp/array.log',str_replace("\n",'',var_export($this->values, true))."\n", FILE_APPEND);
	}

	function count() : int {
		$this->logit(__FUNCTION__);
		return sizeof($this->values);
	}

	public function seek($position) {
		if (!isset($this->values[$position])) {
			throw new OutOfBoundsException("invalid seek position ($position)");
		}

		$this->position = $position;
		$this->logit(__FUNCTION__);
	}

	public function rewind() {
		$this->position = 0;
		$this->logit(__FUNCTION__);
	}

	public function current() {
		$this->logit(__FUNCTION__);
		return $this->values[$this->position];
	}

	public function key() {
		$this->logit(__FUNCTION__);
		return $this->position;
	}

	public function next() {
		++$this->position;
		$this->logit(__FUNCTION__);
	}

	public function valid() {
		$this->logit(__FUNCTION__);
		return isset($this->values[$this->position]);
	}

	public function offsetSet($offset, $value) {
		if (is_null($offset)) {
			$this->values[] = $value;
		} else {
			$this->values[$offset] = $value;
		}
		$this->logit(__FUNCTION__);
	}

	public function offsetExists($offset) {
		return isset($this->values[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->values[$offset]);
		$this->logit(__FUNCTION__);
	}

	public function offsetGet($offset) {
		$this->logit(__FUNCTION__);
		return isset($this->values[$offset]) ? $this->values[$offset] : null;
	}
}

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

class SchedulerConfig {
	private $configData = array(
		'Type' => NULL, 'Repeat' => NULL, 'Day' => NULL, 'Week' => NULL,
		'Month' => NULL, 'Repeat' => NULL, 'DayOfMonth' => NULL
	);

	function __construct(
		$schedulerType = NULL, $schedulerRepeat = NULL, $schedulerDay = NULL,
		$schedulerWeek = NULL, $schedulerMonth = NULL, $schedulerDayOfMonth = NULL) {

		if (isset($schedulerType)) {
			$this->Type = $schedulerType;
		} else {
			$this->Type = SchedulerType::Manual;
		}

		if (isset($schedulerDay))		$this->Day = $schedulerDay;
		if (isset($schedulerWeek))		$this->Week = $schedulerWeek;
		if (isset($schedulerMonth))		$this->Month = $schedulerMonth;
		if (isset($schedulerRepeat))		$this->Repeat = $schedulerRepeat;
		if (isset($schedulerDayOfMonth))	$this->DayOfMonth = $schedulerDayOfMonth;
	}

	function Validate() {
		$type = $this->Type;
		switch ($type) {
			case SchedulerType::Manual:
				unset($this->Day);
				unset($this->Week);
				unset($this->Month);
				unset($this->DayOfMonth);
				unset($this->DayOfWeek);
				break;

			case SchedulerType::Daily:
				$this->DayOfWeek = "1,2,3,4,5,6,7";
				ValidateDayOfWeek($this->DayOfWeek);

				unset($this->Day);
				unset($this->Week);
				unset($this->Month);
				unset($this->DayOfMonth);
				break;

			case SchedulerType::Weekly:
				ValidateDayOfWeek($this->DayOfWeek);

				unset($this->Day);
				unset($this->Week);
				unset($this->Month);
				unset($this->DayOfMonth);
				break;

			case SchedulerType::Month:
				ValidateDayOfMonth($this->DayOfMonth);
				ValidateMonth($this->Month);

				unset($this->Day);
				unset($this->Week);
				unset($this->DayOfWeek);
				break;

			case SchedulerType::MonthlyOnDay:
				ValidateDayOfWeek($this->DayOfWeek);
				ValidateWeek($this->Week);
				ValidateMonth($this->Month);

				unset($this->DayOfMonth);
				unset($this->Day);
				break;

			default:
				throw new SchedulerValidationException(__('Type'),__('specified is invalid: ') . $this->Type);
		}
	}

	function ValidateDay(string $day) {
		if (!isset($day)) {
			throw new SchedulerValidationException(__('Day'), __(' has NOT been defined'));
		}
	}

	function ValidateDayOfWeek(string $DayOfWeek) {
		if (!isset($DayOfWeek)) {
			throw new SchedulerValidationException(__('Day Of Week'), __(' has NOT been defined'));
		}
	}

	function ValidateDayOfMonth(string $DayOfMonth) {
		if (!isset($DayOfMonth)) {
			throw new SchedulerValidationException(__('Day Of Month'), __(' has NOT been defined'));
		}
	}

	function ValidateWeek(string $Week) {
		if (!isset($Week)) {
			throw new SchedulerValidationException(__('Week'), __(' has NOT been defined'));
		}
	}

	function ValidateMonth(string $Month) {
		if (!isset($Month)) {
			throw new SchedulerValidationException(__('Month'), __(' has NOT been defined'));
		}
	}

	function __get($property) {
		switch ($property)
		{
			case 'Type':
			case 'Day':
			case 'Week':
			case 'Month':
			case 'Repeat':
			case 'DayOfMonth':
				return $this->configData[$property];
			default:
				throw new SchedulerValidationException($property, __('is undefined'));
		}
	}

	function __set($property, $value) {
		switch ($property) {
			case 'Type':
			case 'Day':
			case 'Week':
			case 'Month':
			case 'Repeat':
			case 'DayOfMonth':
				//MJV:Continue from here
				//Determinte if we actually want to set the value
				//by checking against the SchedulerType

				$className = 'Scheduler'.$property;
				//$classInterfaces = class_implements($className);

				$allowedTypes = SchedulerType::getSettingsArray();
				$allowMultiples = false;

				$interfaces = class_implements($className);
				if (in_array('SchedulerAllowsMultiple', $interfaces)) {
					$methodNameAllowed = 'getAllowedTypes';
					$allowedTypes = forward_static_call_array(array($className,$methodNameAllowed),array());
					$allowMultiples = true;
				}

				if (is_array($value) && $allowMultiples) {
					foreach ($value as $check_value) {
						$this->ValidateValue($property, $check_value);
					}
				} else {
					$this->ValidateValue($property, $value);
				}

				$this->configData[$property] = $value;
				break;
			default:
				$trace = debug_backtrace();

				throw new SchedulerException(
					'Undefined property \'' . $property . '\'' .
					' in ' . $trace[0]['file'] .
					' on line ' . $trace[0]['line']);
				break;
		}
	}

	private function ValidateValue($propertyName, $value) {
		$className = 'Scheduler'.$propertyName;

		$isValid = forward_static_call_array(array($className,'isValidValue'),array($value));
		if (!$isValid) {
			if (!isset($value)) {
				$value = 'NULL';
			} elseif (is_array($value)) {
				$value = json_encode($value);
			} else {
				$value = '\'' . $value . '\'';
			}

			throw new SchedulerValidationException($propertyName,
				__('has invalid value ') . $value);
		}
	}
}

abstract class SchedulerType extends SchedulerEnum {
	const Manual = 1;
	const Daily = 2;
	const Weekly = 3;
	const Monthly = 4;
	const MonthlyOnDay = 5;
}

abstract class SchedulerDay extends SchedulerEnum implements SchedulerAllowsMultiple {
	const Sunday = 1;
	const Monday = 2;
	const Tuesday = 3;
	const Wednesday = 4;
	const Thursday = 5;
	const Friday = 6;
	const Saturday = 7;

	public static function getAllowedTypes() {
		$types = [];
		$types[] = SchedulerType::Weekly;
		$types[] = SchedulerType::MonthlyOnDay;
		return $types;
	}
}

abstract class SchedulerMonth extends SchedulerEnum implements SchedulerAllowsMultiple {
	const January = 1;
	const February = 2;
	const March = 3;
	const April = 4;
	const May = 5;
	const June = 6;
	const July = 7;
	const August = 8;
	const September = 9;
	const October = 10;
	const November = 11;
	const December = 12;

	public static function getAllowedTypes() {
		$types = [];
		$types[] = SchedulerType::Monthly;
		$types[] = SchedulerType::MonthlyOnDay;
		return $types;
	}
}

abstract class SchedulerWeek extends SchedulerEnum implements SchedulerAllowsMultiple {
	const First = 1;
	const Second = 2;
	const Third = 3;
	const Fourth = 4;
	const Last = 32;

	public static function getAllowedTypes() {
		$types = [];
		$types[] = SchedulerType::Monthly;
		return $types;
	}
}

abstract class SchedulerRepeat extends SchedulerArray implements SchedulerAllowsMultiple {
	private static $repeat;

	protected static function ensureInitialized() {
		if (!isset(static::$repeat)) {
			static::$repeat = array();
			for ($scheduler_loop = 1; $scheduler_loop <= 14; $scheduler_loop++) {
				static::$repeat[$scheduler_loop] = $scheduler_loop;
			}
		}
	}

	protected static function getConstantValues() {
		static::ensureInitialized();
		return static::$repeat;
	}

	public static function getAllowedTypes() {
		return static::getConstantValues();
	}
}

abstract class SchedulerDayOfMonth extends SchedulerArray implements SchedulerAllowsMultiple {
	private static $day_of_month;

	protected static function ensureInitialized() {
		if (!isset(static::$day_of_month)) {
			static::$day_of_month = array();
			for ($scheduler_loop = 1; $scheduler_loop < 33; $scheduler_loop++) {
				static::$day_of_month[$scheduler_loop] = ($scheduler_loop == 32 ? __('Last') : $scheduler_loop);
			}
		}
	}

	public static function getAllowedTypes() {
		$types = [];
		$types[] = SchedulerType::Monthly;
		return $types;
	}

	protected static function getConstantValues() {
		static::ensureInitialized();
		return static::$day_of_month;
	}
}

abstract class SchedulerArray {
	protected static function getConstantValues() { die('missing function \'getConstantValues()\''); }
	protected static function ensureInitialised() { die('missing function \'ensureInitialized()\''); }

	public static function isValidName($name, $strict = false) {
		static::ensureInitialized();
		$constants = static::getConstantValues();

		if ($strict) {
			return array_key_exists($name, $constants);
		}

		$keys = array_map('strtolower', array_keys($constants));
		return in_array(strtolower($name), $keys);
	}

	public static function isValidValue($value, $strict = true) {
		return array_search($value, static::getConstantValues(), $strict);
	}

	public static function getNameFromValue($value, $strict = true) {
		$constants = array_values(static::getConstantValues());
		$last_name = null;
		foreach ($constants as $constant_name => $constant_value) {
			if ($constants_value == $value) {
				return $constant_name;
			} else if (!$strict && $constants_value > $value) {
				return $last_name;
			}
			$last_name = $constant_name;
		}
		return null;
	}

	public static function getSettingsArray() {
		return static::getConstantValues();
	}
}

abstract class SchedulerEnum {
	protected static function ensureInitialized() {}

	private static $constCacheArray = NULL;
	private static function getConstants() {
		if (self::$constCacheArray == NULL) {
			self::$constCacheArray = [];
		}

		$calledClass = get_called_class();
		if (!array_key_exists($calledClass, self::$constCacheArray)) {
			$reflect = new ReflectionClass($calledClass);
			self::$constCacheArray[$calledClass] = $reflect->getConstants();
		}

		return self::$constCacheArray[$calledClass];
	}

	public static function isValidName($name, $strict = false) {
		$constants = static::getConstants();

		if ($strict) {
			return array_key_exists($name, $constants);
		}

		$keys = array_map('strtolower', array_keys($constants));
		return in_array(strtolower($name), $keys);
	}

	public static function isValidValue($value, $strict = false) {
		$calledClass = get_called_class();
		$values = array_values(static::getConstants());
		return in_array($value, $values, $strict);
	}

	public static function getNameFromValue($value, $strict = false) {
		$constants = static::getConstants();
		$last_name = null;
		foreach ($constants as $constant_name => $constant_value) {
			if ($value == $constant_value) {
				return $constant_name;
			} else if (!$strict && $value > $constant_value) {
				return $last_name;
			}
			$last_name = $constant_name;
		}
		return null;
	}

	public static function getSettingsArray() {
		$constants = static::getConstants();

		$return_array = array();
		foreach ($constants as $constant => $value) {
			if (preg_match_all('/((?:^|[A-Z])[a-z]+)/',$constant,$parts)) {
				$parts_combined = implode(' ',$parts[1]);
				$return_array[$value] = __($parts_combined);
			}
		}

		return $return_array;
	}
}

class SchedulerException extends Exception {

	// Redefine the exception so message isn't optional
	function __construct($message, $code = 0, Exception $previous = null) {
		// make sure everything is assigned properly
		parent::__construct($message, $code, $previous);
	}

	// custom string representation of object
	function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}

class SchedulerValidationException extends Exception {
	private $field = NULL;

	// Redefine the exception so message isn't optional
	function __construct($field, $message, $code = 0, Exception $previous = null) {
		// make sure everything is assigned properly
        	parent::__construct($field . ' ' . $message, $code, $previous);
		$this->field = $field;
	}

	function Field() {
		return isset($this->field) ? $this->field : '';
	}

	// custom string representation of object
	function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}
