<?php
class SchedulerTimes implements Countable, ArrayAccess, SeekableIterator {
	protected $values = array();

	function __construct() {
		$this->values = array();
	}

	static function FromArray(array $values) {
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

	function toArray() {
		$array = clone $this->values;
		return $array;
	}

	function logit($name) {
		//file_put_contents('/tmp/array.log',sprintf("%s(%d)\n",$name, $this->position), FILE_APPEND);
		//file_put_contents('/tmp/array.log',str_replace("\n",'',var_export($this->values, true))."\n", FILE_APPEND);
	}

	function count() {
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

