<?php
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
