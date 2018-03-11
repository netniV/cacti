<?php
interface IScheduler {
	function getNextTimeSinceTime(DateTime $lastTime);
}

interface SchedulerAllowsMultiple {
}
