<?php
namespace PHPQueue;
class Exception extends \Exception{}
class Base
{
	static public $queue_path = '';
	static public $worker_path = '';
	static private $all_queues = array();
	static private $all_workers = array();

	/**
	 * @param string $queue
	 * @param array $options
	 * @return \PHPQueue\JobQueue
	 */
	static public function getQueue($queue=null, $options=array())
	{
		if (empty($queue))
		{
			throw new \PHPQueue\Exception("Queue name is empty");
		}
		if ( empty(self::$all_queues[$queue]) )
		{
			$classFile = self::$queue_path . '/' . $queue . 'Queue.php';
			if ( !empty($options['classFile']) )
			{
				$classFile = $options['classFile'];
			}
			if (file_exists($classFile))
			{
				require_once $classFile;
			}
			else
			{
				throw new \PHPQueue\Exception("Queue file does not exist: $classFile");
			}

			$className =  "\\" . $queue . 'Queue';
			if ( !empty($options['className']) )
			{
				$className = $options['className'];
			}
			if (class_exists($className))
			{
				self::$all_queues[$queue] = new $className();
			}
			else
			{
				throw new \PHPQueue\Exception("Queue class does not exist: $className");
			}
		}
		return self::$all_queues[$queue];
	}

	/**
	 * @param \PHPQueue\JobQueue $queue
	 * @param array $newJob
	 * @return boolean
	 */
	static public function addJob($queue, $newJob=array())
	{
		if (!is_a($queue, '\\PHPQueue\\JobQueue'))
		{
			throw new \PHPQueue\Exception("Invalid queue object.");
		}
		if (empty($newJob))
		{
			throw new \PHPQueue\Exception("Invalid job data.");
		}
		$status = false;
		try
		{
			$queue->beforeAdd($newJob);
			$status = $queue->addJob($newJob);
			$queue->afterAdd();
		}
		catch (Exception $ex)
		{
			$queue->onError($ex);
			throw $ex;
		}
		return $status;
	}

	/**
	 * @param \PHPQueue\JobQueue $queue
	 * @param string $jobId
	 * @return \PHPQueue\Job
	 */
	static public function getJob($queue, $jobId=null)
	{
		if (!is_a($queue, '\\PHPQueue\\JobQueue'))
		{
			throw new \PHPQueue\Exception("Invalid queue object.");
		}
		$job = null;
		try
		{
			$queue->beforeGet();
			$job = $queue->getJob($jobId);
			$queue->afterGet();
		}
		catch (Exception $ex)
		{
			$queue->onError($ex);
			throw $ex;
		}
		return $job;
	}

	/**
	 * @param \PHPQueue\JobQueue $queue
	 * @param string $jobId
	 * @param mixed $resultData
	 * @return boolean
	 */
	static public function updateJob($queue, $jobId=null, $resultData=null)
	{
		if (!is_a($queue, '\\PHPQueue\\JobQueue'))
		{
			throw new \PHPQueue\Exception("Invalid queue object.");
		}
		$status = false;
		try
		{
			$queue->beforeUpdate();
			$queue->updateJob($jobId, $resultData);
			$status = $queue->clearJob($jobId);
			$queue->afterUpdate();
		}
		catch (Exception $ex)
		{
			$queue->onError($ex);
			$queue->releaseJob($jobId);
			throw $ex;
		}
		return $status;
	}

	/**
	 * @param string $workerName
	 * @param array $options
	 * @return \PHPQueue\Worker
	 * @throws \PHPQueue\Exception
	 */
	static public function getWorker($workerName=null, $options=array())
	{
		if (empty($workerName))
		{
			throw new \PHPQueue\Exception("Worker name is empty");
		}
		if ( empty(self::$all_workers[$workerName]) )
		{
			$classFile = self::$worker_path . '/' . $workerName . 'Worker.php';
			if ( !empty($options['classFile']) )
			{
				$classFile = $options['classFile'];
			}
			if ( file_exists($classFile) )
			{
				require_once $classFile;
			}
			else
			{
				throw new \PHPQueue\Exception("Worker file does not exist: $classFile");
			}

			$className = "\\" . $workerName . 'Worker';
			if ( !empty($options['className']) )
			{
				$className = $options['className'];
			}
			if ( class_exists($className) )
			{
				self::$all_workers[$workerName] = new $className();
			}
			else
			{
				throw new \PHPQueue\Exception("Worker class does not exist: $className");
			}
		}
		return self::$all_workers[$workerName];
	}

	/**
	 * @param \PHPQueue\Worker $worker
	 * @param \PHPQueue\Job $job
	 * @return \PHPQueue\Worker
	 * @throws \PHPQueue\Exception
	 */
	static public function workJob($worker, $job)
	{
		if (!is_a($worker, '\\PHPQueue\\Worker'))
		{
			throw new \PHPQueue\Exception("Invalid worker object.");
		}
		if (!is_a($job, '\\PHPQueue\\Job'))
		{
			throw new \PHPQueue\Exception("Invalid job object.");
		}
		try
		{
			$worker->beforeJob($job->data);
			$worker->runJob($job);
			$job->onSuccessful();
			$worker->afterJob();
			$worker->onSuccess();
		}
		catch (Exception $ex)
		{
			$worker->onError($ex);
			$job->onError();
			throw $ex;
		}
		return $worker;
	}

	/**
	 * Factory method to instantiate a copy of a backend class.
	 * @param string $type Case-sensitive class name.
	 * @param array $options Constuctor options.
	 * @return \PHPQueue\backend_classname
	 * @throws \PHPQueue\Exception
	 */
	static public function backendFactory($type=null, $options=array())
	{
		$backend_classname = '\\PHPQueue\\Backend\\' . $type;
		$obj = new $backend_classname($options);
		if (is_a($obj, $backend_classname))
		{
			return $obj;
		}
		else
		{
			throw new \PHPQueue\Exception("Invalid Backend object.");
		}
	}
}
?>