<?php

namespace IsaacRankin\SocialFeed;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use SilverStripe\Core\Config\Config;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

if (!class_exists('Symbiote\QueuedJobs\Services\AbstractQueuedJob')) {
	return;
}

class SocialFeedCacheQueuedJob extends AbstractQueuedJob {
	/**
	 * Set queued job execution time to be 5 minutes before the cache expires
	 * by default.
	 *
	 * @var int
	 */
	private static $cache_time_offset = -300;


	public function createJob($prov) {
		$cache = $prov->getCacheFactory();

		$existing = QueuedJobDescriptor::get()->filter([
			"Implementation" => SocialFeedCacheQueuedJob::class,
			"JobStatus:not" => 'Complete'
		])->sort('StartAfter DESC')->first();

		if ($existing && $existing->ID > 0) {
			/*
			$runDate = $existing->StartAfter;
			$timeOffset = intval(Config::inst()->get(__CLASS__, 'cache_time_offset'));
			$runDate += $timeOffset;
			$runDate = date('Y-m-d H:i:s', $runDate);
			*/
		} else {
			$runDate = date('Y-m-d H:i:s', strtotime("+10 minutes"));
			$class = get_class();
			singleton(QueuedJobService::class)->queueJob(new $class($prov), $runDate);
		}


	}

	public function __construct($provider = null) {
		if ($provider) {
			$this->setObject($provider);
			$this->totalSteps = 1;
		}
	}

	/**
	 * Get the name of the job to show in the QueuedJobsAdmin.
	 */
	public function getTitle() {
		$provider = $this->getObject();
		return _t(
			'SocialFeed.SCHEDULEJOBTITLE',
			'Social Feed - Update cache for "{label}" ({class})',
			'',
			array(
				'class' => $provider->ClassName,
				'label' => $provider->Label
			)
		);
	}

	/**
	 * Gets the providers feed and stores it in the
	 * providers cache.
	 */
	public function process() {
		if ($prov = $this->getObject()) {
			$feed = $prov->getFeedUncached();
			$prov->setFeedCache($feed);
		}
		$this->currentStep = 1;
		$this->isComplete = true;
	}

	/**
	 * Called when the job is determined to be 'complete'
	 */
	public function afterComplete() {
		$prov = $this->getObject();
		if ($prov) {
			// Create next job
			singleton(__CLASS__)->createJob($prov);
		}
/*
		$old = QueuedJobDescriptor::get()->filter([
			"Implementation" => SocialFeedCacheQueuedJob::class,
			"JobStatus" => 'Complete',
			"RunAfter:LessThan" => date('Y-m-d H:i:s', strtotime("-2 days"))
		])->sort('StartAfter DESC')->first();
*/
	}
}
