<?php
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

