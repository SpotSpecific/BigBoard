<?php
/**
 * Handles communication with a JenkinsCI server
 *
 * @author Jake Worrell (jakeworrell.co.uk)
 * @author Spot Specific (http://www.spotspecific.com)
 */

require_once 'base/ContinuousIntegrationServerInterface.php';
require_once 'lib/Exceptions.php';

class JenkinsCI implements ContinuousIntegrationServerInterface{
	private $url;

	function __construct($url = null, $view = null) {
		if ($url==null) {
			$url = 'http://' . gethostname() . ':8080';
		}
		$this->url = $url;
		if ($view!=null) {
			$this->view = '/view/' . $view;
		} else {
			$this->view = '';
		}
	}

	public function getAllJobs() {
		$json = @file_get_contents($this->url . $this->view .'/api/json?tree=jobs[name,color]');
		if (!$json) {
			throw new BigBoardCIServerCommunicationException ("Error getting build data from Jenkins server at {$this->url}");
		}
		$jobs = json_decode($json);
		foreach ($jobs->jobs as $job) {
			$newjob = array('name'=>$job->name, 'status'=>$this->translateColorToStatus($job->color));
			if ($newjob['status'][0] == 'failed') {
				$newjob['blame'] = $this->getBlameFor($job->name);
			}
			$return[] = $newjob;
		}
		return ($return);
	}

	private function getBlameFor($jobName) {
		$job = rawurlencode($jobName);
		$json = file_get_contents($this->url . "/job/{$job}/lastBuild/api/json?tree=culprits[fullName]");
		$culprits = json_decode($json);

		if (empty($culprits->culprits)) {
			return "Unknown";
		}
		return $culprits->culprits[0]->fullName;

	}

	private function translateColorToStatus($color) {
		switch($color){
			case 'blue':
				return array('successful');
			case 'blue_anime':
				return array('successful','building');
			case 'red':
				return array('failed');
			case 'red_anime':
				return array('failed','building');
			case 'yellow':
				return array('unstable');
			case 'yellow_anime':
				return array('unstable', 'building');
			case 'aborted':
				return array('cancelled');
			case 'aborted_anime':
				return array('cancelled','building');
			case 'disabled':
				return array('disabled');
			default:
				return array('unknown');
		}
	}
}

?>
