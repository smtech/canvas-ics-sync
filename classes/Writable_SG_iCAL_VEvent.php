<?php

class Writable_SG_iCAL_VEvent extends SG_iCal_VEvent {
	
	public function __construct() {
		switch (func_num_args()) {
			case 1: { // clone an SG_iCal_VEvent
				$sg_ical_vevent = func_get_arg(0);
				if ($sg_ical_vevent instanceof SG_iCal_VEvent) {
					$this->uid = $sg_ical_vevent->uid;
									
					$this->start = $sg_ical_vevent->start;
					$this->end = $sg_ical_vevent->end;
									
					$this->summary = $sg_ical_vevent->summary;
					$this->description = $sg_ical_vevent->description;
					$this->location = $sg_ical_vevent->location;
									
					$this->laststart = $sg_ical_vevent->laststart;
					$this->lastend = $sg_ical_vevent->lastend;
									
					$this->recurrence = $sg_ical_vevent->recurrence; //RRULE
					$this->recurex = $sg_ical_vevent->recurex;    //EXRULE
					$this->excluded = $sg_ical_vevent->excluded;   //EXDATE(s)
					$this->added = $sg_ical_vevent->added;      //RDATE(s)
									
					$this->freq = $sg_ical_vevent->freq; //getFrequency() SG_iCal_Freq
									
					$this->data = $sg_ical_vevent->data;
				} else {
					throw new Writable_SG_iCAL_VEvent_Exception(
						'Expected SG_iCal_VEvent paraemter to clone',
						Writable_SG_iCAL_VEvent_Exception::INVALID_CONSTRUCTOR
					);
				}
				break;
			}
			case 2: { // pass through to parent class
				call_user_func_array(array(__CLASS__, 'parent::__construct'), func_get_args());
				break;
			}
			default: {
				throw new Writable_SG_iCAL_VEvent_Exception(
					'Wrong number of arguments to constructor',
					Writable_SG_iCAL_VEvent_Exception::INVALID_CONSTRUCTOR
				);
			}
		}
	}

	public function setSummary($summary) {
		$this->summary = $summary;
	}
	
	public function setDescription($description) {
		$this->description = $description;
	}
}

class Writable_SG_iCAL_VEvent_Exception {
	const INVALID_CONSTRUCTOR = 1;
}
	
?>