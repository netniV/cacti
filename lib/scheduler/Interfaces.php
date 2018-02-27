<?php
interface IScheduler {
	function getNextTimeSinceTime(DateTime $lastTime) : DateTime;
}

interface SchedulerAllowsMultiple {
}
