<?php
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

